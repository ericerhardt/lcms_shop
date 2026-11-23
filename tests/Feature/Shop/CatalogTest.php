<?php

use App\Models\CustomField;
use App\Models\Product;
use Livewire\Livewire;

test('the catalog lists active products and hides inactive ones', function () {
    $visible = Product::factory()->create(['name' => 'Visible Roll']);
    $hidden = Product::factory()->inactive()->create(['name' => 'Hidden Roll']);

    Livewire::test('pages::shop.catalog')
        ->assertSee($visible->name)
        ->assertSee($visible->sku)
        ->assertDontSee($hidden->name);
});

test('the catalog can be searched by sku', function () {
    $match = Product::factory()->create(['sku' => 'ZEBRA-4X6', 'name' => 'Match Item']);
    $other = Product::factory()->create(['sku' => 'OTHER-1', 'name' => 'Other Item']);

    Livewire::test('pages::shop.catalog')
        ->set('search', 'zebra')
        ->assertSee($match->name)
        ->assertDontSee($other->name);
});

test('the catalog search also matches custom field values', function () {
    $field = CustomField::factory()->create(['name' => 'Adhesive']);
    $match = Product::factory()->create(['name' => 'Freezer Labels']);
    $match->customFields()->attach($field, ['value' => 'Deep freeze']);
    $other = Product::factory()->create(['name' => 'Plain Labels']);

    Livewire::test('pages::shop.catalog')
        ->set('search', 'deep freeze')
        ->assertSee($match->name)
        ->assertDontSee($other->name);
});

test('the catalog can be filtered by packaging type', function () {
    $box = Product::factory()->create(['packaging_type' => 'box', 'name' => 'Boxed Item']);
    $roll = Product::factory()->create(['packaging_type' => 'roll', 'name' => 'Rolled Item']);

    Livewire::test('pages::shop.catalog')
        ->set('packaging', 'roll')
        ->assertSee($roll->name)
        ->assertDontSee($box->name);
});

test('adding an item puts one package in the cart and notifies the cart badge', function () {
    $product = Product::factory()->create();

    Livewire::test('pages::shop.catalog')
        ->call('addToCart', $product->id)
        ->assertDispatched('cart-updated');

    expect(session('cart'))->toBe([$product->id => 1]);
});

test('an out of stock item cannot be added to the cart', function () {
    $product = Product::factory()->outOfStock()->create();

    Livewire::test('pages::shop.catalog')
        ->call('addToCart', $product->id)
        ->assertNotDispatched('cart-updated');

    expect(session('cart'))->toBeNull();
});

test('the product page shows packaging details and custom field values', function () {
    $field = CustomField::factory()->create(['name' => 'Core size']);
    $product = Product::factory()->create(['packaging_type' => 'case', 'quantity_per_package' => 6000]);
    $product->customFields()->attach($field, ['value' => '3 in']);

    $this->get(route('products.show', $product))
        ->assertOk()
        ->assertSeeInOrder(['Packaging', 'Case', 'Quantity per package', '6,000'])
        ->assertSee('Core size')
        ->assertSee('3 in');
});

test('the product page returns 404 for an inactive product', function () {
    $product = Product::factory()->inactive()->create();

    $this->get(route('products.show', $product))->assertNotFound();
});

test('the product page adds the chosen quantity to the cart', function () {
    $product = Product::factory()->create(['stock_quantity' => 20]);

    Livewire::test('pages::shop.product', ['product' => $product])
        ->set('quantity', 4)
        ->call('addToCart')
        ->assertHasNoErrors();

    expect(session('cart'))->toBe([$product->id => 4]);
});

test('the product page rejects a quantity above the available stock', function () {
    $product = Product::factory()->create(['stock_quantity' => 3]);

    Livewire::test('pages::shop.product', ['product' => $product])
        ->set('quantity', 4)
        ->call('addToCart')
        ->assertHasErrors(['quantity']);

    expect(session('cart'))->toBeNull();
});
