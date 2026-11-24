<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderNotifier;
use Flux\Flux;
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

    public function resendConfirmation(int $orderId, OrderNotifier $notifier): void
    {
        $order = Order::where('status', OrderStatus::Paid)->findOrFail($orderId);

        if ($notifier->sendConfirmation($order, force: true)) {
            Flux::toast(text: __('Confirmation email sent to :email.', ['email' => $order->email]), variant: 'success');
        } else {
            Flux::toast(text: __('The email could not be sent. Check the mail settings and logs.'), variant: 'danger');
        }
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
                    <dl class="space-y-1 md:col-span-2 md:max-w-xs md:justify-self-end md:w-full">
                        <div class="flex justify-between"><dt>{{ __('Subtotal') }}</dt><dd>{{ $order->formattedSubtotal() }}</dd></div>
                        @if ($order->shipping_carrier)
                            <div class="flex justify-between gap-3"><dt>{{ __('Shipping') }} <span class="text-zinc-500">({{ $order->shipping_carrier }}, {{ $order->shipping_timeframe }})</span></dt><dd>{{ $order->formattedShipping() }}</dd></div>
                        @endif
                        @if ($order->tax_cents > 0)
                            <div class="flex justify-between"><dt>{{ $order->tax_label }}</dt><dd>{{ $order->formattedTax() }}</dd></div>
                        @endif
                        <div class="flex justify-between border-t border-zinc-200 pt-1 font-semibold dark:border-zinc-700"><dt>{{ __('Total') }}</dt><dd>{{ $order->formattedTotal() }}</dd></div>
                    </dl>
                    <address class="not-italic text-zinc-600 dark:text-zinc-400">
                        {{ $order->name }}<br>
                        @if ($order->company){{ $order->company }}<br>@endif
                        {{ $order->address_line1 }}<br>
                        @if ($order->address_line2){{ $order->address_line2 }}<br>@endif
                        {{ $order->city }}, {{ $order->state }} {{ $order->postal_code }} {{ $order->country }}
                        @if ($order->phone)<br>{{ $order->phone }}@endif
                    </address>

                    @if ($order->status === \App\Enums\OrderStatus::Paid)
                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700 md:col-span-2">
                            <flux:text size="sm">
                                {{ $order->confirmation_sent_at ? __('Confirmation emailed :when.', ['when' => $order->confirmation_sent_at->format('M j, Y g:i A')]) : __('No confirmation email has been sent for this order.') }}
                            </flux:text>
                            <flux:button wire:click="resendConfirmation({{ $order->id }})" size="sm" icon="paper-airplane" data-test="resend-confirmation">{{ $order->confirmation_sent_at ? __('Send again') : __('Send confirmation') }}</flux:button>
                        </div>
                    @endif
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
