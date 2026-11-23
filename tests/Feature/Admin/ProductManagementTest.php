<?php

use App\Models\CustomField;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

test('an admin creates an item with custom field values', function () {
    $size = CustomField::factory()->create(['name' => 'Label size']);
    $material = CustomField::factory()->select(['Direct thermal', 'Thermal transfer'])->create(['name' => 'Material']);

    Livewire::test('pages::admin.product-form')
        ->set('sku', 'LBL-100')
        ->set('name', '4 x 6 labels')
        ->set('description', 'Carrier labels')
        ->set('packaging_type', 'case')
        ->set('quantity_per_package', 6000)
        ->set('price', '139.50')
        ->set('stock_quantity', 12)
        ->set("customValues.{$size->id}", '4 x 6 in')
        ->set("customValues.{$material->id}", 'Direct thermal')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.products'));

    $product = Product::with('customFields')->sole();

    expect($product->sku)->toBe('LBL-100')
        ->and($product->packaging_type->value)->toBe('case')
        ->and($product->quantity_per_package)->toBe(6000)
        ->and($product->price_cents)->toBe(13950)
        ->and($product->stock_quantity)->toBe(12)
        ->and($product->customFieldValues())->toBe([$size->id => '4 x 6 in', $material->id => 'Direct thermal']);
});

test('an admin edits an item and clearing a custom field removes its value', function () {
    $field = CustomField::factory()->create();
    $product = Product::factory()->create(['sku' => 'OLD-1', 'price_cents' => 1000]);
    $product->customFields()->attach($field, ['value' => 'Something']);

    Livewire::test('pages::admin.product-form', ['product' => $product])
        ->assertSet('sku', 'OLD-1')
        ->assertSet('price', '10.00')
        ->assertSet("customValues.{$field->id}", 'Something')
        ->set('name', 'Renamed')
        ->set('price', '12.34')
        ->set("customValues.{$field->id}", '')
        ->call('save')
        ->assertHasNoErrors();

    expect($product->fresh())->name->toBe('Renamed')->price_cents->toBe(1234)
        ->and($product->fresh()->customFields)->toHaveCount(0);
});

test('an item requires its core fields', function () {
    Livewire::test('pages::admin.product-form')
        ->set('quantity_per_package', 0)
        ->call('save')
        ->assertHasErrors(['sku', 'name', 'price', 'quantity_per_package']);

    expect(Product::count())->toBe(0);
});

test('an item sku must be unique but may be kept when editing', function () {
    Product::factory()->create(['sku' => 'TAKEN']);
    $product = Product::factory()->create(['sku' => 'MINE', 'price_cents' => 500]);

    Livewire::test('pages::admin.product-form', ['product' => $product])
        ->set('sku', 'TAKEN')
        ->call('save')
        ->assertHasErrors(['sku' => 'unique']);

    Livewire::test('pages::admin.product-form', ['product' => $product])
        ->call('save')
        ->assertHasNoErrors();
});

test('custom field values are validated against the field type', function () {
    $number = CustomField::factory()->create(['type' => 'number']);
    $select = CustomField::factory()->select(['Matte', 'Gloss'])->create();

    Livewire::test('pages::admin.product-form')
        ->set('sku', 'X-1')
        ->set('name', 'X')
        ->set('price', '1')
        ->set("customValues.{$number->id}", 'abc')
        ->set("customValues.{$select->id}", 'Satin')
        ->call('save')
        ->assertHasErrors(["customValues.{$number->id}", "customValues.{$select->id}"]);
});

test('an admin can hide and delete items from the inventory list', function () {
    $product = Product::factory()->create();

    Livewire::test('pages::admin.products')
        ->call('toggleActive', $product->id);

    expect($product->fresh()->is_active)->toBeFalse();

    Livewire::test('pages::admin.products')
        ->call('delete', $product->id);

    expect(Product::count())->toBe(0);
});

test('the inventory list can be searched', function () {
    $match = Product::factory()->create(['sku' => 'FIND-ME']);
    $other = Product::factory()->create(['sku' => 'NOT-THIS']);

    Livewire::test('pages::admin.products')
        ->set('search', 'find')
        ->assertSee($match->sku)
        ->assertDontSee($other->sku);
});

test('an admin creates a text custom field with a generated key', function () {
    Livewire::test('pages::admin.custom-fields')
        ->set('name', 'Label size')
        ->set('type', 'text')
        ->call('save')
        ->assertHasNoErrors();

    expect(CustomField::sole())->name->toBe('Label size')->key->toBe('label_size');
});

test('fields with the same name get distinct keys', function () {
    CustomField::factory()->create(['name' => 'Color', 'key' => 'color']);

    Livewire::test('pages::admin.custom-fields')
        ->set('name', 'Color')
        ->call('save')
        ->assertHasNoErrors();

    expect(CustomField::pluck('key')->all())->toEqualCanonicalizing(['color', 'color_2']);
});

test('a dropdown field stores its options one per line', function () {
    Livewire::test('pages::admin.custom-fields')
        ->set('name', 'Material')
        ->set('type', 'select')
        ->set('options', "Direct thermal\n\nThermal transfer\nDirect thermal")
        ->call('save')
        ->assertHasNoErrors();

    expect(CustomField::sole()->options)->toBe(['Direct thermal', 'Thermal transfer']);
});

test('a dropdown field requires options', function () {
    Livewire::test('pages::admin.custom-fields')
        ->set('name', 'Material')
        ->set('type', 'select')
        ->call('save')
        ->assertHasErrors(['options']);
});

test('deleting a custom field removes its values from items', function () {
    $field = CustomField::factory()->create();
    $product = Product::factory()->create();
    $product->customFields()->attach($field, ['value' => 'x']);

    Livewire::test('pages::admin.custom-fields')->call('delete', $field->id);

    expect(CustomField::count())->toBe(0)
        ->and($product->fresh()->customFields)->toHaveCount(0);
});
