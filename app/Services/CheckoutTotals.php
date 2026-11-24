<?php

namespace App\Services;

use App\Models\ShippingMethod;

/**
 * Works out what an order costs once shipping and tax are added to the items.
 */
class CheckoutTotals
{
    public function __construct(private readonly StoreSettings $settings) {}

    /**
     * @return array{subtotal_cents: int, shipping_cents: int, shipping_carrier: string|null, shipping_timeframe: string|null, tax_label: string|null, tax_rate: float, tax_cents: int, total_cents: int}
     */
    public function calculate(int $subtotalCents, ?ShippingMethod $shipping): array
    {
        $shippingCents = $shipping?->price_cents ?? 0;

        $taxRate = $this->settings->taxEnabled() ? $this->settings->taxRate() : 0.0;
        $taxableCents = $subtotalCents + ($this->settings->taxOnShipping() ? $shippingCents : 0);
        $taxCents = (int) round($taxableCents * $taxRate / 100);

        return [
            'subtotal_cents' => $subtotalCents,
            'shipping_cents' => $shippingCents,
            'shipping_carrier' => $shipping?->carrier,
            'shipping_timeframe' => $shipping?->timeframe,
            'tax_label' => $taxRate > 0 ? $this->settings->taxLabel() : null,
            'tax_rate' => $taxRate,
            'tax_cents' => $taxCents,
            'total_cents' => $subtotalCents + $shippingCents + $taxCents,
        ];
    }
}
