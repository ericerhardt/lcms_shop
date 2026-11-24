<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Services\OrderNotifier;

class SendOrderConfirmation
{
    public function __construct(private readonly OrderNotifier $notifier) {}

    public function handle(OrderPaid $event): void
    {
        $this->notifier->sendConfirmation($event->order);
    }
}
