<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\StoreSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OrderConfirmation extends Mailable
{
    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        $settings = app(StoreSettings::class);
        $replyTo = $settings->contactEmail();

        return new Envelope(
            subject: $settings->fill($settings->get('email_subject'), $this->placeholders()),
            replyTo: $replyTo ? [new Address($replyTo, $settings->storeName())] : [],
        );
    }

    public function content(): Content
    {
        $settings = app(StoreSettings::class);

        return new Content(
            markdown: 'emails.order-confirmation',
            with: [
                'intro' => $settings->fill($settings->get('email_intro'), $this->placeholders()),
                'footer' => $settings->fill($settings->get('email_footer'), $this->placeholders()),
                'storeName' => $settings->storeName(),
            ],
        );
    }

    /**
     * @return array<string, string>
     */
    private function placeholders(): array
    {
        return [
            'store_name' => app(StoreSettings::class)->storeName(),
            'order_number' => $this->order->number(),
            'customer_name' => $this->order->name,
        ];
    }
}
