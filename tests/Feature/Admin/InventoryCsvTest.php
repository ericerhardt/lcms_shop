<?php

use App\Models\CustomField;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryCsv;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

function csvFile(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'inv');
    file_put_contents($path, $contents);

    return $path;
}

test('the export contains every item with base and custom field columns', function () {
    $field = CustomField::factory()->create(['key' => 'label_size']);
    $product = Product::factory()->create([
        'sku' => 'LBL-1', 'name' => 'Four by six', 'packaging_type' => 'box',
        'quantity_per_package' => 500, 'price_cents' => 1295, 'stock_quantity' => 7,
    ]);
    $product->customFields()->attach($field, ['value' => '4 x 6 in']);

    $csv = $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.inventory.export'))
        ->assertOk()
        ->assertDownload()
        ->streamedContent();

    $rows = array_map('str_getcsv', array_filter(explode("\n", $csv)));

    expect($rows[0])->toBe(['sku', 'name', 'description', 'packaging_type', 'quantity_per_package', 'price', 'stock_quantity', 'is_active', 'custom:label_size'])
        ->and($rows[1])->toBe(['LBL-1', 'Four by six', $product->description, 'box', '500', '12.95', '7', '1', '4 x 6 in']);
});

test('importing creates new items and updates existing ones by sku', function () {
    $existing = Product::factory()->create(['sku' => 'LBL-1', 'name' => 'Old name', 'price_cents' => 100]);
    $field = CustomField::factory()->create(['key' => 'material']);

    $result = app(InventoryCsv::class)->import(csvFile(
        "sku,name,description,packaging_type,quantity_per_package,price,stock_quantity,is_active,custom:material\n"
        ."LBL-1,New name,,box,500,12.50,10,1,Thermal\n"
        ."LBL-2,Second,Nice,case,1000,99.99,3,0,\n"
    ));

    expect($result)->toMatchArray(['created' => 1, 'updated' => 1, 'errors' => []])
        ->and($existing->fresh())->name->toBe('New name')->price_cents->toBe(1250)->stock_quantity->toBe(10)
        ->and($existing->fresh()->customFieldValues())->toBe([$field->id => 'Thermal']);

    $created = Product::where('sku', 'LBL-2')->sole();
    expect($created->is_active)->toBeFalse()
        ->and($created->price_cents)->toBe(9999)
        ->and($created->packaging_type->value)->toBe('case');
});

test('importing skips invalid rows and reports them while keeping valid ones', function () {
    $result = app(InventoryCsv::class)->import(csvFile(
        "sku,name,packaging_type,quantity_per_package,price\n"
        ."GOOD-1,Good,box,10,1.00\n"
        ."BAD-1,Bad,crate,10,1.00\n"
        ."BAD-2,,box,0,-5\n"
    ));

    expect($result['created'])->toBe(1)
        ->and($result['errors'])->toHaveCount(2)
        ->and($result['errors'][0])->toStartWith('Row 3:')
        ->and($result['errors'][1])->toStartWith('Row 4:')
        ->and(Product::pluck('sku')->all())->toBe(['GOOD-1']);
});

test('importing ignores custom columns that have no matching field', function () {
    $result = app(InventoryCsv::class)->import(csvFile(
        "sku,name,packaging_type,quantity_per_package,price,custom:unknown\n"
        ."A-1,Item,box,10,1.00,whatever\n"
    ));

    expect($result['created'])->toBe(1)
        ->and($result['ignored_columns'])->toBe(['custom:unknown'])
        ->and(Product::sole()->customFields)->toHaveCount(0);
});

test('importing a file without a sku column is rejected', function () {
    $result = app(InventoryCsv::class)->import(csvFile("name,price\nThing,1.00\n"));

    expect($result['errors'])->toBe(['The file needs a "sku" column.'])
        ->and(Product::count())->toBe(0);
});

test('an exported file can be imported back without changes', function () {
    $field = CustomField::factory()->create(['key' => 'color']);
    $product = Product::factory()->create(['price_cents' => 1999]);
    $product->customFields()->attach($field, ['value' => 'Blue, with "quotes"']);

    $csv = $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.inventory.export'))
        ->streamedContent();

    $result = app(InventoryCsv::class)->import(csvFile($csv));

    expect($result)->toMatchArray(['created' => 0, 'updated' => 1, 'errors' => []])
        ->and($product->fresh()->price_cents)->toBe(1999)
        ->and($product->fresh()->customFieldValues())->toBe([$field->id => 'Blue, with "quotes"']);
});

test('an admin can upload a csv from the inventory page', function () {
    $file = UploadedFile::fake()->createWithContent(
        'inventory.csv',
        "sku,name,packaging_type,quantity_per_package,price\nUP-1,Uploaded,each,1,2.00\n",
    );

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::admin.products')
        ->set('importFile', $file)
        ->call('import')
        ->assertHasNoErrors()
        ->assertSee('Import finished: 1 created, 0 updated.');

    expect(Product::where('sku', 'UP-1')->exists())->toBeTrue();
});

test('the import rejects files that are not csv', function () {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::admin.products')
        ->set('importFile', UploadedFile::fake()->create('inventory.pdf', 10, 'application/pdf'))
        ->call('import')
        ->assertHasErrors(['importFile']);
});
