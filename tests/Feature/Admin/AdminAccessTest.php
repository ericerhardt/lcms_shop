<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

dataset('admin routes', [
    'inventory' => fn () => route('admin.products'),
    'new item' => fn () => route('admin.products.create'),
    'custom fields' => fn () => route('admin.custom-fields'),
    'orders' => fn () => route('admin.orders'),
    'users' => fn () => route('admin.users'),
    'export' => fn () => route('admin.inventory.export'),
]);

test('guests are redirected to log in from admin pages', function (string $url) {
    $this->get($url)->assertRedirect(route('login'));
})->with('admin routes');

test('customers cannot open admin pages', function (string $url) {
    $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
})->with('admin routes');

test('admins can open admin pages', function (string $url) {
    $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
})->with('admin routes');

test('only admins may manage the shop', function () {
    expect(Gate::forUser(User::factory()->admin()->create())->allows('manage-shop'))->toBeTrue()
        ->and(Gate::forUser(User::factory()->create())->allows('manage-shop'))->toBeFalse();
});

test('admins can filter orders by status', function () {
    $paid = Order::factory()->paid()->create(['name' => 'Paid Customer']);
    $pending = Order::factory()->create(['name' => 'Pending Customer']);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::admin.orders')
        ->assertSee('Paid Customer')
        ->assertSee('Pending Customer')
        ->set('status', 'paid')
        ->assertSee($paid->name)
        ->assertDontSee($pending->name);
});

test('customers only see their own orders', function () {
    $user = User::factory()->create();
    $mine = Order::factory()->for($user)->create();
    $theirs = Order::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::shop.orders')
        ->assertSee($mine->number())
        ->assertDontSee($theirs->number());
});
