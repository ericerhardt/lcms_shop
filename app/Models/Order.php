<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $token
 * @property int|null $user_id
 * @property OrderStatus $status
 * @property string $email
 * @property string $name
 * @property int $subtotal_cents
 * @property string|null $stripe_session_id
 * @property string|null $stripe_payment_intent_id
 */
#[Fillable([
    'user_id', 'status', 'email', 'name', 'company', 'phone',
    'address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country',
    'subtotal_cents', 'stripe_session_id', 'stripe_payment_intent_id', 'paid_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            $order->token ??= (string) Str::uuid();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Mark the order as paid and deduct stock. Safe to call more than once.
     */
    public function markPaid(?string $paymentIntentId = null): void
    {
        DB::transaction(function () use ($paymentIntentId): void {
            $order = static::query()->lockForUpdate()->findOrFail($this->id);

            if ($order->status === OrderStatus::Paid) {
                return;
            }

            $order->update([
                'status' => OrderStatus::Paid,
                'paid_at' => now(),
                'stripe_payment_intent_id' => $paymentIntentId,
            ]);

            foreach ($order->items()->with('product')->get() as $item) {
                $item->product?->update([
                    'stock_quantity' => max(0, $item->product->stock_quantity - $item->quantity),
                ]);
            }
        });

        $this->refresh();
    }

    public function formattedTotal(): string
    {
        return Number::currency($this->subtotal_cents / 100, 'USD');
    }

    public function number(): string
    {
        return 'ORD-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }
}
