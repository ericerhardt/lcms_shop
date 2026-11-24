<?php

use App\Enums\OrderStatus;
use App\Models\Inquiry;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    /**
     * Shop-wide figures for admins, personal figures for customers.
     *
     * @return array<int, array{label: string, value: string, hint: string, icon: string}>
     */
    #[Computed]
    public function cards(): array
    {
        $monthStart = now()->startOfMonth();
        $isAdmin = auth()->user()->can('manage-shop');

        $paidOrders = fn () => Order::query()
            ->where('status', OrderStatus::Paid)
            ->unless($isAdmin, fn ($query) => $query->where('user_id', auth()->id()));

        $monthSales = (int) $paidOrders()->where('paid_at', '>=', $monthStart)->sum(DB::raw('subtotal_cents + shipping_cents'));
        $allSales = (int) $paidOrders()->sum(DB::raw('subtotal_cents + shipping_cents'));
        $monthOrders = $paidOrders()->where('paid_at', '>=', $monthStart)->count();
        $allOrders = $paidOrders()->count();

        $cards = [
            [
                'label' => $isAdmin ? __('Current Sales') : __('My Spending'),
                'value' => Number::currency($monthSales / 100, 'USD'),
                'hint' => __(':amount all time', ['amount' => Number::currency($allSales / 100, 'USD')]),
                'icon' => 'banknotes',
            ],
            [
                'label' => $isAdmin ? __('Current Orders') : __('My Orders'),
                'value' => Number::format($monthOrders),
                'hint' => __(':count all time', ['count' => Number::format($allOrders)]),
                'icon' => 'clipboard-document-list',
            ],
        ];

        if ($isAdmin) {
            $cards[] = [
                'label' => __('Volume Forms Submitted'),
                'value' => Number::format(Inquiry::where('created_at', '>=', $monthStart)->count()),
                'hint' => __(':count all time', ['count' => Number::format(Inquiry::count())]),
                'icon' => 'envelope',
            ];
        }

        return $cards;
    }
}; ?>

<section class="w-full">
    <flux:heading size="xl" level="1">{{ __('Dashboard') }}</flux:heading>
    <flux:subheading>{{ __('Figures for :month. Sales and orders count paid orders only.', ['month' => now()->format('F Y')]) }}</flux:subheading>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($this->cards as $card)
            <div wire:key="card-{{ $loop->index }}" class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700" data-test="dashboard-card">
                <div class="flex items-center justify-between">
                    <flux:text class="font-medium">{{ $card['label'] }}</flux:text>
                    <flux:icon :name="$card['icon']" class="size-5 text-zinc-400" />
                </div>
                <div class="mt-3 text-3xl font-bold">{{ $card['value'] }}</div>
                <flux:text size="sm" class="mt-1">{{ $card['hint'] }}</flux:text>
            </div>
        @endforeach
    </div>
</section>
