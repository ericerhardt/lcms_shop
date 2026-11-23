<?php

namespace Database\Seeders;

use App\Enums\PackagingType;
use App\Models\CustomField;
use App\Models\Product;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    /**
     * Starting inventory, taken from catalog_product.xlsx.
     *
     * Each item is sold by the box. Prices are per box, in cents. The spreadsheet has
     * no SKUs or stock counts, so SKUs are generated (size / direct thermal / format /
     * coating) and every item starts with zero stock until real counts are entered.
     *
     * Products are only created when their SKU is missing, so re-running this seeder
     * never overwrites prices or stock that were edited in the admin.
     *
     * @var array<int, array{sku: string, size: string, format: string, coating: string, labels: int, price_cents: int}>
     */
    private const PRODUCTS = [
        ['sku' => 'LBL-4X6-DT-FF-NT', 'size' => '4x6', 'format' => 'Fan Fold', 'coating' => 'Non-Top Coated', 'labels' => 4000, 'price_cents' => 15200],
        ['sku' => 'LBL-4X6-DT-3C-TC', 'size' => '4x6', 'format' => '3" Core', 'coating' => 'Top Coated', 'labels' => 4000, 'price_cents' => 15200],
        ['sku' => 'LBL-4X6-DT-1C-TC', 'size' => '4x6', 'format' => '1" Core', 'coating' => 'Top Coated', 'labels' => 3000, 'price_cents' => 11800],
        ['sku' => 'LBL-4X3-DT-3C-NT', 'size' => '4x3', 'format' => '3" Core', 'coating' => 'Non-Top Coated', 'labels' => 7600, 'price_cents' => 12500],
        ['sku' => 'LBL-4X3-DT-1C-TC', 'size' => '4x3', 'format' => '1" Core', 'coating' => 'Top Coated', 'labels' => 6000, 'price_cents' => 9200],
        ['sku' => 'LBL-3X2-DT-3C-TC', 'size' => '3x2', 'format' => '3" Core', 'coating' => 'Top Coated', 'labels' => 16500, 'price_cents' => 19500],
        ['sku' => 'LBL-3X2-DT-1C-TC', 'size' => '3x2', 'format' => '1" Core', 'coating' => 'Top Coated', 'labels' => 8880, 'price_cents' => 15000],
    ];

    public function run(): void
    {
        $size = CustomField::firstOrCreate(['key' => 'label_size'], ['name' => 'Label size', 'type' => 'text', 'position' => 1]);
        $format = CustomField::firstOrCreate(['key' => 'core_format'], ['name' => 'Core / format', 'type' => 'text', 'position' => 2]);
        $coating = CustomField::firstOrCreate(['key' => 'coating'], ['name' => 'Coating', 'type' => 'select', 'options' => ['Top Coated', 'Non-Top Coated'], 'position' => 3]);

        foreach (self::PRODUCTS as $item) {
            $product = Product::firstOrCreate(['sku' => $item['sku']], [
                'name' => "{$item['size']} Direct Thermal Labels, {$item['format']}",
                'description' => "{$item['size']} direct thermal labels on a {$item['format']} format, {$item['coating']}. "
                    .number_format($item['labels']).' labels per box.',
                'packaging_type' => PackagingType::Box,
                'quantity_per_package' => $item['labels'],
                'price_cents' => $item['price_cents'],
                'stock_quantity' => 0,
                'is_active' => true,
            ]);

            if ($product->wasRecentlyCreated) {
                $product->customFields()->sync([
                    $size->id => ['value' => $item['size']],
                    $format->id => ['value' => $item['format']],
                    $coating->id => ['value' => $item['coating']],
                ]);
            }
        }
    }
}
