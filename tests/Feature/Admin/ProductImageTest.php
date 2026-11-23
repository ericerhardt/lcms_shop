<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    $this->admin = User::factory()->admin()->create();
});

function newProductForm(User $admin)
{
    return Livewire::actingAs($admin)
        ->test('pages::admin.product-form')
        ->set('sku', 'IMG-1')
        ->set('name', 'Imaged Labels')
        ->set('quantity_per_package', 100)
        ->set('price', '10.00');
}

test('an image can be uploaded when creating an item', function () {
    newProductForm($this->admin)
        ->set('photo', UploadedFile::fake()->image('labels.jpg', 600, 600))
        ->call('save')
        ->assertHasNoErrors();

    $product = Product::where('sku', 'IMG-1')->sole();

    expect($product->image_path)->toStartWith('products/')
        ->and($product->imageUrl())->not->toBeNull();
    Storage::disk('public')->assertExists($product->image_path);
});

test('an item can be saved without an image', function () {
    newProductForm($this->admin)->call('save')->assertHasNoErrors();

    expect(Product::sole()->imageUrl())->toBeNull();
});

test('only images up to four megabytes are accepted', function () {
    newProductForm($this->admin)
        ->set('photo', UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'))
        ->assertHasErrors(['photo'])
        ->assertSet('photo', null)
        ->call('save')
        ->assertHasNoErrors();

    newProductForm($this->admin)
        ->set('photo', UploadedFile::fake()->image('huge.png')->size(5000))
        ->assertHasErrors(['photo'])
        ->assertSet('photo', null);

    expect(Product::sole()->image_path)->toBeNull();
});

test('uploading a new image replaces and deletes the old file', function () {
    $old = UploadedFile::fake()->image('old.jpg')->store('products', 'public');
    $product = Product::factory()->create(['image_path' => $old]);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.product-form', ['product' => $product])
        ->set('photo', UploadedFile::fake()->image('new.png'))
        ->call('save')
        ->assertHasNoErrors();

    $product->refresh();

    expect($product->image_path)->not->toBe($old);
    Storage::disk('public')->assertMissing($old);
    Storage::disk('public')->assertExists($product->image_path);
});

test('editing without choosing a file keeps the current image', function () {
    $path = UploadedFile::fake()->image('keep.jpg')->store('products', 'public');
    $product = Product::factory()->create(['image_path' => $path]);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.product-form', ['product' => $product])
        ->set('name', 'Renamed')
        ->call('save')
        ->assertHasNoErrors();

    expect($product->fresh()->image_path)->toBe($path);
    Storage::disk('public')->assertExists($path);
});

test('the current image can be removed', function () {
    $path = UploadedFile::fake()->image('gone.jpg')->store('products', 'public');
    $product = Product::factory()->create(['image_path' => $path]);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.product-form', ['product' => $product])
        ->set('removeImage', true)
        ->call('save')
        ->assertHasNoErrors();

    expect($product->fresh()->image_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('deleting an item deletes its image file', function () {
    $path = UploadedFile::fake()->image('bye.jpg')->store('products', 'public');
    $product = Product::factory()->create(['image_path' => $path]);

    Livewire::actingAs($this->admin)->test('pages::admin.products')->call('delete', $product->id);

    Storage::disk('public')->assertMissing($path);
});

test('the storefront shows the product image when there is one', function () {
    $path = UploadedFile::fake()->image('shown.jpg')->store('products', 'public');
    $product = Product::factory()->create(['image_path' => $path]);
    $url = Storage::disk('public')->url($path);

    $this->get(route('home'))->assertSee($url, false);
    $this->get(route('products.show', $product))->assertSee($url, false);
});
