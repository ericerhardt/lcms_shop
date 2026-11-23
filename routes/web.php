<?php

use App\Http\Controllers\CheckoutReturnController;
use App\Http\Controllers\InventoryExportController;
use Illuminate\Support\Facades\Route;

// Storefront (guests and customers)
Route::livewire('/', 'pages::shop.catalog')->name('home');
Route::livewire('products/{product}', 'pages::shop.product')->name('products.show');
Route::livewire('volume-pricing', 'pages::shop.volume-pricing')->name('volume-pricing');
Route::livewire('cart', 'pages::shop.cart')->name('cart');
Route::livewire('checkout', 'pages::shop.checkout')->name('checkout');
Route::get('checkout/success', [CheckoutReturnController::class, 'success'])->name('checkout.success');
Route::get('checkout/cancel', [CheckoutReturnController::class, 'cancel'])->name('checkout.cancel');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('orders', 'pages::shop.orders')->name('orders');
});

// Admin
Route::middleware(['auth', 'verified', 'can:manage-shop'])->prefix('admin')->name('admin.')->group(function () {
    Route::redirect('/', 'admin/products');
    Route::livewire('products', 'pages::admin.products')->name('products');
    Route::livewire('products/create', 'pages::admin.product-form')->name('products.create');
    Route::livewire('products/{product}/edit', 'pages::admin.product-form')->name('products.edit');
    Route::livewire('custom-fields', 'pages::admin.custom-fields')->name('custom-fields');
    Route::livewire('orders', 'pages::admin.orders')->name('orders');
    Route::livewire('volume-requests', 'pages::admin.inquiries')->name('inquiries');
    Route::livewire('users', 'pages::admin.users')->name('users');
    Route::get('inventory/export', InventoryExportController::class)->name('inventory.export');
});

require __DIR__.'/settings.php';
