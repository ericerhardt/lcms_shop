<?php

use App\Models\CustomField;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

test('the catalog seeder creates the starting inventory sold by the box', function () {
    $this->seed(CatalogSeeder::class);

    $product = Product::where('sku', 'LBL-4X6-DT-FF-NT')->sole();

    expect(Product::count())->toBe(7)
        ->and($product->name)->toBe('4x6 Direct Thermal Labels, Fan Fold')
        ->and($product->packaging_type->value)->toBe('box')
        ->and($product->quantity_per_package)->toBe(4000)
        ->and($product->price_cents)->toBe(15200)
        ->and($product->stock_quantity)->toBe(0)
        ->and($product->is_active)->toBeTrue()
        ->and(CustomField::orderBy('position')->pluck('key')->all())->toBe(['label_size', 'core_format', 'coating']);

    $values = $product->load('customFields')->customFields->pluck('pivot.value', 'key')->all();
    expect($values)->toBe(['label_size' => '4x6', 'core_format' => 'Fan Fold', 'coating' => 'Non-Top Coated']);
});

test('the catalog seeder matches the prices and box quantities in the spreadsheet', function () {
    $this->seed(CatalogSeeder::class);

    $rows = Product::orderBy('id')->get()->map(fn (Product $product) => [$product->quantity_per_package, $product->price_cents / 100])->all();

    expect($rows)->toBe([[4000, 152], [4000, 152], [3000, 118], [7600, 125], [6000, 92], [16500, 195], [8880, 150]]);
});

test('re-running the catalog seeder keeps prices and stock edited in the admin', function () {
    $this->seed(CatalogSeeder::class);

    Product::where('sku', 'LBL-4X6-DT-FF-NT')->update(['price_cents' => 9999, 'stock_quantity' => 42, 'is_active' => false]);

    $this->seed(CatalogSeeder::class);

    expect(Product::count())->toBe(7)
        ->and(CustomField::count())->toBe(3)
        ->and(Product::where('sku', 'LBL-4X6-DT-FF-NT')->sole())
        ->price_cents->toBe(9999)
        ->stock_quantity->toBe(42)
        ->is_active->toBeFalse();
});

test('locally the admin seeder falls back to a default admin account', function () {
    $this->seed(AdminSeeder::class);

    $admin = User::where('email', 'admin@example.com')->sole();

    expect($admin->is_admin)->toBeTrue()
        ->and(Hash::check('password', $admin->password))->toBeTrue();
});

test('in production the admin seeder uses the configured credentials', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['shop.admin.email' => 'owner@lcm.test', 'shop.admin.password' => 'a-strong-secret', 'shop.admin.name' => 'Owner']);

    app(AdminSeeder::class)->run();

    $admin = User::sole();

    expect($admin->email)->toBe('owner@lcm.test')
        ->and($admin->name)->toBe('Owner')
        ->and($admin->is_admin)->toBeTrue()
        ->and(Hash::check('a-strong-secret', $admin->password))->toBeTrue();
});

test('the admin seeder stores the email lowercased so it matches the lowercased login', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['shop.admin.email' => ' Owner@LCM.test ', 'shop.admin.password' => 'a-strong-secret']);

    app(AdminSeeder::class)->run();

    expect(User::sole()->email)->toBe('owner@lcm.test');
});

test('in production the admin seeder creates no account without configured credentials', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['shop.admin.email' => null, 'shop.admin.password' => null]);

    app(AdminSeeder::class)->run();

    expect(User::count())->toBe(0);
});

test('the database seeder skips the test user in production and still seeds the catalog', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['shop.admin.email' => null, 'shop.admin.password' => null]);

    app(DatabaseSeeder::class)->run();

    expect(User::count())->toBe(0)
        ->and(Product::count())->toBe(7);
});

test('the database seeder can be run twice without errors', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(Product::count())->toBe(7)
        ->and(User::count())->toBe(2);
});
