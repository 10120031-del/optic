<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * One row in someone's inbox.
 *
 * Every event in the shop — an order placed, a status moved on, a review
 * approved, stock running out — is delivered as one of these. Rather than a
 * class per event, the copy and the link are passed in by
 * App\Services\Notifier, which is the single place to read what the shop
 * notifies about and who receives it.
 *
 * Delivery is the `database` channel only, and deliberately *not* queued:
 * the row is written in the same request that caused it, so the bell is
 * accurate the moment the customer's page reloads, with no worker to keep
 * running. Adding e-mail later is a one-line change to via() — see the
 * note there.
 */
class InboxNotification extends Notification
{
    /**
     * @param  string  $event  Stable machine key, e.g. 'order.status'. Grouping/filtering hangs off this, not off the copy.
     * @param  string  $title  One-line headline shown in bold in the inbox.
     * @param  string|null  $body  Optional supporting detail.
     * @param  string|null  $url  Relative path to deep-link to (see Notifier — always built with absolute: false).
     * @param  string  $level  info | success | warn | danger — picks the badge colour.
     */
    public function __construct(
        public readonly string $event,
        public readonly string $title,
        public readonly ?string $body = null,
        public readonly ?string $url = null,
        public readonly string $level = 'info',
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Add 'mail' here (and a toMail() method) to also e-mail these.
        // Do that behind ShouldQueue — MAIL_MAILER points at a real SMTP
        // host in production and a timeout there would fail the checkout
        // request that triggered it.
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event' => $this->event,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'level' => $this->level,
        ];
    }
}
