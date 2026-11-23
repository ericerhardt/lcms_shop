<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Password;

/**
 * Tells a new user their account exists and lets them choose their own password.
 */
class WelcomeNewUser extends Notification
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => Password::broker()->createToken($notifiable),
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject(__('Welcome to :app', ['app' => config('app.name')]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('An account has been created for you on :app.', ['app' => config('app.name')]))
            ->line(__('Your email address is :email. Use the button below to choose your password and sign in.', ['email' => $notifiable->email]))
            ->action(__('Set your password'), $url)
            ->line(__('This link expires in :minutes minutes. If it has expired, use "Forgot your password?" on the login page to get a new one.', ['minutes' => $minutes]));
    }
}
