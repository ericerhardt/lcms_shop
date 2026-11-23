<?php

namespace App\Services;

use App\Enums\PackagingType;
use App\Models\CustomField;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports and imports the product catalog as CSV. Custom fields travel as `custom:<key>` columns.
 */
class InventoryCsv
{
    private const BASE_COLUMNS = [
        'sku', 'name', 'description', 'packaging_type', 'quantity_per_package', 'price', 'stock_quantity', 'is_active',
    ];

    private const CUSTOM_PREFIX = 'custom:';

    public function export(): StreamedResponse
    {
        $fields = CustomField::query()->orderBy('position')->orderBy('id')->get();

        return response()->streamDownload(function () use ($fields): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                ...self::BASE_COLUMNS,
                ...$fields->map(fn (CustomField $field): string => self::CUSTOM_PREFIX.$field->key)->all(),
            ]);

            Product::query()->with('customFields')->orderBy('sku')->chunk(200, function ($products) use ($out, $fields): void {
                foreach ($products as $product) {
                    $values = $product->customFieldValues();

                    fputcsv($out, [
                        $product->sku,
                        $product->name,
                        $product->description,
                        $product->packaging_type->value,
                        $product->quantity_per_package,
                        number_format($product->price_cents / 100, 2, '.', ''),
                        $product->stock_quantity,
                        $product->is_active ? '1' : '0',
                        ...$fields->map(fn (CustomField $field): string => (string) ($values[$field->id] ?? ''))->all(),
                    ]);
                }
            });

            fclose($out);
        }, 'inventory-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Create or update products by SKU. Invalid rows are skipped and reported.
     *
     * @return array{created: int, updated: int, errors: array<int, string>, ignored_columns: array<int, string>}
     */
    public function import(string $path): array
    {
        $result = ['created' => 0, 'updated' => 0, 'errors' => [], 'ignored_columns' => []];

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, escape: '\\');

        if (! $header) {
            fclose($handle);

            return [...$result, 'errors' => ['The file is empty.']];
        }

        $header = array_map(fn ($column): string => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $column))), $header);

        if (! in_array('sku', $header, true)) {
            fclose($handle);

            return [...$result, 'errors' => ['The file needs a "sku" column.']];
        }

        $fieldsByColumn = CustomField::query()->get()->keyBy(fn (CustomField $field): string => self::CUSTOM_PREFIX.strtolower($field->key));

        foreach ($header as $column) {
            if (str_starts_with($column, self::CUSTOM_PREFIX) && ! $fieldsByColumn->has($column)) {
                $result['ignored_columns'][] = $column;
            }
        }

        $rowNumber = 1;

        DB::transaction(function () use ($handle, $header, $fieldsByColumn, &$result, &$rowNumber): void {
            while (($cells = fgetcsv($handle, escape: '\\')) !== false) {
                $rowNumber++;

                if ($cells === [null]) {
                    continue;
                }

                $row = array_combine($header, array_pad(array_slice($cells, 0, count($header)), count($header), ''));

                $validator = Validator::make($row, [
                    'sku' => ['required', 'string', 'max:100'],
                    'name' => ['required', 'string', 'max:255'],
                    'description' => ['nullable', 'string'],
                    'packaging_type' => ['required', Rule::enum(PackagingType::class)],
                    'quantity_per_package' => ['required', 'integer', 'min:1'],
                    'price' => ['required', 'numeric', 'min:0'],
                    'stock_quantity' => ['nullable', 'integer', 'min:0'],
                ]);

                if ($validator->fails()) {
                    $result['errors'][] = "Row {$rowNumber}: ".$validator->errors()->first();

                    continue;
                }

                $product = Product::query()->firstOrNew(['sku' => trim($row['sku'])]);
                $isNew = ! $product->exists;

                $product->fill([
                    'name' => $row['name'],
                    'description' => ($row['description'] ?? '') ?: null,
                    'packaging_type' => strtolower($row['packaging_type']),
                    'quantity_per_package' => (int) $row['quantity_per_package'],
                    'price_cents' => (int) round(((float) $row['price']) * 100),
                    'stock_quantity' => (int) ($row['stock_quantity'] ?? 0),
                    'is_active' => ! in_array(strtolower(trim($row['is_active'] ?? '1')), ['0', 'false', 'no', ''], true),
                ])->save();

                $values = [];
                foreach ($fieldsByColumn as $column => $field) {
                    if (array_key_exists($column, $row)) {
                        $values[$field->id] = ['value' => $row[$column] === '' ? null : $row[$column]];
                    }
                }
                $product->customFields()->syncWithoutDetaching($values);

                $isNew ? $result['created']++ : $result['updated']++;
            }
        });

        fclose($handle);

        return $result;
    }
}
