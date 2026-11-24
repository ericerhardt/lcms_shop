<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Services\CheckoutTotals;
use App\Services\StoreSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

const SHIP_STRIPE_URL = 'https://api.stripe.com/v1/checkout/sessions';

function shipDetails(): array
{
    return [
        'email' => 'buyer@example.com',
        'name' => 'Pat Buyer',
        'address_line1' => '1 Warehouse Way',
        'city' => 'Dallas',
        'state' => 'TX',
        'postal_code' => '75201',
        'country' => 'US',
    ];
}

function checkoutWith(Product $product, int $quantity = 2)
{
    test()->withSession(['cart' => [$product->id => $quantity]]);

    $component = Livewire::test('pages::shop.checkout');

    foreach (shipDetails() as $property => $value) {
        $component->set($property, $value);
    }

    return $component;
}

beforeEach(function () {
    config(['services.stripe.secret' => 'sk_test_123']);
    Http::preventStrayRequests();
    Http::fake([SHIP_STRIPE_URL => Http::response(['id' => 'cs_ship_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_ship_1'])]);
    $this->product = Product::factory()->create(['price_cents' => 10000, 'stock_quantity' => 50]);
});

test('the shipping dropdown lists only active methods with their cost', function () {
    ShippingMethod::factory()->create(['carrier' => 'UPS Ground', 'timeframe' => '3-5 business days', 'price_cents' => 1800, 'position' => 1]);
    ShippingMethod::factory()->create(['carrier' => 'FedEx Express', 'timeframe' => 'Next day', 'price_cents' => 4500, 'position' => 2]);
    ShippingMethod::factory()->inactive()->create(['carrier' => 'Retired Carrier']);

    $this->withSession(['cart' => [$this->product->id => 1]]);

    $this->get(route('checkout'))
        ->assertOk()
        ->assertSeeInOrder(['UPS Ground - 3-5 business days', '$18.00', 'FedEx Express - Next day', '$45.00'])
        ->assertDontSee('Retired Carrier');
});

test('the first shipping method is preselected and its cost is added to the total', function () {
    $ups = ShippingMethod::factory()->create(['carrier' => 'UPS', 'timeframe' => '3-5 days', 'price_cents' => 1800, 'position' => 1]);
    $fedex = ShippingMethod::factory()->create(['carrier' => 'FedEx', 'timeframe' => 'Next day', 'price_cents' => 4500, 'position' => 2]);

    checkoutWith($this->product)
        ->assertSet('shipping_method_id', $ups->id)
        ->assertSeeText('$218.00')
        ->set('shipping_method_id', $fedex->id)
        ->assertSeeText('$245.00')
        ->assertSeeText('FedEx, Next day');
});

test('a shipping method is required when methods are available', function () {
    $inactive = ShippingMethod::factory()->inactive()->create();
    ShippingMethod::factory()->create();

    checkoutWith($this->product)
        ->set('shipping_method_id', null)
        ->call('pay')
        ->assertHasErrors(['shipping_method_id']);

    checkoutWith($this->product)
        ->set('shipping_method_id', $inactive->id)
        ->call('pay')
        ->assertHasErrors(['shipping_method_id']);

    expect(Order::count())->toBe(0);
});

test('checkout works with no shipping methods and charges no shipping', function () {
    checkoutWith($this->product)->call('pay')->assertHasNoErrors();

    $order = Order::sole();

    expect($order->shipping_cents)->toBe(0)
        ->and($order->shipping_carrier)->toBeNull()
        ->and($order->totalCents())->toBe(20000);
});

test('the order records shipping and tax and stripe is charged the full total', function () {
    app(StoreSettings::class)->save(['tax_enabled' => true, 'tax_rate' => '8.25', 'tax_label' => 'Texas sales tax']);
    $method = ShippingMethod::factory()->create(['carrier' => 'UPS', 'timeframe' => '3-5 days', 'price_cents' => 1800]);

    checkoutWith($this->product)->set('shipping_method_id', $method->id)->call('pay')->assertHasNoErrors();

    $order = Order::sole();

    expect($order->subtotal_cents)->toBe(20000)
        ->and($order->shipping_cents)->toBe(1800)
        ->and($order->shipping_carrier)->toBe('UPS')
        ->and($order->shipping_timeframe)->toBe('3-5 days')
        ->and($order->tax_cents)->toBe(1650)
        ->and((float) $order->tax_rate)->toBe(8.25)
        ->and($order->tax_label)->toBe('Texas sales tax')
        ->and($order->totalCents())->toBe(23450)
        ->and($order->formattedTotal())->toBe('$234.50');

    Http::assertSent(function (Request $request) {
        $lines = $request['line_items'];

        return count($lines) === 3
            && $lines[0]['price_data']['unit_amount'] === 10000
            && $lines[1]['price_data']['unit_amount'] === 1800
            && str_contains($lines[1]['price_data']['product_data']['name'], 'UPS')
            && $lines[2]['price_data']['unit_amount'] === 1650
            && str_contains($lines[2]['price_data']['product_data']['name'], 'Texas sales tax');
    });
});

test('a free shipping method adds no shipping line to stripe', function () {
    $pickup = ShippingMethod::factory()->create(['carrier' => 'Local pickup', 'price_cents' => 0]);

    checkoutWith($this->product)->set('shipping_method_id', $pickup->id)->call('pay')->assertHasNoErrors();

    expect(Order::sole()->shipping_carrier)->toBe('Local pickup');
    Http::assertSent(fn (Request $request) => count($request['line_items']) === 1);
});

test('tax can optionally apply to shipping, and rounds to the nearest cent', function () {
    $ship = ShippingMethod::factory()->make(['price_cents' => 1050]);
    $settings = app(StoreSettings::class);

    $settings->save(['tax_enabled' => true, 'tax_rate' => '8.25', 'tax_on_shipping' => false]);
    $itemsOnly = app(CheckoutTotals::class)->calculate(3333, $ship);

    $settings->save(['tax_on_shipping' => true]);
    $withShipping = app(CheckoutTotals::class)->calculate(3333, $ship);

    expect($itemsOnly['tax_cents'])->toBe(275)
        ->and($withShipping['tax_cents'])->toBe(362)
        ->and($withShipping['total_cents'])->toBe(3333 + 1050 + 362);
});

test('no tax is charged while tax is switched off or the rate is zero', function () {
    $settings = app(StoreSettings::class);

    $settings->save(['tax_enabled' => false, 'tax_rate' => '8.25']);
    expect(app(CheckoutTotals::class)->calculate(10000, null)['tax_cents'])->toBe(0);

    $settings->save(['tax_enabled' => true, 'tax_rate' => '0']);
    expect(app(CheckoutTotals::class)->calculate(10000, null)['tax_cents'])->toBe(0);
});

test('changing rates later does not change an existing order', function () {
    app(StoreSettings::class)->save(['tax_enabled' => true, 'tax_rate' => '8.25']);
    $method = ShippingMethod::factory()->create(['price_cents' => 1800]);

    checkoutWith($this->product)->set('shipping_method_id', $method->id)->call('pay');

    $method->update(['price_cents' => 9900]);
    app(StoreSettings::class)->save(['tax_rate' => '2']);

    expect(Order::sole()->totalCents())->toBe(23450);
});
