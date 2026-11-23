<?php

use App\Services\Cart;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function count(): int
    {
        return app(Cart::class)->count();
    }

    #[On('cart-updated')]
    public function refreshCount(): void
    {
        unset($this->count);
    }
}; ?>

<div>
    <flux:button :href="route('cart')" variant="ghost" icon="shopping-cart" size="sm" wire:navigate class="relative">
        {{ __('Cart') }}
        @if ($this->count > 0)
            <flux:badge size="sm" color="blue" class="ms-1" data-test="cart-count">{{ $this->count }}</flux:badge>
        @endif
    </flux:button>
</div>
