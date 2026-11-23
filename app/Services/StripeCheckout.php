<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal Stripe Checkout client built on Laravel's HTTP client.
 */
class StripeCheckout
{
    private const BASE_URL = 'https://api.stripe.com/v1';

    public function isConfigured(): bool
    {
        return filled(config('services.stripe.secret'));
    }

    /**
     * Create a hosted Checkout Session for the order and return its id and redirect URL.
     *
     * @return array{id: string, url: string}
     */
    public function createSession(Order $order, string $successUrl, string $cancelUrl): array
    {
        $lineItems = $order->items->map(fn ($item): array => [
            'quantity' => $item->quantity,
            'price_data' => [
                'currency' => 'usd',
                'unit_amount' => $item->unit_price_cents,
                'product_data' => [
                    'name' => "{$item->name} ({$item->sku})",
                    'description' => ucfirst($item->packaging_type).' of '.number_format($item->quantity_per_package),
                ],
            ],
        ])->all();

        $response = $this->client()->post('/checkout/sessions', [
            'mode' => 'payment',
            'customer_email' => $order->email,
            'client_reference_id' => $order->token,
            'metadata' => ['order_id' => $order->id],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items' => $lineItems,
        ]);

        if ($response->failed()) {
            throw new RuntimeException($response->json('error.message', 'Stripe could not start the checkout.'));
        }

        return ['id' => $response->json('id'), 'url' => $response->json('url')];
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveSession(string $sessionId): array
    {
        $response = $this->client()->get('/checkout/sessions/'.urlencode($sessionId));

        if ($response->failed()) {
            throw new RuntimeException($response->json('error.message', 'Stripe session could not be retrieved.'));
        }

        return $response->json();
    }

    private function client(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Stripe is not configured. Set STRIPE_SECRET in your .env file.');
        }

        return Http::asForm()
            ->baseUrl(self::BASE_URL)
            ->withToken(config('services.stripe.secret'))
            ->timeout(20);
    }
}
