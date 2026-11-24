<?php

use App\Mail\OrderConfirmation;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderNotifier;
use App\Services\StoreSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

function paidableOrder(array $attributes = []): Order
{
    $product = Product::factory()->create(['name' => '4x6 Labels', 'sku' => 'LBL-4X6', 'price_cents' => 15200, 'stock_quantity' => 20]);
    $order = Order::factory()->create([
        'email' => 'buyer@example.com',
        'name' => 'Pat Buyer',
        'subtotal_cents' => 30400,
        'shipping_carrier' => 'UPS Ground',
        'shipping_timeframe' => '3-5 days',
        'shipping_cents' => 1800,
        'tax_label' => 'Sales tax',
        'tax_rate' => 8.25,
        'tax_cents' => 2508,
        'stripe_session_id' => 'cs_mail_1',
        ...$attributes,
    ]);
    $order->items()->create([
        'product_id' => $product->id,
        'sku' => $product->sku,
        'name' => $product->name,
        'packaging_type' => 'box',
        'quantity_per_package' => 4000,
        'unit_price_cents' => 15200,
        'quantity' => 2,
    ]);

    return $order;
}

beforeEach(function () {
    Mail::fake();
    app(StoreSettings::class)->save(['order_copy_emails' => 'orders@lcm.test, owner@lcm.test']);
});

test('paying an order emails the customer with the company copied', function () {
    $order = paidableOrder();

    $order->markPaid('pi_1');

    Mail::assertSent(OrderConfirmation::class, fn (OrderConfirmation $mail) => $mail->hasTo('buyer@example.com')
        && $mail->hasCc('orders@lcm.test')
        && $mail->hasCc('owner@lcm.test'));
    Mail::assertSent(OrderConfirmation::class, 1);

    expect($order->fresh()->confirmation_sent_at)->not->toBeNull();
});

test('the email lists the items, shipping, tax, total and address', function () {
    $order = paidableOrder(['address_line1' => '1 Warehouse Way', 'city' => 'Dallas', 'state' => 'TX', 'postal_code' => '75201']);
    $order->markPaid();

    Mail::assertSent(OrderConfirmation::class, function (OrderConfirmation $mail) use ($order) {
        $mail->assertSeeInHtml($order->number());
        $mail->assertSeeInHtml('4x6 Labels');
        $mail->assertSeeInHtml('LBL-4X6');
        $mail->assertSeeInHtml('UPS Ground');
        $mail->assertSeeInHtml('$18.00');
        $mail->assertSeeInHtml('$25.08');
        $mail->assertSeeInHtml('$347.08');
        $mail->assertSeeInHtml('1 Warehouse Way');

        return true;
    });
});

test('the admin wording, store name and reply-to are used with placeholders filled in', function () {
    app(StoreSettings::class)->save([
        'store_name' => 'LCM Test Store',
        'contact_email' => 'help@lcm.test',
        'email_subject' => '{store_name}: order {order_number} confirmed',
        'email_intro' => 'Hi {customer_name}, thanks for ordering!',
        'email_footer' => 'Call us any time.',
    ]);
    $order = paidableOrder();
    $order->markPaid();

    Mail::assertSent(OrderConfirmation::class, function (OrderConfirmation $mail) use ($order) {
        $mail->assertHasSubject("LCM Test Store: order {$order->number()} confirmed");
        $mail->assertSeeInHtml('Hi Pat Buyer, thanks for ordering!');
        $mail->assertSeeInHtml('Call us any time.');

        return $mail->hasReplyTo('help@lcm.test');
    });
});

test('the same order is never emailed twice, even if it is marked paid again', function () {
    $order = paidableOrder();

    $order->markPaid('pi_1');
    $order->markPaid('pi_1');
    app(OrderNotifier::class)->sendConfirmation($order->fresh());

    Mail::assertSent(OrderConfirmation::class, 1);
});

test('the customer is not listed twice when they are also a copy recipient', function () {
    app(StoreSettings::class)->save(['order_copy_emails' => 'Buyer@Example.com, orders@lcm.test']);

    paidableOrder()->markPaid();

    Mail::assertSent(OrderConfirmation::class, fn (OrderConfirmation $mail) => $mail->hasTo('buyer@example.com')
        && ! $mail->hasCc('buyer@example.com')
        && $mail->hasCc('orders@lcm.test'));
});

test('a stripe webhook that settles an order also sends the email', function () {
    config(['services.stripe.webhook_secret' => 'whsec_mail']);
    $order = paidableOrder();
    $payload = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_mail_1', 'payment_status' => 'paid', 'payment_intent' => 'pi_w']]]);
    $timestamp = now()->timestamp;
    $signature = "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_mail');

    $this->call('POST', route('stripe.webhook'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature], $payload)->assertOk();

    Mail::assertSent(OrderConfirmation::class, fn (OrderConfirmation $mail) => $mail->hasTo('buyer@example.com'));
    expect($order->fresh()->confirmation_sent_at)->not->toBeNull();
});

test('the return page sends the email when the customer lands back from stripe', function () {
    config(['services.stripe.secret' => 'sk_test_123']);
    Http::preventStrayRequests();
    Http::fake(['https://api.stripe.com/v1/checkout/sessions/cs_mail_1' => Http::response(['payment_status' => 'paid', 'payment_intent' => 'pi_r'])]);
    $order = paidableOrder();

    $this->get(route('checkout.success', ['order' => $order->token, 'session_id' => 'cs_mail_1']))
        ->assertOk()
        ->assertSee('A confirmation email is on its way');

    Mail::assertSent(OrderConfirmation::class, 1);
});

test('a mail failure does not break payment and the order can be re-sent later', function () {
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('smtp down'));
    $order = paidableOrder();

    $order->markPaid('pi_1');

    expect($order->fresh())
        ->status->value->toBe('paid')
        ->confirmation_sent_at->toBeNull();
});

test('an unpaid order sends no email', function () {
    paidableOrder();

    Mail::assertNothingSent();
});

test('an admin can send the confirmation again from the orders page', function () {
    $order = paidableOrder();
    $order->markPaid();
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.orders')
        ->assertSee('Send again')
        ->call('resendConfirmation', $order->id);

    Mail::assertSent(OrderConfirmation::class, 2);
});

test('the admin orders page shows the shipping and tax charged', function () {
    $order = paidableOrder();
    $order->markPaid();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::admin.orders')
        ->assertSee('UPS Ground, 3-5 days')
        ->assertSee('$25.08')
        ->assertSee('$347.08');
});
