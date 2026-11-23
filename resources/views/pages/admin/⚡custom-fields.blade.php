<?php

use App\Models\CustomField;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Custom fields')] class extends Component {
    public ?int $editingId = null;

    public string $name = '';
    public string $type = 'text';
    public string $options = '';
    public int|string $position = 0;

    /**
     * @return Collection<int, CustomField>
     */
    #[Computed]
    public function fields(): Collection
    {
        return CustomField::query()->withCount('products')->orderBy('position')->orderBy('id')->get();
    }

    public function create(): void
    {
        $this->resetForm();
        Flux::modal('field')->show();
    }

    public function edit(int $fieldId): void
    {
        $field = CustomField::findOrFail($fieldId);

        $this->editingId = $field->id;
        $this->name = $field->name;
        $this->type = $field->type;
        $this->options = implode("\n", $field->options ?? []);
        $this->position = $field->position;

        $this->resetValidation();
        Flux::modal('field')->show();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(array_keys(CustomField::TYPES))],
            'options' => ['required_if:type,select', 'nullable', 'string', 'max:2000'],
            'position' => ['required', 'integer', 'min:0'],
        ]);

        $options = $validated['type'] === 'select'
            ? collect(preg_split('/\R/', $validated['options']))->map(fn ($line) => trim($line))->filter()->unique()->values()->all()
            : null;

        if ($validated['type'] === 'select' && $options === []) {
            $this->addError('options', __('Add at least one option.'));

            return;
        }

        $attributes = [
            'name' => $validated['name'],
            'type' => $validated['type'],
            'options' => $options,
            'position' => (int) $validated['position'],
        ];

        if ($this->editingId) {
            CustomField::findOrFail($this->editingId)->update($attributes);
        } else {
            CustomField::create([...$attributes, 'key' => $this->uniqueKey($validated['name'])]);
        }

        unset($this->fields);
        Flux::modal('field')->close();
        Flux::toast(text: __('Custom field saved.'), variant: 'success');
    }

    public function delete(int $fieldId): void
    {
        CustomField::findOrFail($fieldId)->delete();

        unset($this->fields);
        Flux::toast(text: __('Custom field deleted.'), variant: 'success');
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'name', 'type', 'options', 'position');
        $this->resetValidation();
    }

    private function uniqueKey(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'field';
        $key = $base;
        $suffix = 2;

        while (CustomField::where('key', $key)->exists()) {
            $key = $base.'_'.$suffix++;
        }

        return $key;
    }
}; ?>

<section class="w-full max-w-4xl">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">{{ __('Custom fields') }}</flux:heading>
            <flux:subheading>{{ __('Extra attributes you can fill in on every item, such as label size or adhesive.') }}</flux:subheading>
        </div>
        <flux:button wire:click="create" variant="primary" icon="plus" data-test="new-field">{{ __('New field') }}</flux:button>
    </div>

    <div class="mt-6">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('CSV column') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Items') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->fields as $field)
                    <flux:table.row :key="$field->id">
                        <flux:table.cell>{{ $field->name }}</flux:table.cell>
                        <flux:table.cell>
                            {{ \App\Models\CustomField::TYPES[$field->type] ?? $field->type }}
                            @if ($field->type === 'select')
                                <span class="text-zinc-500">({{ implode(', ', $field->options ?? []) }})</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">custom:{{ $field->key }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $field->products_count }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button wire:click="edit({{ $field->id }})" size="sm" variant="ghost" icon="pencil-square" :aria-label="__('Edit')" />
                            <flux:button wire:click="delete({{ $field->id }})" wire:confirm="{{ __('Delete this field and its values on all items?') }}" size="sm" variant="ghost" icon="trash" :aria-label="__('Delete')" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-8 text-center">{{ __('No custom fields yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="field" class="md:w-[28rem]">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? __('Edit custom field') : __('New custom field') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Field name')" placeholder="{{ __('e.g. Label size') }}" required />

            <flux:select wire:model.live="type" :label="__('Type')">
                @foreach (\App\Models\CustomField::TYPES as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($type === 'select')
                <flux:textarea wire:model="options" :label="__('Options (one per line)')" rows="4" />
            @endif

            <flux:input wire:model="position" type="number" min="0" :label="__('Display order')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="save-field">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
