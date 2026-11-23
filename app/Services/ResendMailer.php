<?php

namespace App\Services;

use App\Models\Inquiry;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Sends email through the Resend HTTP API using Laravel's HTTP client.
 */
class ResendMailer
{
    private const ENDPOINT = 'https://api.resend.com/emails';

    public function isConfigured(): bool
    {
        return filled(config('services.resend.key'))
            && filled(config('mail.from.address'))
            && $this->recipients('to') !== [];
    }

    /**
     * Email the sales team about a new volume pricing request.
     */
    public function sendInquiry(Inquiry $inquiry): void
    {
        $name = config('mail.from.name');
        $from = config('mail.from.address');
        $company = Str::of($inquiry->company)->replaceMatches('/\s+/', ' ')->limit(80);

        $payload = [
            'from' => "{$name} <{$from}>",
            'to' => $this->recipients('to'),
            'reply_to' => $inquiry->email,
            'subject' => "Volume pricing request {$inquiry->reference()} - {$company}",
            'text' => $inquiry->emailBody(),
        ];

        if ($this->recipients('cc') !== []) {
            $payload['cc'] = $this->recipients('cc');
        }

        $response = Http::withToken(config('services.resend.key'))
            ->acceptJson()
            ->timeout(15)
            ->post(self::ENDPOINT, $payload);

        if ($response->failed()) {
            throw new RuntimeException('Resend rejected the email: '.$response->json('message', $response->status()));
        }
    }

    /**
     * @return array<int, string>
     */
    private function recipients(string $key): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) config("services.volume_pricing.{$key}")))));
    }
}
