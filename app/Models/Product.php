<?php

namespace App\Models;

use App\Enums\PackagingType;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

/**
 * @property int $id
 * @property string $sku
 * @property string $name
 * @property string|null $description
 * @property PackagingType $packaging_type
 * @property int $quantity_per_package
 * @property int $price_cents
 * @property int $stock_quantity
 * @property string|null $image_path
 * @property bool $is_active
 */
#[Fillable(['sku', 'name', 'description', 'image_path', 'packaging_type', 'quantity_per_package', 'price_cents', 'stock_quantity', 'is_active'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (Product $product): void {
            $product->deleteImage();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'packaging_type' => PackagingType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<CustomField, $this>
     */
    public function customFields(): BelongsToMany
    {
        return $this->belongsToMany(CustomField::class)
            ->withPivot('value')
            ->orderBy('custom_fields.position')
            ->orderBy('custom_fields.id');
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function deleteImage(): void
    {
        if ($this->image_path) {
            Storage::disk('public')->delete($this->image_path);
        }
    }

    public function formattedPrice(): string
    {
        return Number::currency($this->price_cents / 100, 'USD');
    }

    public function packageLabel(): string
    {
        return $this->packaging_type->label().' of '.number_format($this->quantity_per_package);
    }

    /**
     * Custom field values keyed by custom field id.
     *
     * @return array<int, string|null>
     */
    public function customFieldValues(): array
    {
        return $this->customFields->mapWithKeys(
            fn (CustomField $field): array => [$field->id => $field->pivot->value]
        )->all();
    }
}
