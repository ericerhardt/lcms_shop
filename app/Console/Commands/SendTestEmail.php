<?php

namespace App\Console\Commands;

use App\Services\ResendMailer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('shop:test-email')]
#[Description('Send a test email to the volume pricing recipients to confirm Resend is working')]
class SendTestEmail extends Command
{
    public function handle(ResendMailer $mailer): int
    {
        if (! $mailer->isConfigured()) {
            $this->error('Email is not configured. Set RESEND_API_KEY, MAIL_FROM_ADDRESS and VOLUME_PRICING_TO (then run php artisan config:clear).');

            return self::FAILURE;
        }

        try {
            $sent = $mailer->sendTest();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Test email accepted by Resend.');
        $this->line('From: '.$sent['from']);
        $this->line('To:   '.implode(', ', $sent['to']));
        $this->line('CC:   '.($sent['cc'] === [] ? '(none)' : implode(', ', $sent['cc'])));

        return self::SUCCESS;
    }
}
