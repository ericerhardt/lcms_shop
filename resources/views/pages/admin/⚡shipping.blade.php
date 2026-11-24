<?php

use App\Models\ShippingMethod;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Shipping')] class extends Component {
    public ?int $editingId = null;

    public string $carrier = '';
    public string $timeframe = '';
    public string $price = '';
    public int|string $position = 0;
    public bool $is_active = true;

    /**
     * @return Collection<int, ShippingMethod>
     */
    #[Computed]
    public function methods(): Collection
    {
        return ShippingMethod::query()->ordered()->get();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->position = (int) ShippingMethod::max('position') + 1;
        Flux::modal('method')->show();
    }

    public function edit(int $methodId): void
    {
        $method = ShippingMethod::findOrFail($methodId);

        $this->resetForm();
        $this->editingId = $method->id;
        $this->carrier = $method->carrier;
        $this->timeframe = $method->timeframe;
        $this->price = number_format($method->price_cents / 100, 2, '.', '');
        $this->position = $method->position;
        $this->is_active = $method->is_active;

        Flux::modal('method')->show();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'carrier' => ['required', 'string', 'max:100'],
            'timeframe' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999'],
            'position' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $attributes = [
            'carrier' => trim($validated['carrier']),
            'timeframe' => trim($validated['timeframe']),
            'price_cents' => (int) round(((float) $validated['price']) * 100),
            'position' => (int) $validated['position'],
            'is_active' => $validated['is_active'],
        ];

        if ($this->editingId) {
            ShippingMethod::findOrFail($this->editingId)->update($attributes);
        } else {
            ShippingMethod::create($attributes);
        }

        unset($this->methods);
        Flux::modal('method')->close();
        Flux::toast(text: __('Shipping method saved.'), variant: 'success');
    }

    public function toggle(int $methodId): void
    {
        $method = ShippingMethod::findOrFail($methodId);
        $method->update(['is_active' => ! $method->is_active]);

        unset($this->methods);
    }

    public function delete(int $methodId): void
    {
        ShippingMethod::findOrFail($methodId)->delete();

        unset($this->methods);
        Flux::toast(text: __('Shipping method deleted.'), variant: 'success');
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'carrier', 'timeframe', 'price', 'position', 'is_active');
        $this->resetValidation();
    }
}; ?>

<section class="w-full max-w-4xl">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">{{ __('Shipping') }}</flux:heading>
            <flux:subheading>{{ __('The options customers choose from at checkout. The price is added to the order total.') }}</flux:subheading>
        </div>
        <flux:button wire:click="create" variant="primary" icon="plus" data-test="new-method">{{ __('New shipping method') }}</flux:button>
    </div>

    @if ($this->methods->where('is_active', true)->isEmpty())
        <flux:callout variant="warning" icon="exclamation-triangle" class="mt-6" data-test="no-active-methods">
            <flux:callout.heading>{{ __('No active shipping methods') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Checkout will not ask for a shipping method and no shipping is charged until you add and activate one.') }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="mt-6">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Carrier') }}</flux:table.column>
                <flux:table.column>{{ __('Timeframe') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Cost') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->methods as $method)
                    <flux:table.row :key="$method->id">
                        <flux:table.cell>{{ $method->carrier }}</flux:table.cell>
                        <flux:table.cell>{{ $method->timeframe }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $method->formattedPrice() }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:switch wire:click="toggle({{ $method->id }})" :checked="$method->is_active" :aria-label="__('Active')" />
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button wire:click="edit({{ $method->id }})" size="sm" variant="ghost" icon="pencil-square" :aria-label="__('Edit')" />
                            <flux:button wire:click="delete({{ $method->id }})" wire:confirm="{{ __('Delete this shipping method? Past orders keep the shipping details they were placed with.') }}" size="sm" variant="ghost" icon="trash" :aria-label="__('Delete')" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-8 text-center">{{ __('No shipping methods yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="method" class="md:w-[28rem]">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? __('Edit shipping method') : __('New shipping method') }}</flux:heading>

            <flux:input wire:model="carrier" :label="__('Carrier')" placeholder="{{ __('e.g. UPS Ground') }}" required />
            <flux:input wire:model="timeframe" :label="__('Timeframe')" placeholder="{{ __('e.g. 3-5 business days') }}" required />
            <flux:input wire:model="price" type="number" step="0.01" min="0" :label="__('Cost (USD, 0 for free)')" required />
            <flux:input wire:model="position" type="number" min="0" :label="__('Display order')" />
            <flux:switch wire:model="is_active" :label="__('Available at checkout')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="save-method">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
