<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\StripeWebhookVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

class StripeWebhookController extends Controller
{
    /**
     * Receive Stripe events so orders are settled even if the customer never returns to the site.
     */
    public function __invoke(Request $request, StripeWebhookVerifier $verifier): Response
    {
        if (! $verifier->isConfigured()) {
            return response('Stripe webhook secret is not configured.', 503);
        }

        try {
            $event = $verifier->constructEvent($request->getContent(), $request->header('Stripe-Signature'));
        } catch (InvalidArgumentException $e) {
            return response($e->getMessage(), 400);
        }

        /** @var array<string, mixed> $session */
        $session = $event['data']['object'] ?? [];

        match ($event['type']) {
            'checkout.session.completed', 'checkout.session.async_payment_succeeded' => $this->settle($session),
            'checkout.session.expired', 'checkout.session.async_payment_failed' => $this->cancel($session),
            default => null,
        };

        return response('ok');
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function settle(array $session): void
    {
        if (($session['payment_status'] ?? null) !== 'paid') {
            return;
        }

        $this->findOrder($session)?->markPaid($session['payment_intent'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function cancel(array $session): void
    {
        $order = $this->findOrder($session);

        if ($order?->status === OrderStatus::Pending) {
            $order->update(['status' => OrderStatus::Cancelled]);
        }
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function findOrder(array $session): ?Order
    {
        return Order::query()
            ->when(
                isset($session['id']),
                fn ($query) => $query->where('stripe_session_id', $session['id']),
                fn ($query) => $query->where('token', (string) ($session['client_reference_id'] ?? '')),
            )
            ->first();
    }
}
