<?php

namespace App\Models;

use Database\Factories\ShippingMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

/**
 * A way to ship an order: a carrier, how long it takes and a flat price.
 *
 * @property int $id
 * @property string $carrier
 * @property string $timeframe
 * @property int $price_cents
 * @property bool $is_active
 * @property int $position
 * @property Carbon|null $created_at
 */
#[Fillable(['carrier', 'timeframe', 'price_cents', 'is_active', 'position'])]
class ShippingMethod extends Model
{
    /** @use HasFactory<ShippingMethodFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }

    public function formattedPrice(): string
    {
        return $this->price_cents === 0 ? __('Free') : Number::currency($this->price_cents / 100, 'USD');
    }

    public function label(): string
    {
        return "{$this->carrier} - {$this->timeframe}";
    }
}
