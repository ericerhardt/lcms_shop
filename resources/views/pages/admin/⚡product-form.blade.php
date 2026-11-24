<?php

use App\Enums\PackagingType;
use App\Models\CustomField;
use App\Models\Product;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Item')] class extends Component {
    use WithFileUploads;

    public ?Product $product = null;

    public string $sku = '';
    public string $name = '';
    public string $description = '';
    public string $packaging_type = 'box';
    public int|string $quantity_per_package = 1;
    public string $price = '';
    public int|string $stock_quantity = 0;
    public bool $is_active = true;

    public $photo = null;

    public bool $removeImage = false;

    /**
     * Custom field values keyed by custom field id.
     *
     * @var array<int, string|null>
     */
    public array $customValues = [];

    public function mount(?Product $product = null): void
    {
        if ($product?->exists) {
            $this->product = $product->load('customFields');
            $this->sku = $product->sku;
            $this->name = $product->name;
            $this->description = $product->description ?? '';
            $this->packaging_type = $product->packaging_type->value;
            $this->quantity_per_package = $product->quantity_per_package;
            $this->price = number_format($product->price_cents / 100, 2, '.', '');
            $this->stock_quantity = $product->stock_quantity;
            $this->is_active = $product->is_active;
            $this->customValues = $product->customFieldValues();
        }
    }

    public function updatedPhoto(): void
    {
        $this->removeImage = false;

        try {
            $this->validateOnly('photo', ['photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096']]);
        } catch (ValidationException $exception) {
            $this->photo = null;

            throw $exception;
        }
    }

    /**
     * @return Collection<int, CustomField>
     */
    #[Computed]
    public function customFields(): Collection
    {
        return CustomField::query()->orderBy('position')->orderBy('id')->get();
    }

    public function save(): void
    {
        $rules = [
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($this->product?->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'packaging_type' => ['required', Rule::enum(PackagingType::class)],
            'quantity_per_package' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];

        foreach ($this->customFields as $field) {
            $rules["customValues.{$field->id}"] = match ($field->type) {
                'number' => ['nullable', 'numeric'],
                'select' => ['nullable', Rule::in($field->options ?? [])],
                default => ['nullable', 'string', 'max:500'],
            };
        }

        $validated = $this->validate($rules);

        $product = $this->product ?? new Product;
        $product->fill([
            'sku' => trim($validated['sku']),
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'packaging_type' => $validated['packaging_type'],
            'quantity_per_package' => (int) $validated['quantity_per_package'],
            'price_cents' => (int) round(((float) $validated['price']) * 100),
            'stock_quantity' => (int) $validated['stock_quantity'],
            'is_active' => $validated['is_active'],
        ]);

        if ($this->photo) {
            $product->deleteImage();
            $product->image_path = $this->photo->store('products', 'public');
        } elseif ($this->removeImage) {
            $product->deleteImage();
            $product->image_path = null;
        }

        $product->save();

        $values = collect($this->customValues)
            ->filter(fn ($value, $fieldId) => filled($value) && $this->customFields->contains('id', (int) $fieldId))
            ->mapWithKeys(fn ($value, $fieldId) => [(int) $fieldId => ['value' => (string) $value]]);

        $product->customFields()->sync($values->all());

        Flux::toast(text: __('Item saved.'), variant: 'success');

        $this->redirectRoute('admin.products', navigate: true);
    }
}; ?>

<section class="w-full max-w-3xl">
    <flux:button :href="route('admin.products')" variant="subtle" size="sm" icon="arrow-left" wire:navigate class="-ms-2">{{ __('Inventory') }}</flux:button>

    <flux:heading size="xl" level="1" class="mt-2">{{ $product ? __('Edit :sku', ['sku' => $product->sku]) : __('New item') }}</flux:heading>

    <form wire:submit="save" class="mt-8 space-y-8">
        <div class="space-y-4">
            <flux:heading>{{ __('Item details') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="sku" :label="__('SKU')" required />
                <flux:input wire:model="name" :label="__('Name')" required />
            </div>
            <flux:textarea wire:model="description" :label="__('Description')" rows="4" />
        </div>

        <div class="space-y-4">
            <flux:heading>{{ __('Image') }}</flux:heading>
            <div class="flex flex-wrap items-start gap-5">
                @php($currentImage = $removeImage ? null : $product?->imageUrl())
                @if ($photo && $photo->isPreviewable())
                    <img src="{{ $photo->temporaryUrl() }}" alt="" class="size-32 rounded-lg border border-zinc-200 bg-white object-contain p-1 dark:border-zinc-700" data-test="image-preview">
                @elseif ($currentImage)
                    <img src="{{ $currentImage }}" alt="{{ $product->name }}" class="size-32 rounded-lg border border-zinc-200 bg-white object-contain p-1 dark:border-zinc-700" data-test="image-current">
                @else
                    <div class="flex size-32 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                        <flux:icon.photo class="size-10 text-zinc-400" />
                    </div>
                @endif

                <div class="min-w-64 flex-1 space-y-3">
                    <flux:input wire:model="photo" type="file" accept="image/png,image/jpeg,image/webp" :label="__('Upload an image')" />
                    <flux:text size="sm">{{ __('JPG, PNG or WebP, up to 4 MB. Square images look best.') }}</flux:text>
                    @if ($product?->image_path && ! $photo)
                        <flux:switch wire:model.live="removeImage" :label="__('Remove the current image')" />
                    @endif
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <flux:heading>{{ __('Packaging & pricing') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="packaging_type" :label="__('Packaging type')">
                    @foreach (\App\Enums\PackagingType::options() as $value => $label)
                        <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="quantity_per_package" type="number" min="1" :label="__('Quantity per package')" required />
                <flux:input wire:model="price" type="number" step="0.01" min="0" :label="__('Price per package (USD)')" required />
                <flux:input wire:model="stock_quantity" type="number" min="0" :label="__('Packages in stock')" required />
            </div>
            <flux:switch wire:model="is_active" :label="__('Visible in the storefront')" />
        </div>

        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading>{{ __('Custom fields') }}</flux:heading>
                <flux:link :href="route('admin.custom-fields')" class="text-sm" wire:navigate>{{ __('Manage fields') }}</flux:link>
            </div>

            @forelse ($this->customFields as $field)
                <div wire:key="field-{{ $field->id }}">
                    @if ($field->type === 'select')
                        <flux:select wire:model="customValues.{{ $field->id }}" :label="$field->name">
                            <flux:select.option value="">—</flux:select.option>
                            @foreach ($field->options ?? [] as $option)
                                <flux:select.option :value="$option">{{ $option }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @else
                        <flux:input wire:model="customValues.{{ $field->id }}" :type="$field->type === 'number' ? 'number' : 'text'" step="any" :label="$field->name" />
                    @endif
                </div>
            @empty
                <flux:text>{{ __('No custom fields yet. Add some to capture details like label size, adhesive or core size.') }}</flux:text>
            @endforelse
        </div>

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary" data-test="save-product">{{ __('Save item') }}</flux:button>
            <flux:button :href="route('admin.products')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
