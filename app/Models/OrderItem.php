<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $product_id
 * @property string $sku
 * @property string $name
 * @property string $packaging_type
 * @property int $quantity_per_package
 * @property int $unit_price_cents
 * @property int $quantity
 */
#[Fillable(['product_id', 'sku', 'name', 'packaging_type', 'quantity_per_package', 'unit_price_cents', 'quantity'])]
class OrderItem extends Model
{
    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lineTotalCents(): int
    {
        return $this->unit_price_cents * $this->quantity;
    }

    public function formattedLineTotal(): string
    {
        return Number::currency($this->lineTotalCents() / 100, 'USD');
    }
}
