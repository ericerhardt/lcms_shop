<?php

use App\Models\Product;
use App\Services\Cart;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::shop')] class extends Component {
    public Product $product;

    public int $quantity = 1;

    public function mount(Product $product): void
    {
        abort_unless($product->is_active, 404);

        $this->product = $product->load('customFields');
    }

    public function render()
    {
        return $this->view()->title($this->product->name);
    }

    public function addToCart(Cart $cart): void
    {
        $this->validate(['quantity' => ['required', 'integer', 'min:1', 'max:'.max(1, $this->product->stock_quantity)]]);

        if ($this->product->stock_quantity < 1) {
            return;
        }

        $cart->add($this->product, $this->quantity);

        $this->dispatch('cart-updated');
        Flux::toast(text: __(':name added to your cart.', ['name' => $this->product->name]), variant: 'success');
    }
}; ?>

<div class="mx-auto max-w-7xl px-6 py-10 lg:px-8">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('home')" wire:navigate>{{ __('Catalog') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $product->sku }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="mt-8 grid gap-10 lg:grid-cols-2">
        <div class="flex aspect-square items-center justify-center rounded-2xl bg-gradient-to-br from-zinc-100 to-zinc-200 dark:from-zinc-700 dark:to-zinc-800">
            <flux:icon.tag class="size-24 text-zinc-400 dark:text-zinc-500" />
        </div>

        <div class="flex flex-col gap-5">
            <div>
                <flux:text class="font-mono">{{ $product->sku }}</flux:text>
                <flux:heading size="xl" level="1" class="mt-1">{{ $product->name }}</flux:heading>
            </div>

            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-bold">{{ $product->formattedPrice() }}</span>
                <flux:text>{{ __('per :type', ['type' => strtolower($product->packaging_type->label())]) }}</flux:text>
            </div>

            @if (filled($product->description))
                <flux:text class="whitespace-pre-line">{{ $product->description }}</flux:text>
            @endif

            <dl class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 text-sm dark:divide-zinc-700 dark:border-zinc-700">
                <div class="flex justify-between px-4 py-2.5">
                    <dt class="text-zinc-500">{{ __('Packaging') }}</dt>
                    <dd class="font-medium">{{ $product->packaging_type->label() }}</dd>
                </div>
                <div class="flex justify-between px-4 py-2.5">
                    <dt class="text-zinc-500">{{ __('Quantity per package') }}</dt>
                    <dd class="font-medium">{{ number_format($product->quantity_per_package) }}</dd>
                </div>
                @foreach ($product->customFields->filter(fn ($field) => filled($field->pivot->value)) as $field)
                    <div class="flex justify-between px-4 py-2.5">
                        <dt class="text-zinc-500">{{ $field->name }}</dt>
                        <dd class="font-medium">{{ $field->pivot->value }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($product->stock_quantity > 0)
                <form wire:submit="addToCart" class="flex items-start gap-3">
                    <flux:input type="number" wire:model="quantity" min="1" :max="$product->stock_quantity" class="w-28" :aria-label="__('Quantity')" />
                    <flux:button type="submit" variant="primary" icon="shopping-cart" data-test="add-to-cart">{{ __('Add to cart') }}</flux:button>
                </form>
                <flux:text size="sm">
                    {{ $product->stock_quantity <= 10 ? __('Only :count left in stock.', ['count' => $product->stock_quantity]) : __('In stock.') }}
                </flux:text>
            @else
                <flux:badge color="red" class="self-start">{{ __('Out of stock') }}</flux:badge>
            @endif
        </div>
    </div>
</div>
