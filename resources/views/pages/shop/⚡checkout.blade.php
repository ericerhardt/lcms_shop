<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Cart;
use App\Services\StripeCheckout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
    }

    /**
     * Create the order from the cart and send the customer to Stripe to pay.
     */
    public function pay(Cart $cart, StripeCheckout $stripe): void
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

        $order = DB::transaction(function () use ($validated, $lines): Order {
            $order = Order::create([
                ...$validated,
                'country' => strtoupper($validated['country']),
                'user_id' => Auth::id(),
                'status' => OrderStatus::Pending,
                'subtotal_cents' => $lines->sum('total_cents'),
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
        $cart = app(Cart::class);

        return ['lines' => $cart->lines(), 'subtotal' => $cart->formattedSubtotal()];
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

            @if ($paymentError)
                <flux:callout variant="danger" icon="exclamation-triangle" :heading="$paymentError" data-test="payment-error" />
            @endif

            <flux:button type="submit" variant="primary" class="w-full" icon="lock-closed" data-test="pay-button">
                <span wire:loading.remove wire:target="pay">{{ __('Pay :amount with Stripe', ['amount' => $subtotal]) }}</span>
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
            <div class="mt-4 flex justify-between border-t border-zinc-200 pt-4 font-semibold dark:border-zinc-700">
                <span>{{ __('Total') }}</span>
                <span>{{ $subtotal }}</span>
            </div>
            <flux:link :href="route('cart')" class="mt-4 inline-block text-sm" wire:navigate>{{ __('Edit cart') }}</flux:link>
        </aside>
    </div>
</div>
