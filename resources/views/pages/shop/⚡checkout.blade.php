<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\ShippingMethod;
use App\Services\Cart;
use App\Services\CheckoutTotals;
use App\Services\StripeCheckout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Checkout')] #[Layout('layouts::shop')] class extends Component {
    public string $email = '';
    public string $name = '';
    public string $company = '';
    public string $phone = '';
    public string $address_line1 = '';
    public string $address_line2 = '';
    public string $city = '';
    public string $state = '';
    public string $postal_code = '';
    public string $country = 'US';

    public ?int $shipping_method_id = null;

    public ?string $paymentError = null;

    public function mount(Cart $cart): void
    {
        if ($cart->isEmpty()) {
            $this->redirectRoute('cart', navigate: true);

            return;
        }

        if ($user = Auth::user()) {
            $this->email = $user->email;
            $this->name = $user->name;
        }

        $this->shipping_method_id = $this->shippingMethods->first()?->id;
    }

    /**
     * @return Collection<int, ShippingMethod>
     */
    #[Computed]
    public function shippingMethods(): Collection
    {
        return ShippingMethod::query()->active()->ordered()->get();
    }

    /**
     * Create the order from the cart and send the customer to Stripe to pay.
     */
    public function pay(Cart $cart, StripeCheckout $stripe, CheckoutTotals $totals): void
    {
        $this->paymentError = null;

        $validated = $this->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['required', 'string', 'size:2'],
            'shipping_method_id' => $this->shippingMethods->isEmpty()
                ? ['nullable']
                : ['required', Rule::in($this->shippingMethods->pluck('id')->all())],
        ], [
            'shipping_method_id.required' => __('Choose a shipping method.'),
            'shipping_method_id.in' => __('Choose a shipping method.'),
        ]);

        $lines = $cart->lines();

        if ($lines->isEmpty()) {
            $this->redirectRoute('cart', navigate: true);

            return;
        }

        if (! $stripe->isConfigured()) {
            $this->paymentError = __('Online payments are not set up yet. Please contact us to place your order.');

            return;
        }

        $shipping = $this->shippingMethods->firstWhere('id', $this->shipping_method_id);
        $amounts = Arr::except($totals->calculate($lines->sum('total_cents'), $shipping), 'total_cents');

        $order = DB::transaction(function () use ($validated, $lines, $amounts): Order {
            $order = Order::create([
                ...Arr::except($validated, 'shipping_method_id'),
                ...$amounts,
                'country' => strtoupper($validated['country']),
                'user_id' => Auth::id(),
                'status' => OrderStatus::Pending,
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'product_id' => $line['product']->id,
                    'sku' => $line['product']->sku,
                    'name' => $line['product']->name,
                    'packaging_type' => $line['product']->packaging_type->value,
                    'quantity_per_package' => $line['product']->quantity_per_package,
                    'unit_price_cents' => $line['product']->price_cents,
                    'quantity' => $line['quantity'],
                ]);
            }

            return $order->load('items');
        });

        try {
            $session = $stripe->createSession(
                $order,
                route('checkout.success', ['order' => $order->token]).'&session_id={CHECKOUT_SESSION_ID}',
                route('checkout.cancel'),
            );
        } catch (Throwable $e) {
            report($e);
            $order->update(['status' => OrderStatus::Cancelled]);
            $this->paymentError = __('We could not start the payment. Please try again in a moment.');

            return;
        }

        $order->update(['stripe_session_id' => $session['id']]);

        $this->redirect($session['url']);
    }

    public function with(): array
    {
        $lines = app(Cart::class)->lines();
        $shipping = $this->shippingMethods->firstWhere('id', $this->shipping_method_id);

        return [
            'lines' => $lines,
            'totals' => app(CheckoutTotals::class)->calculate($lines->sum('total_cents'), $shipping),
        ];
    }
}; ?>

