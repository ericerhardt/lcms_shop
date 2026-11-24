<x-layouts::shop :title="__('Order confirmation')">
    <div class="mx-auto max-w-2xl px-6 py-16 lg:px-8">
        @if ($order->status === \App\Enums\OrderStatus::Paid)
            <div class="text-center">
                <flux:icon.check-circle class="mx-auto size-14 text-green-500" />
                <flux:heading size="xl" level="1" class="mt-4">{{ __('Thank you for your order!') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Order :number is confirmed. A confirmation email is on its way to :email.', ['number' => $order->number(), 'email' => $order->email]) }}
                </flux:text>
            </div>
        @else
            <div class="text-center">
                <flux:icon.clock class="mx-auto size-14 text-amber-500" />
                <flux:heading size="xl" level="1" class="mt-4">{{ __('We have not received your payment yet') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ $paymentFailed ? __('Stripe has not confirmed the payment. If you were charged, please contact us with order :number.', ['number' => $order->number()]) : __('This order is still awaiting payment.') }}
                </flux:text>
                <flux:button :href="route('cart')" class="mt-4">{{ __('Back to cart') }}</flux:button>
            </div>
        @endif

        <div class="mt-10 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <ul class="divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                @foreach ($order->items as $item)
                    <li class="flex justify-between gap-4 py-3">
                        <div>
                            <div class="font-medium">{{ $item->name }}</div>
                            <div class="font-mono text-xs text-zinc-500">{{ $item->sku }}</div>
                            <div class="text-zinc-500">{{ $item->quantity }} &times; {{ ucfirst($item->packaging_type) }} of {{ number_format($item->quantity_per_package) }}</div>
                        </div>
                        <div class="font-medium">{{ $item->formattedLineTotal() }}</div>
                    </li>
                @endforeach
            </ul>
            <dl class="space-y-2 border-t border-zinc-200 pt-4 text-sm dark:border-zinc-700">
                <div class="flex justify-between">
                    <dt>{{ __('Subtotal') }}</dt>
                    <dd>{{ $order->formattedSubtotal() }}</dd>
                </div>
                @if ($order->shipping_carrier)
                    <div class="flex justify-between">
                        <dt>{{ __('Shipping') }} <span class="text-zinc-500">({{ $order->shipping_carrier }}, {{ $order->shipping_timeframe }})</span></dt>
                        <dd>{{ $order->formattedShipping() }}</dd>
                    </div>
                @endif
                @if ($order->tax_cents > 0)
                    <div class="flex justify-between">
                        <dt>{{ $order->tax_label }}</dt>
                        <dd>{{ $order->formattedTax() }}</dd>
                    </div>
                @endif
                <div class="flex justify-between border-t border-zinc-200 pt-3 text-base font-semibold dark:border-zinc-700">
                    <dt>{{ __('Total') }}</dt>
                    <dd>{{ $order->formattedTotal() }}</dd>
                </div>
            </dl>
        </div>

        <div class="mt-6 text-center">
            <flux:button :href="route('home')" variant="primary" wire:navigate>{{ __('Continue shopping') }}</flux:button>
        </div>
    </div>
</x-layouts::shop>
