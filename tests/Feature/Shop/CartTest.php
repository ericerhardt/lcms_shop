<?php

use App\Models\Product;
use App\Services\Cart;
use Livewire\Livewire;

test('the cart page shows the lines and the subtotal', function () {
    $product = Product::factory()->create(['price_cents' => 2500, 'stock_quantity' => 10]);

    $this->withSession(['cart' => [$product->id => 3]]);

    Livewire::test('pages::shop.cart')
        ->assertSee($product->name)
        ->assertSee('$75.00');
});

test('the cart page shows an empty state when nothing was added', function () {
    Livewire::test('pages::shop.cart')->assertSee('Your cart is empty');
});

test('updating a quantity above stock caps it at the available stock', function () {
    $product = Product::factory()->create(['stock_quantity' => 5]);

    $this->withSession(['cart' => [$product->id => 1]]);

    Livewire::test('pages::shop.cart')
        ->call('updateQuantity', $product->id, 50)
        ->assertDispatched('cart-updated');

    expect(app(Cart::class)->lines()->first()['quantity'])->toBe(5);
});

test('updating a quantity to zero removes the line', function () {
    $product = Product::factory()->create();

    $this->withSession(['cart' => [$product->id => 2]]);

    Livewire::test('pages::shop.cart')->call('updateQuantity', $product->id, 0);

    expect(session('cart'))->toBe([]);
});

test('removing a line empties the cart', function () {
    $product = Product::factory()->create();

    $this->withSession(['cart' => [$product->id => 2]]);

    Livewire::test('pages::shop.cart')->call('remove', $product->id);

    expect(app(Cart::class)->isEmpty())->toBeTrue();
});

test('products that became inactive or sold out are dropped from the cart', function () {
    $inactive = Product::factory()->inactive()->create();
    $soldOut = Product::factory()->outOfStock()->create();
    $available = Product::factory()->create(['stock_quantity' => 9]);

    $this->withSession(['cart' => [$inactive->id => 1, $soldOut->id => 1, $available->id => 2]]);

    $lines = app(Cart::class)->lines();

    expect($lines)->toHaveCount(1)
        ->and($lines->first()['product']->is($available))->toBeTrue();
});

test('the cart badge shows the total number of packages', function () {
    $first = Product::factory()->create(['stock_quantity' => 10]);
    $second = Product::factory()->create(['stock_quantity' => 10]);

    $this->withSession(['cart' => [$first->id => 2, $second->id => 3]]);

    Livewire::test('cart-badge')->assertSeeHtml('data-test="cart-count"')->assertSee('5');
});
