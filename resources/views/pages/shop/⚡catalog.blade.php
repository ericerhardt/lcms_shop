<?php

use App\Enums\PackagingType;
use App\Models\Product;
use App\Services\Cart;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Bulk labels for package printing')] #[Layout('layouts::shop')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $packaging = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPackaging(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    #[Computed]
    public function products(): LengthAwarePaginator
    {
        $term = '%'.Str::lower(trim($this->search)).'%';

        return Product::query()
            ->active()
            ->with('customFields')
            ->when($this->packaging !== '', fn ($query) => $query->where('packaging_type', $this->packaging))
            ->when(trim($this->search) !== '', fn ($query) => $query->where(function ($query) use ($term) {
                $query->whereRaw('lower(sku) like ?', [$term])
                    ->orWhereRaw('lower(name) like ?', [$term])
                    ->orWhereRaw('lower(description) like ?', [$term])
                    ->orWhereHas('customFields', fn ($fields) => $fields->whereRaw('lower(custom_field_product.value) like ?', [$term]));
            }))
            ->orderBy('name')
            ->paginate(12);
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function packagingOptions(): array
    {
        $inUse = Product::query()->active()->distinct()->pluck('packaging_type')->map->value->all();

        return collect(PackagingType::options())->only($inUse)->all();
    }

    public function addToCart(int $productId, Cart $cart): void
    {
        $product = Product::query()->active()->findOrFail($productId);

        if ($product->stock_quantity < 1) {
            Flux::toast(text: __('That item is out of stock.'), variant: 'danger');

            return;
        }

        $cart->add($product);

        $this->dispatch('cart-updated');
        Flux::toast(text: __(':name added to your cart.', ['name' => $product->name]), variant: 'success');
    }
}; ?>

<div>
    {{-- Hero --}}
    <section class="mx-auto max-w-7xl px-6 pt-8 lg:px-8" data-test="hero">
        <div class="rounded-[2.5rem] bg-[#112336] px-8 py-12 text-white shadow-xl sm:px-16 sm:py-16">
            <h1 class="max-w-4xl text-3xl font-extrabold uppercase tracking-tight sm:text-5xl">
                {{ __('Business labels. Local service. Better value.') }}
            </h1>

            <p class="mt-8 max-w-3xl text-lg text-slate-100 sm:text-xl">
                {{ __('A purpose-built online store for DFW warehouses, manufacturers, fulfillment centers and other commercial buyers.') }}
            </p>

            <div class="mt-10 flex flex-wrap gap-4">
                <a href="#catalog" class="inline-flex min-w-64 items-center justify-center rounded-md bg-[#00868b] px-8 py-4 text-sm font-bold uppercase tracking-wide text-white shadow-md transition hover:bg-[#007479]">
                    {{ __('Shop business labels') }}
                </a>
                <a href="{{ route('volume-pricing') }}" wire:navigate class="inline-flex min-w-64 items-center justify-center rounded-md bg-white px-8 py-4 text-sm font-bold uppercase tracking-wide text-[#112336] shadow-md transition hover:bg-slate-100">
                    {{ __('Request volume pricing') }}
                </a>
            </div>

            <dl class="mt-12 grid gap-6 border-t border-white/15 pt-8 text-sm sm:grid-cols-3">
                <div class="flex items-start gap-3">
                    <flux:icon.cube class="mt-0.5 size-5 shrink-0 text-teal-300" />
                    <div>
                        <dt class="font-semibold">{{ __('Sold in bulk packs') }}</dt>
                        <dd class="text-slate-300">{{ __('Every item shows its packaging and quantity per pack.') }}</dd>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <flux:icon.lock-closed class="mt-0.5 size-5 shrink-0 text-teal-300" />
                    <div>
                        <dt class="font-semibold">{{ __('Secure card payment') }}</dt>
                        <dd class="text-slate-300">{{ __('Payments are processed by Stripe.') }}</dd>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <flux:icon.user-circle class="mt-0.5 size-5 shrink-0 text-teal-300" />
                    <div>
                        <dt class="font-semibold">{{ __('Account or guest') }}</dt>
                        <dd class="text-slate-300">{{ __('Order history for accounts; no sign-up needed for one-off orders.') }}</dd>
                    </div>
                </div>
            </dl>
        </div>
    </section>

    {{-- Catalog --}}
    <section id="catalog" class="mx-auto max-w-7xl scroll-mt-4 px-6 py-12 lg:px-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="xl" level="2">{{ __('Catalog') }}</flux:heading>
                <flux:text class="mt-1">{{ trans_choice(':count item|:count items', $this->products->total()) }}</flux:text>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Search SKU, name or details') }}" clearable class="sm:w-72" />
                <flux:select wire:model.live="packaging" class="sm:w-44">
                    <flux:select.option value="">{{ __('All packaging') }}</flux:select.option>
                    @foreach ($this->packagingOptions as $value => $label)
                        <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        @if ($this->products->isEmpty())
            <div class="mt-10 rounded-xl border border-dashed border-zinc-300 p-12 text-center dark:border-zinc-600">
                <flux:icon.archive-box class="mx-auto size-8 text-zinc-400" />
                <flux:heading class="mt-3">{{ __('No items found') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Try a different search or clear the packaging filter.') }}</flux:text>
            </div>
        @else
            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($this->products as $product)
                    <article wire:key="product-{{ $product->id }}" class="group flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white transition hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800">
                        <a href="{{ route('products.show', $product) }}" wire:navigate class="flex aspect-[4/3] items-center justify-center bg-gradient-to-br from-zinc-100 to-zinc-200 dark:from-zinc-700 dark:to-zinc-800">
                            <flux:icon.tag class="size-12 text-zinc-400 transition group-hover:scale-110 dark:text-zinc-500" />
                        </a>

                        <div class="flex flex-1 flex-col gap-2 p-4">
                            <flux:text size="sm" class="font-mono">{{ $product->sku }}</flux:text>
                            <a href="{{ route('products.show', $product) }}" wire:navigate class="font-semibold leading-snug hover:underline">{{ $product->name }}</a>

                            <div class="flex flex-wrap gap-1">
                                <flux:badge size="sm">{{ $product->packageLabel() }}</flux:badge>
                                @foreach ($product->customFields->filter(fn ($field) => filled($field->pivot->value))->take(2) as $field)
                                    <flux:badge size="sm" color="zinc">{{ $field->name }}: {{ $field->pivot->value }}</flux:badge>
                                @endforeach
                            </div>

                            <div class="mt-auto flex items-end justify-between pt-3">
                                <div>
                                    <div class="text-lg font-bold">{{ $product->formattedPrice() }}</div>
                                    <flux:text size="sm">{{ __('per :type', ['type' => strtolower($product->packaging_type->label())]) }}</flux:text>
                                </div>

                                @if ($product->stock_quantity > 0)
                                    <flux:button wire:click="addToCart({{ $product->id }})" variant="primary" size="sm" icon="shopping-cart" data-test="add-to-cart-{{ $product->id }}">
                                        {{ __('Add') }}
                                    </flux:button>
                                @else
                                    <flux:badge color="red" size="sm">{{ __('Out of stock') }}</flux:badge>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-10">
                <flux:pagination :paginator="$this->products" />
            </div>
        @endif
    </section>
</div>
