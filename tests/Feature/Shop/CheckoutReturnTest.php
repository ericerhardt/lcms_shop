<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Http;

const STRIPE_SESSION_URL = 'https://api.stripe.com/v1/checkout/sessions/cs_test_1';

function pendingOrderFor(Product $product, int $quantity): Order
{
    $order = Order::factory()->create(['stripe_session_id' => 'cs_test_1']);
    $order->items()->create([
        'product_id' => $product->id,
        'sku' => $product->sku,
        'name' => $product->name,
        'packaging_type' => $product->packaging_type->value,
        'quantity_per_package' => $product->quantity_per_package,
        'unit_price_cents' => $product->price_cents,
        'quantity' => $quantity,
    ]);

    return $order;
}

beforeEach(function () {
    config(['services.stripe.secret' => 'sk_test_123']);
    Http::preventStrayRequests();
});

test('a paid stripe session marks the order paid, deducts stock and clears the cart', function () {
    Http::fake([
        STRIPE_SESSION_URL => Http::response(['payment_status' => 'paid', 'payment_intent' => 'pi_123']),
    ]);

    $product = Product::factory()->create(['stock_quantity' => 10]);
    $order = pendingOrderFor($product, 4);

    $this->withSession(['cart' => [$product->id => 4]])
        ->get(route('checkout.success', ['order' => $order->token, 'session_id' => 'cs_test_1']))
        ->assertOk()
        ->assertSee('Thank you for your order');

    expect($order->fresh())
        ->status->toBe(OrderStatus::Paid)
        ->stripe_payment_intent_id->toBe('pi_123')
        ->paid_at->not->toBeNull()
        ->and($product->fresh()->stock_quantity)->toBe(6)
        ->and(session('cart'))->toBeNull();
});

test('reloading the success page does not deduct stock twice', function () {
    Http::fake([
        STRIPE_SESSION_URL => Http::response(['payment_status' => 'paid', 'payment_intent' => 'pi_123']),
    ]);

    $product = Product::factory()->create(['stock_quantity' => 10]);
    $order = pendingOrderFor($product, 4);
    $url = route('checkout.success', ['order' => $order->token, 'session_id' => 'cs_test_1']);

    $this->get($url)->assertOk();
    $this->get($url)->assertOk();

    expect($product->fresh()->stock_quantity)->toBe(6);
    Http::assertSentCount(1);
});

test('an unpaid stripe session leaves the order pending and keeps the cart', function () {
    Http::fake([
        STRIPE_SESSION_URL => Http::response(['payment_status' => 'unpaid']),
    ]);

    $product = Product::factory()->create(['stock_quantity' => 10]);
    $order = pendingOrderFor($product, 4);

    $this->withSession(['cart' => [$product->id => 4]])
        ->get(route('checkout.success', ['order' => $order->token, 'session_id' => 'cs_test_1']))
        ->assertOk()
        ->assertSee('We have not received your payment yet');

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($product->fresh()->stock_quantity)->toBe(10)
        ->and(session('cart'))->not->toBeNull();
});

test('the success page returns 404 when the session id does not belong to the order', function () {
    $product = Product::factory()->create();
    $order = pendingOrderFor($product, 1);

    $this->get(route('checkout.success', ['order' => $order->token, 'session_id' => 'cs_other']))->assertNotFound();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

test('the success page returns 404 for an unknown order token', function () {
    $this->get(route('checkout.success', ['order' => 'not-a-real-token', 'session_id' => 'cs_test_1']))->assertNotFound();
});

test('cancelling payment returns to the cart with a message', function () {
    $this->get(route('checkout.cancel'))
        ->assertRedirect(route('cart'))
        ->assertSessionHas('status');
});
