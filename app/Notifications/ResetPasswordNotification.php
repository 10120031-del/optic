<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The "you asked to reset your password" email.
 *
 * Laravel can send this on its own, but its built-in notification renders the
 * generic markdown mail layout. This one carries the same plain-table styling
 * as the shop's other email (see resources/views/emails/) so a reset link
 * doesn't look like it came from somewhere else.
 *
 * Deliberately not queued — see User::sendPasswordResetNotification().
 */
class ResetPasswordNotification extends Notification
{
    public function __construct(public readonly string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // The address rides along in the query string because the reset form
        // has to post back both halves of the pair, and the token alone
        // doesn't say whose it is.
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject(__('Reset your :app password', ['app' => config('app.name')]))
            ->view('emails.password-reset', [
                'user' => $notifiable,
                'url' => $url,
                'expiresIn' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
            ]);
    }
}
