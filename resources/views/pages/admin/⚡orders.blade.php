<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Orders')] class extends Component {
    use WithPagination;

    #[Url]
    public string $status = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Order>
     */
    #[Computed]
    public function orders(): LengthAwarePaginator
    {
        return Order::query()
            ->with('items')
            ->when(OrderStatus::tryFrom($this->status), fn ($query, OrderStatus $status) => $query->where('status', $status))
            ->latest()
            ->paginate(15);
    }
}; ?>

<section class="w-full">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">{{ __('Orders') }}</flux:heading>
            <flux:subheading>{{ __('Orders placed through the storefront.') }}</flux:subheading>
        </div>
        <flux:select wire:model.live="status" class="w-44">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach (\App\Enums\OrderStatus::cases() as $case)
                <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div class="mt-6 space-y-3">
        @forelse ($this->orders as $order)
            <details wire:key="order-{{ $order->id }}" class="group rounded-xl border border-zinc-200 dark:border-zinc-700">
                <summary class="flex cursor-pointer flex-wrap items-center justify-between gap-3 p-4">
                    <div>
                        <span class="font-semibold">{{ $order->number() }}</span>
                        <span class="ms-2 text-sm text-zinc-500">{{ $order->created_at->format('M j, Y g:i A') }}</span>
                        <div class="text-sm text-zinc-500">{{ $order->name }}{{ $order->company ? ' · '.$order->company : '' }} &middot; {{ $order->email }}</div>
                    </div>
                    <div class="flex items-center gap-3">
                        @unless ($order->user_id)
                            <flux:badge size="sm" color="zinc">{{ __('Guest') }}</flux:badge>
                        @endunless
                        <flux:badge :color="$order->status->color()">{{ $order->status->label() }}</flux:badge>
                        <span class="font-semibold">{{ $order->formattedTotal() }}</span>
                    </div>
                </summary>
                <div class="grid gap-6 border-t border-zinc-200 p-4 text-sm dark:border-zinc-700 md:grid-cols-2">
                    <ul class="space-y-1">
                        @foreach ($order->items as $item)
                            <li class="flex justify-between gap-3">
                                <span>{{ $item->quantity }} &times; {{ $item->name }} <span class="font-mono text-xs text-zinc-500">({{ $item->sku }})</span></span>
                                <span>{{ $item->formattedLineTotal() }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <address class="not-italic text-zinc-600 dark:text-zinc-400">
                        {{ $order->name }}<br>
                        @if ($order->company){{ $order->company }}<br>@endif
                        {{ $order->address_line1 }}<br>
                        @if ($order->address_line2){{ $order->address_line2 }}<br>@endif
                        {{ $order->city }}, {{ $order->state }} {{ $order->postal_code }} {{ $order->country }}
                        @if ($order->phone)<br>{{ $order->phone }}@endif
                    </address>
                </div>
            </details>
        @empty
            <flux:text class="py-8 text-center">{{ __('No orders yet.') }}</flux:text>
        @endforelse
    </div>

    <div class="mt-6">
        <flux:pagination :paginator="$this->orders" />
    </div>
</section>
