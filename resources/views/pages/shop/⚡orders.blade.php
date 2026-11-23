<?php

use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('My orders')] #[Layout('layouts::shop')] class extends Component {
    use WithPagination;

    /**
     * @return LengthAwarePaginator<int, Order>
     */
    #[Computed]
    public function orders(): LengthAwarePaginator
    {
        return Order::query()
            ->where('user_id', Auth::id())
            ->with('items')
            ->latest()
            ->paginate(10);
    }
}; ?>

<div class="mx-auto max-w-4xl px-6 py-10 lg:px-8">
    <flux:heading size="xl" level="1">{{ __('My orders') }}</flux:heading>

    @if ($this->orders->isEmpty())
        <div class="mt-8 rounded-xl border border-dashed border-zinc-300 p-12 text-center dark:border-zinc-600">
            <flux:heading>{{ __('No orders yet') }}</flux:heading>
            <flux:button :href="route('home')" variant="primary" class="mt-4" wire:navigate>{{ __('Browse the catalog') }}</flux:button>
        </div>
    @else
        <div class="mt-8 space-y-4">
            @foreach ($this->orders as $order)
                <div wire:key="order-{{ $order->id }}" class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <span class="font-semibold">{{ $order->number() }}</span>
                            <span class="ms-2 text-sm text-zinc-500">{{ $order->created_at->format('M j, Y') }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <flux:badge :color="$order->status->color()">{{ $order->status->label() }}</flux:badge>
                            <span class="font-semibold">{{ $order->formattedTotal() }}</span>
                        </div>
                    </div>
                    <ul class="mt-3 space-y-1 text-sm text-zinc-600 dark:text-zinc-400">
                        @foreach ($order->items as $item)
                            <li>{{ $item->quantity }} &times; {{ $item->name }} <span class="font-mono text-xs">({{ $item->sku }})</span></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        <div class="mt-8">
            <flux:pagination :paginator="$this->orders" />
        </div>
    @endif
</div>
