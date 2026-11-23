<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;

/**
 * Session-backed shopping cart. Works for guests and signed-in customers alike.
 * Quantities are counted in packages (e.g. 3 boxes of 500 labels).
 */
class Cart
{
    private const SESSION_KEY = 'cart';

    public function __construct(private Session $session) {}

    public function add(Product $product, int $quantity = 1): void
    {
        $items = $this->items();
        $items[$product->id] = ($items[$product->id] ?? 0) + $quantity;

        $this->store($items);
    }

    public function set(int $productId, int $quantity): void
    {
        $items = $this->items();

        if ($quantity < 1) {
            unset($items[$productId]);
        } else {
            $items[$productId] = $quantity;
        }

        $this->store($items);
    }

    public function remove(int $productId): void
    {
        $this->set($productId, 0);
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    /**
     * Cart lines for products that are still purchasable, with quantities capped at available stock.
     *
     * @return Collection<int, array{product: Product, quantity: int, total_cents: int}>
     */
    public function lines(): Collection
    {
        $items = $this->items();

        if ($items === []) {
            return collect();
        }

        return Product::query()
            ->active()
            ->where('stock_quantity', '>', 0)
            ->whereKey(array_keys($items))
            ->get()
            ->map(function (Product $product) use ($items): array {
                $quantity = min($items[$product->id], $product->stock_quantity);

                return [
                    'product' => $product,
                    'quantity' => $quantity,
                    'total_cents' => $product->price_cents * $quantity,
                ];
            })
            ->values();
    }

    public function count(): int
    {
        return $this->lines()->sum('quantity');
    }

    public function isEmpty(): bool
    {
        return $this->lines()->isEmpty();
    }

    public function subtotalCents(): int
    {
        return $this->lines()->sum('total_cents');
    }

    public function formattedSubtotal(): string
    {
        return Number::currency($this->subtotalCents() / 100, 'USD');
    }

    /**
     * @return array<int, int>
     */
    private function items(): array
    {
        return $this->session->get(self::SESSION_KEY, []);
    }

    /**
     * @param  array<int, int>  $items
     */
    private function store(array $items): void
    {
        $this->session->put(self::SESSION_KEY, $items);
    }
}