<div class="mx-auto max-w-5xl px-6 py-10 lg:px-8">
    <flux:heading size="xl" level="1">{{ __('Checkout') }}</flux:heading>

    @guest
        <flux:callout icon="user-circle" class="mt-6">
            <flux:callout.heading>{{ __('Checking out as a guest') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Have an account?') }}
                <flux:callout.link :href="route('login')">{{ __('Log in') }}</flux:callout.link>
                {{ __('or') }}
                <flux:callout.link :href="route('register')">{{ __('create one') }}</flux:callout.link>
                {{ __('to track your orders. Otherwise just fill in the details below.') }}
            </flux:callout.text>
        </flux:callout>
    @endguest

    <div class="mt-8 grid gap-8 lg:grid-cols-5">
        <form wire:submit="pay" class="space-y-8 lg:col-span-3">
            <fieldset class="space-y-4">
                <flux:heading>{{ __('Contact') }}</flux:heading>
                <flux:input wire:model="email" type="email" :label="__('Email')" required autocomplete="email" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="name" :label="__('Full name')" required autocomplete="name" />
                    <flux:input wire:model="phone" :label="__('Phone (optional)')" autocomplete="tel" />
                </div>
                <flux:input wire:model="company" :label="__('Company (optional)')" autocomplete="organization" />
            </fieldset>

            <fieldset class="space-y-4">
                <flux:heading>{{ __('Shipping address') }}</flux:heading>
                <flux:input wire:model="address_line1" :label="__('Address')" required autocomplete="address-line1" />
                <flux:input wire:model="address_line2" :label="__('Apartment, suite, dock (optional)')" autocomplete="address-line2" />
                <div class="grid gap-4 sm:grid-cols-6">
                    <flux:input wire:model="city" :label="__('City')" required class="sm:col-span-3" autocomplete="address-level2" />
                    <flux:input wire:model="state" :label="__('State')" required class="sm:col-span-1" autocomplete="address-level1" />
                    <flux:input wire:model="postal_code" :label="__('ZIP')" required class="sm:col-span-2" autocomplete="postal-code" />
                </div>
                <flux:input wire:model="country" :label="__('Country (2-letter code)')" required maxlength="2" class="sm:w-40" autocomplete="country" />
            </fieldset>

            @if ($this->shippingMethods->isNotEmpty())
                <fieldset class="space-y-4">
                    <flux:heading>{{ __('Shipping method') }}</flux:heading>
                    <flux:select wire:model.live="shipping_method_id" :label="__('How should we ship it?')" data-test="shipping-method">
                        @foreach ($this->shippingMethods as $method)
                            <flux:select.option :value="$method->id">{{ $method->label() }} ({{ $method->formattedPrice() }})</flux:select.option>
                        @endforeach
                    </flux:select>
                </fieldset>
            @endif

            @if ($paymentError)
                <flux:callout variant="danger" icon="exclamation-triangle" :heading="$paymentError" data-test="payment-error" />
            @endif

            <flux:button type="submit" variant="primary" class="w-full" icon="lock-closed" data-test="pay-button">
                <span wire:loading.remove wire:target="pay">{{ __('Pay :amount with Stripe', ['amount' => Number::currency($totals['total_cents'] / 100, 'USD')]) }}</span>
                <span wire:loading wire:target="pay">{{ __('Redirecting to Stripe…') }}</span>
            </flux:button>
        </form>

        <aside class="h-fit rounded-xl border border-zinc-200 p-6 dark:border-zinc-700 lg:col-span-2">
            <flux:heading>{{ __('Order summary') }}</flux:heading>
            <ul class="mt-4 divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                @foreach ($lines as $line)
                    <li class="flex justify-between gap-4 py-3">
                        <div>
                            <div class="font-medium">{{ $line['product']->name }}</div>
                            <div class="text-zinc-500">{{ $line['quantity'] }} &times; {{ $line['product']->packageLabel() }}</div>
                        </div>
                        <div class="font-medium">{{ Number::currency($line['total_cents'] / 100, 'USD') }}</div>
                    </li>
                @endforeach
            </ul>
            <dl class="mt-4 space-y-2 border-t border-zinc-200 pt-4 text-sm dark:border-zinc-700">
                <div class="flex justify-between">
                    <dt>{{ __('Subtotal') }}</dt>
                    <dd>{{ Number::currency($totals['subtotal_cents'] / 100, 'USD') }}</dd>
                </div>
                @if ($totals['shipping_carrier'])
                    <div class="flex justify-between" data-test="summary-shipping">
                        <dt>{{ __('Shipping') }} <span class="text-zinc-500">({{ $totals['shipping_carrier'] }}, {{ $totals['shipping_timeframe'] }})</span></dt>
                        <dd>{{ $totals['shipping_cents'] === 0 ? __('Free') : Number::currency($totals['shipping_cents'] / 100, 'USD') }}</dd>
                    </div>
                @endif
                @if ($totals['tax_cents'] > 0)
                    <div class="flex justify-between" data-test="summary-tax">
                        <dt>{{ $totals['tax_label'] }} <span class="text-zinc-500">({{ rtrim(rtrim(number_format($totals['tax_rate'], 3), '0'), '.') }}%)</span></dt>
                        <dd>{{ Number::currency($totals['tax_cents'] / 100, 'USD') }}</dd>
                    </div>
                @endif
                <div class="flex justify-between border-t border-zinc-200 pt-3 text-base font-semibold dark:border-zinc-700">
                    <dt>{{ __('Total') }}</dt>
                    <dd data-test="summary-total">{{ Number::currency($totals['total_cents'] / 100, 'USD') }}</dd>
                </div>
            </dl>
            <flux:link :href="route('cart')" class="mt-4 inline-block text-sm" wire:navigate>{{ __('Edit cart') }}</flux:link>
        </aside>
    </div>
</div>
