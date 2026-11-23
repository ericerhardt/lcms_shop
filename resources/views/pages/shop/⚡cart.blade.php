<?php

use App\Services\Cart;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Your cart')] #[Layout('layouts::shop')] class extends Component {
    /**
     * @return \Illuminate\Support\Collection<int, array{product: \App\Models\Product, quantity: int, total_cents: int}>
     */
    #[Computed]
    public function lines(): \Illuminate\Support\Collection
    {
        return app(Cart::class)->lines();
    }

    #[Computed]
    public function subtotal(): string
    {
        return app(Cart::class)->formattedSubtotal();
    }

    public function updateQuantity(int $productId, int|string $quantity, Cart $cart): void
    {
        $cart->set($productId, max(0, (int) $quantity));

        $this->refreshCart();
    }

    public function remove(int $productId, Cart $cart): void
    {
        $cart->remove($productId);

        $this->refreshCart();
    }

    private function refreshCart(): void
    {
        unset($this->lines, $this->subtotal);

        $this->dispatch('cart-updated');
    }
}; ?>

<div class="mx-auto max-w-5xl px-6 py-10 lg:px-8">
    <flux:heading size="xl" level="1">{{ __('Your cart') }}</flux:heading>

    @if (session('status'))
        <flux:callout variant="warning" icon="information-circle" class="mt-6" :heading="session('status')" />
    @endif

    @if ($this->lines->isEmpty())
        <div class="mt-8 rounded-xl border border-dashed border-zinc-300 p-12 text-center dark:border-zinc-600">
            <flux:icon.shopping-cart class="mx-auto size-8 text-zinc-400" />
            <flux:heading class="mt-3">{{ __('Your cart is empty') }}</flux:heading>
            <flux:button :href="route('home')" variant="primary" class="mt-4" wire:navigate>{{ __('Browse the catalog') }}</flux:button>
        </div>
    @else
        <div class="mt-8 grid gap-8 lg:grid-cols-3">
            <div class="divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700 lg:col-span-2">
                @foreach ($this->lines as $line)
                    @php($product = $line['product'])
                    <div wire:key="line-{{ $product->id }}" class="flex flex-wrap items-center gap-4 p-4">
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('products.show', $product) }}" wire:navigate class="font-semibold hover:underline">{{ $product->name }}</a>
                            <flux:text size="sm" class="font-mono">{{ $product->sku }}</flux:text>
                            <flux:text size="sm">{{ $product->packageLabel() }} &middot; {{ $product->formattedPrice() }} {{ __('each') }}</flux:text>
                        </div>

                        <flux:input type="number" min="1" :max="$product->stock_quantity" :value="$line['quantity']"
                            wire:change="updateQuantity({{ $product->id }}, $event.target.value)" class="!w-24" :aria-label="__('Quantity')" />

                        <div class="w-24 text-end font-semibold">{{ Number::currency($line['total_cents'] / 100, 'USD') }}</div>

                        <flux:button wire:click="remove({{ $product->id }})" variant="ghost" size="sm" icon="trash" :aria-label="__('Remove')" />
                    </div>
                @endforeach
            </div>

            <aside class="h-fit rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:heading>{{ __('Order summary') }}</flux:heading>
                <div class="mt-4 flex justify-between text-sm">
                    <span class="text-zinc-500">{{ __('Subtotal') }}</span>
                    <span class="font-semibold" data-test="cart-subtotal">{{ $this->subtotal }}</span>
                </div>
                <flux:text size="sm" class="mt-1">{{ __('Shipping and taxes are not charged online.') }}</flux:text>
                <flux:button :href="route('checkout')" variant="primary" class="mt-6 w-full" wire:navigate>{{ __('Proceed to checkout') }}</flux:button>
                <flux:button :href="route('home')" variant="ghost" class="mt-2 w-full" wire:navigate>{{ __('Continue shopping') }}</flux:button>
            </aside>
        </div>
    @endif
</div>
