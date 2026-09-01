<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * The "confirm this is your address" email, sent on registration by the
 * framework's Registered listener and again whenever someone asks for a fresh
 * link from the verification notice page.
 *
 * Same reason for existing as ResetPasswordNotification: our own template
 * rather than Laravel's generic markdown layout.
 */
class VerifyEmailNotification extends Notification
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $expiresIn = config('auth.verification.expire', 60);

        // A signed, expiring URL: the link works without the recipient being
        // signed in on that device, and can't be edited to verify a different
        // account. The hash pins it to the address it was mailed to, so
        // changing the address afterwards invalidates any link still in flight.
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes($expiresIn),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        return (new MailMessage)
            ->subject(__('Confirm your email address'))
            ->view('emails.verify-email', [
                'user' => $notifiable,
                'url' => $url,
                'expiresIn' => $expiresIn,
            ]);
    }
}
