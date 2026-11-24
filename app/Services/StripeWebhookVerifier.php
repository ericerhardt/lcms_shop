<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Verifies the signature Stripe puts on every webhook request.
 */
class StripeWebhookVerifier
{
    /**
     * Seconds a signed request stays valid, guarding against replayed requests.
     */
    private const TOLERANCE = 300;

    public function isConfigured(): bool
    {
        return filled(config('services.stripe.webhook_secret'));
    }

    /**
     * Check the Stripe-Signature header and return the decoded event.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException when the signature, timestamp or payload is not valid
     */
    public function constructEvent(string $payload, ?string $signatureHeader): array
    {
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', (string) $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if ($key === 't') {
                $timestamp = $value;
            } elseif ($key === 'v1' && $value !== null) {
                $signatures[] = $value;
            }
        }

        if (! ctype_digit((string) $timestamp) || $signatures === []) {
            throw new InvalidArgumentException('Missing or malformed Stripe signature.');
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", (string) config('services.stripe.webhook_secret'));

        $matches = array_filter($signatures, fn (string $signature): bool => hash_equals($expected, $signature));

        if ($matches === []) {
            throw new InvalidArgumentException('Stripe signature does not match.');
        }

        if (abs(now()->timestamp - (int) $timestamp) > self::TOLERANCE) {
            throw new InvalidArgumentException('Stripe signature is too old.');
        }

        $event = json_decode($payload, true);

        if (! is_array($event) || ! isset($event['type'])) {
            throw new InvalidArgumentException('Stripe payload is not a valid event.');
        }

        return $event;
    }
}
