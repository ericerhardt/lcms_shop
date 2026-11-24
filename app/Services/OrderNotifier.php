<?php

namespace App\Services;

use App\Mail\OrderConfirmation;
use App\Models\Order;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Emails the order confirmation to the customer, with a copy to the company.
 */
class OrderNotifier
{
    public function __construct(private readonly StoreSettings $settings) {}

    /**
     * Send the confirmation. A failure is reported but never thrown, so it cannot break payment handling.
     *
     * @param  bool  $force  send again even if it was already sent
     */
    public function sendConfirmation(Order $order, bool $force = false): bool
    {
        if (! $force && $order->confirmation_sent_at !== null) {
            return true;
        }

        $customer = Str::lower($order->email);
        $copies = array_values(array_diff($this->settings->copyRecipients(), [$customer]));

        try {
            $message = Mail::to($order->email);

            if ($copies !== []) {
                $message->cc($copies);
            }

            $message->send(new OrderConfirmation($order->loadMissing('items')));
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        $order->forceFill(['confirmation_sent_at' => now()])->save();

        return true;
    }
}
