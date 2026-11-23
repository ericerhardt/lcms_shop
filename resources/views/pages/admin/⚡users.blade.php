<?php

use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Users')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public ?int $editingId = null;

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public bool $is_admin = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        $term = '%'.Str::lower(trim($this->search)).'%';

        return User::query()
            ->withCount('orders')
            ->when(trim($this->search) !== '', fn ($query) => $query->where(function ($query) use ($term) {
                $query->whereRaw('lower(name) like ?', [$term])->orWhereRaw('lower(email) like ?', [$term]);
            }))
            ->orderBy('name')
            ->paginate(15);
    }

    public function create(): void
    {
        $this->resetForm();
        Flux::modal('user')->show();
    }

    public function edit(int $userId): void
    {
        $user = User::findOrFail($userId);

        $this->resetForm();
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->is_admin = $user->is_admin;

        Flux::modal('user')->show();
    }

    public function save(): void
    {
        $this->email = Str::lower(trim($this->email));

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($this->editingId)],
            'password' => [$this->editingId ? 'nullable' : 'required', 'string', Password::default()],
            'is_admin' => ['boolean'],
        ]);

        if ($this->editingId === auth()->id() && ! $validated['is_admin']) {
            $this->addError('is_admin', __('You cannot remove your own admin access.'));

            return;
        }

        $user = $this->editingId ? User::findOrFail($this->editingId) : new User;

        $user->fill(['name' => $validated['name'], 'email' => $validated['email']]);

        if (filled($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->is_admin = $validated['is_admin'];

        if (! $user->exists) {
            $user->email_verified_at = now();
        }

        $user->save();

        unset($this->users);
        Flux::modal('user')->close();
        Flux::toast(text: __('User saved.'), variant: 'success');
    }

    public function delete(int $userId): void
    {
        if ($userId === auth()->id()) {
            Flux::toast(text: __('You cannot delete your own account here.'), variant: 'danger');

            return;
        }

        User::findOrFail($userId)->delete();

        unset($this->users);
        Flux::toast(text: __('User deleted.'), variant: 'success');
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'name', 'email', 'password', 'is_admin');
        $this->resetValidation();
    }
}; ?>

<section class="w-full">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">{{ __('Users') }}</flux:heading>
            <flux:subheading>{{ __('Customer and admin accounts.') }}</flux:subheading>
        </div>
        <flux:button wire:click="create" variant="primary" icon="plus" data-test="new-user">{{ __('New user') }}</flux:button>
    </div>

    <div class="mt-6 max-w-sm">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search name or email')" clearable />
    </div>

    <div class="mt-4">
        <flux:table :paginate="$this->users">
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Email') }}</flux:table.column>
                <flux:table.column>{{ __('Role') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Orders') }}</flux:table.column>
                <flux:table.column>{{ __('Joined') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->users as $user)
                    <flux:table.row :key="$user->id">
                        <flux:table.cell>{{ $user->name }}</flux:table.cell>
                        <flux:table.cell>{{ $user->email }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$user->is_admin ? 'teal' : 'zinc'">{{ $user->is_admin ? __('Admin') : __('Customer') }}</flux:badge>
                            @unless ($user->email_verified_at)
                                <flux:badge size="sm" color="amber">{{ __('Unverified') }}</flux:badge>
                            @endunless
                        </flux:table.cell>
                        <flux:table.cell align="end">{{ $user->orders_count }}</flux:table.cell>
                        <flux:table.cell>{{ $user->created_at->format('M j, Y') }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button wire:click="edit({{ $user->id }})" size="sm" variant="ghost" icon="pencil-square" :aria-label="__('Edit')" />
                            @if ($user->id !== auth()->id())
                                <flux:button wire:click="delete({{ $user->id }})" wire:confirm="{{ __('Delete this user? Their past orders are kept.') }}" size="sm" variant="ghost" icon="trash" :aria-label="__('Delete')" />
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-8 text-center">{{ __('No users found.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="user" class="md:w-[28rem]">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? __('Edit user') : __('New user') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Name')" required autocomplete="off" />
            <flux:input wire:model="email" type="email" :label="__('Email')" required autocomplete="off" />
            <flux:input wire:model="password" type="password" :label="$editingId ? __('New password (leave blank to keep)') : __('Password')" viewable autocomplete="new-password" />

            <flux:field variant="inline">
                <flux:checkbox wire:model="is_admin" />
                <flux:label>{{ __('Administrator') }}</flux:label>
                <flux:error name="is_admin" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="save-user">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
