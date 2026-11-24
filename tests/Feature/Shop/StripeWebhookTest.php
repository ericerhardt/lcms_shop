<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Http;

const WEBHOOK_SECRET = 'whsec_test_secret';

function stripeEvent(string $type, array $session): string
{
    return json_encode(['id' => 'evt_1', 'type' => $type, 'data' => ['object' => $session]]);
}

function signedHeader(string $payload, ?int $timestamp = null, string $secret = WEBHOOK_SECRET): string
{
    $timestamp ??= now()->timestamp;

    return "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);
}

function postWebhook($test, string $payload, ?string $signature)
{
    return $test->call('POST', route('stripe.webhook'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => $signature,
    ], $payload);
}

function webhookOrder(int $quantity = 4, int $stock = 10, string $sessionId = 'cs_test_1'): array
{
    $product = Product::factory()->create(['stock_quantity' => $stock]);
    $order = Order::factory()->create(['stripe_session_id' => $sessionId]);
    $order->items()->create([
        'product_id' => $product->id,
        'sku' => $product->sku,
        'name' => $product->name,
        'packaging_type' => $product->packaging_type->value,
        'quantity_per_package' => $product->quantity_per_package,
        'unit_price_cents' => $product->price_cents,
        'quantity' => $quantity,
    ]);

    return [$order, $product];
}

beforeEach(function () {
    config(['services.stripe.webhook_secret' => WEBHOOK_SECRET]);
    Http::preventStrayRequests();
});

test('a completed paid session marks the order paid and deducts stock', function () {
    [$order, $product] = webhookOrder();
    $payload = stripeEvent('checkout.session.completed', ['id' => 'cs_test_1', 'payment_status' => 'paid', 'payment_intent' => 'pi_9']);

    postWebhook($this, $payload, signedHeader($payload))->assertOk();

    expect($order->fresh())
        ->status->toBe(OrderStatus::Paid)
        ->stripe_payment_intent_id->toBe('pi_9')
        ->and($product->fresh()->stock_quantity)->toBe(6);
});

test('delivering the same event twice does not deduct stock twice', function () {
    [$order, $product] = webhookOrder();
    $payload = stripeEvent('checkout.session.completed', ['id' => 'cs_test_1', 'payment_status' => 'paid', 'payment_intent' => 'pi_9']);

    postWebhook($this, $payload, signedHeader($payload))->assertOk();
    postWebhook($this, $payload, signedHeader($payload))->assertOk();

    expect($product->fresh()->stock_quantity)->toBe(6);
});

test('an order the customer already finished on the return page is left alone', function () {
    [$order, $product] = webhookOrder();
    $order->markPaid('pi_first');
    $payload = stripeEvent('checkout.session.completed', ['id' => 'cs_test_1', 'payment_status' => 'paid', 'payment_intent' => 'pi_other']);

    postWebhook($this, $payload, signedHeader($payload))->assertOk();

    expect($order->fresh()->stripe_payment_intent_id)->toBe('pi_first')
        ->and($product->fresh()->stock_quantity)->toBe(6);
});

test('a completed session that is not paid yet leaves the order pending until the async payment succeeds', function () {
    [$order] = webhookOrder();
    $unpaid = stripeEvent('checkout.session.completed', ['id' => 'cs_test_1', 'payment_status' => 'unpaid']);

    postWebhook($this, $unpaid, signedHeader($unpaid))->assertOk();
    expect($order->fresh()->status)->toBe(OrderStatus::Pending);

    $paid = stripeEvent('checkout.session.async_payment_succeeded', ['id' => 'cs_test_1', 'payment_status' => 'paid', 'payment_intent' => 'pi_async']);
    postWebhook($this, $paid, signedHeader($paid))->assertOk();
    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('an expired session cancels a pending order but never a paid one', function () {
    [$pending] = webhookOrder();
    $payload = stripeEvent('checkout.session.expired', ['id' => 'cs_test_1']);

    postWebhook($this, $payload, signedHeader($payload))->assertOk();
    expect($pending->fresh()->status)->toBe(OrderStatus::Cancelled);

    [$paid] = webhookOrder(sessionId: 'cs_test_2');
    $paid->markPaid('pi_x');
    $payload = stripeEvent('checkout.session.expired', ['id' => 'cs_test_2']);

    postWebhook($this, $payload, signedHeader($payload))->assertOk();
    expect($paid->fresh()->status)->toBe(OrderStatus::Paid);
});

test('unknown events and unknown sessions are acknowledged without changes', function () {
    [$order] = webhookOrder();

    $other = stripeEvent('charge.refunded', ['id' => 'ch_1']);
    postWebhook($this, $other, signedHeader($other))->assertOk();

    $unknown = stripeEvent('checkout.session.completed', ['id' => 'cs_missing', 'payment_status' => 'paid']);
    postWebhook($this, $unknown, signedHeader($unknown))->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

test('requests with a bad, missing, stale or tampered signature are rejected', function () {
    [$order] = webhookOrder();
    $payload = stripeEvent('checkout.session.completed', ['id' => 'cs_test_1', 'payment_status' => 'paid']);

    postWebhook($this, $payload, signedHeader($payload, secret: 'whsec_wrong'))->assertStatus(400);
    postWebhook($this, $payload, null)->assertStatus(400);
    postWebhook($this, $payload, signedHeader($payload, now()->subMinutes(10)->timestamp))->assertStatus(400);
    postWebhook($this, $payload.' ', signedHeader($payload))->assertStatus(400);

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

test('the webhook answers 503 when no signing secret is configured', function () {
    config(['services.stripe.webhook_secret' => null]);
    $payload = stripeEvent('checkout.session.completed', ['id' => 'cs_test_1', 'payment_status' => 'paid']);

    postWebhook($this, $payload, signedHeader($payload))->assertStatus(503);
});
