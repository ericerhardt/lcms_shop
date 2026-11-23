<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

const STRIPE_SESSIONS_URL = 'https://api.stripe.com/v1/checkout/sessions';

function checkoutDetails(array $overrides = []): array
{
    return [
        'email' => 'buyer@example.com',
        'name' => 'Pat Buyer',
        'company' => 'Buyer Co',
        'phone' => '555-0100',
        'address_line1' => '1 Warehouse Way',
        'address_line2' => '',
        'city' => 'Columbus',
        'state' => 'OH',
        'postal_code' => '43004',
        'country' => 'US',
        ...$overrides,
    ];
}

beforeEach(function () {
    Http::preventStrayRequests();
});

test('checkout redirects to the cart when it is empty', function () {
    Livewire::test('pages::shop.checkout')->assertRedirect(route('cart'));
});

test('checkout is available to guests', function () {
    $product = Product::factory()->create();
    $this->withSession(['cart' => [$product->id => 1]]);

    $this->get(route('checkout'))->assertOk()->assertSee('Checking out as a guest');
});

test('checkout prefills the contact details of a signed in customer', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $this->withSession(['cart' => [$product->id => 1]]);

    Livewire::actingAs($user)
        ->test('pages::shop.checkout')
        ->assertSet('email', $user->email)
        ->assertSet('name', $user->name);
});

test('checkout requires contact and shipping details', function () {
    $product = Product::factory()->create();
    $this->withSession(['cart' => [$product->id => 1]]);

    Livewire::test('pages::shop.checkout')
        ->call('pay')
        ->assertHasErrors(['email', 'name', 'address_line1', 'city', 'state', 'postal_code']);

    expect(Order::count())->toBe(0);
});

test('a guest creates a pending order and is sent to stripe to pay', function () {
    config(['services.stripe.secret' => 'sk_test_123']);
    Http::fake([
        STRIPE_SESSIONS_URL => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1']),
    ]);

    $product = Product::factory()->create(['sku' => 'LBL-1', 'price_cents' => 1295, 'quantity_per_package' => 500, 'stock_quantity' => 10]);
    $this->withSession(['cart' => [$product->id => 3]]);

    $component = Livewire::test('pages::shop.checkout');
    foreach (checkoutDetails() as $property => $value) {
        $component->set($property, $value);
    }
    $component->call('pay')
        ->assertHasNoErrors()
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');

    $order = Order::with('items')->sole();

    expect($order->status)->toBe(OrderStatus::Pending)
        ->and($order->user_id)->toBeNull()
        ->and($order->email)->toBe('buyer@example.com')
        ->and($order->subtotal_cents)->toBe(3885)
        ->and($order->stripe_session_id)->toBe('cs_test_1')
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->sku)->toBe('LBL-1')
        ->and($order->items->first()->unit_price_cents)->toBe(1295)
        ->and($order->items->first()->quantity)->toBe(3);

    Http::assertSent(fn (Request $request) => $request->url() === STRIPE_SESSIONS_URL
        && $request->hasHeader('Authorization', 'Bearer sk_test_123')
        && $request['mode'] === 'payment'
        && $request['customer_email'] === 'buyer@example.com'
        && $request['line_items'][0]['quantity'] === 3
        && $request['line_items'][0]['price_data']['unit_amount'] === 1295);
});

test('a signed in customer owns the order they place', function () {
    config(['services.stripe.secret' => 'sk_test_123']);
    Http::fake([
        STRIPE_SESSIONS_URL => Http::response(['id' => 'cs_test_2', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_2']),
    ]);

    $user = User::factory()->create();
    $product = Product::factory()->create(['stock_quantity' => 10]);
    $this->withSession(['cart' => [$product->id => 1]]);

    $component = Livewire::actingAs($user)->test('pages::shop.checkout');
    foreach (checkoutDetails(['email' => $user->email]) as $property => $value) {
        $component->set($property, $value);
    }
    $component->call('pay')->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_2');

    expect(Order::sole()->user_id)->toBe($user->id);
});

test('checkout explains when stripe is not configured and creates no order', function () {
    config(['services.stripe.secret' => null]);

    $product = Product::factory()->create();
    $this->withSession(['cart' => [$product->id => 1]]);

    $component = Livewire::test('pages::shop.checkout');
    foreach (checkoutDetails() as $property => $value) {
        $component->set($property, $value);
    }
    $component->call('pay')->assertSee('Online payments are not set up yet');

    expect(Order::count())->toBe(0);
    Http::assertNothingSent();
});

test('a stripe failure cancels the order and shows an error', function () {
    config(['services.stripe.secret' => 'sk_test_123']);
    Http::fake([
        STRIPE_SESSIONS_URL => Http::response(['error' => ['message' => 'Invalid API Key']], 401),
    ]);

    $product = Product::factory()->create();
    $this->withSession(['cart' => [$product->id => 1]]);

    $component = Livewire::test('pages::shop.checkout');
    foreach (checkoutDetails() as $property => $value) {
        $component->set($property, $value);
    }
    $component->call('pay')->assertSee('We could not start the payment');

    expect(Order::sole()->status)->toBe(OrderStatus::Cancelled);
});
