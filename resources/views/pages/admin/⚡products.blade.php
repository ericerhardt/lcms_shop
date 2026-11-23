<?php

use App\Models\Product;
use App\Services\InventoryCsv;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Inventory')] class extends Component {
    use WithFileUploads, WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public $importFile = null;

    /**
     * @var array{created: int, updated: int, errors: array<int, string>, ignored_columns: array<int, string>}|null
     */
    public ?array $importResult = null;

    public function updatedSearch(): void
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
            ->when(trim($this->search) !== '', fn ($query) => $query->where(function ($query) use ($term) {
                $query->whereRaw('lower(sku) like ?', [$term])->orWhereRaw('lower(name) like ?', [$term]);
            }))
            ->orderBy('sku')
            ->paginate(15);
    }

    public function toggleActive(int $productId): void
    {
        $product = Product::findOrFail($productId);
        $product->update(['is_active' => ! $product->is_active]);

        unset($this->products);
    }

    public function delete(int $productId): void
    {
        Product::findOrFail($productId)->delete();

        unset($this->products);
        Flux::toast(text: __('Product deleted.'), variant: 'success');
    }

    public function import(InventoryCsv $inventory): void
    {
        $this->validate(['importFile' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);

        $this->importResult = $inventory->import($this->importFile->getRealPath());
        $this->importFile = null;

        unset($this->products);
        Flux::modal('import')->close();
    }
}; ?>

<section class="w-full">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">{{ __('Inventory') }}</flux:heading>
            <flux:subheading>{{ __('Manage the items shown in your storefront.') }}</flux:subheading>
        </div>

        <div class="flex flex-wrap gap-2">
            <flux:button :href="route('admin.inventory.export')" icon="arrow-down-tray" data-test="export-button">{{ __('Export CSV') }}</flux:button>
            <flux:modal.trigger name="import">
                <flux:button icon="arrow-up-tray" data-test="import-button">{{ __('Import CSV') }}</flux:button>
            </flux:modal.trigger>
            <flux:button :href="route('admin.products.create')" variant="primary" icon="plus" wire:navigate>{{ __('New item') }}</flux:button>
        </div>
    </div>

    @if ($importResult)
        <flux:callout class="mt-6" :variant="$importResult['errors'] ? 'warning' : 'success'" icon="document-check" data-test="import-result">
            <flux:callout.heading>
                {{ __('Import finished: :created created, :updated updated.', ['created' => $importResult['created'], 'updated' => $importResult['updated']]) }}
            </flux:callout.heading>
            @if ($importResult['ignored_columns'])
                <flux:callout.text>{{ __('Ignored unknown custom field columns: :cols', ['cols' => implode(', ', $importResult['ignored_columns'])]) }}</flux:callout.text>
            @endif
            @if ($importResult['errors'])
                <flux:callout.text>
                    <ul class="list-disc ps-5">
                        @foreach (array_slice($importResult['errors'], 0, 10) as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    @if (count($importResult['errors']) > 10)
                        {{ __('…and :count more.', ['count' => count($importResult['errors']) - 10]) }}
                    @endif
                </flux:callout.text>
            @endif
        </flux:callout>
    @endif

    <div class="mt-6 max-w-sm">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Search SKU or name') }}" clearable />
    </div>

    <div class="mt-4">
        <flux:table :paginate="$this->products">
            <flux:table.columns>
                <flux:table.column>{{ __('SKU') }}</flux:table.column>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Packaging') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Price') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Stock') }}</flux:table.column>
                <flux:table.column>{{ __('Active') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->products as $product)
                    <flux:table.row :key="$product->id">
                        <flux:table.cell class="font-mono">{{ $product->sku }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-3">
                                @if ($product->imageUrl())
                                    <img src="{{ $product->imageUrl() }}" alt="" loading="lazy" class="size-8 rounded object-cover">
                                @endif
                                {{ $product->name }}
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $product->packageLabel() }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $product->formattedPrice() }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($product->stock_quantity === 0)
                                <flux:badge color="red" size="sm">0</flux:badge>
                            @else
                                {{ number_format($product->stock_quantity) }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:switch :checked="$product->is_active" wire:click="toggleActive({{ $product->id }})" />
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button :href="route('admin.products.edit', $product)" size="sm" variant="ghost" icon="pencil-square" wire:navigate :aria-label="__('Edit')" />
                            <flux:button wire:click="delete({{ $product->id }})" wire:confirm="{{ __('Delete :sku? This cannot be undone.', ['sku' => $product->sku]) }}" size="sm" variant="ghost" icon="trash" :aria-label="__('Delete')" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-8 text-center">{{ __('No items yet. Add one or import a CSV.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="import" class="md:w-[32rem]">
        <form wire:submit="import" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Import inventory') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Upload a CSV with the columns sku, name, description, packaging_type, quantity_per_package, price, stock_quantity and is_active. Existing SKUs are updated; new SKUs are created. Custom fields use a "custom:<key>" column. Tip: export first to get a template.') }}
                </flux:text>
            </div>

            <div>
                <input type="file" wire:model="importFile" accept=".csv,.txt" class="block w-full text-sm file:me-3 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-2 dark:file:bg-zinc-700" data-test="import-file" />
                @error('importFile')
                    <flux:text class="mt-2 !text-red-500">{{ $message }}</flux:text>
                @enderror
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="import-submit">{{ __('Import') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
