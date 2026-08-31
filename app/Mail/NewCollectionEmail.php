<?php

namespace App\Mail;

use App\Models\Collection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The "new collection has dropped" blast.
 *
 * Queued (unlike PromotionEmail, which is queued at the call site) because
 * announcing is a single admin request that fans out to the whole customer
 * list — MAIL_MAILER points at real SMTP in production and a slow host
 * there must not hold up the owner's browser or fail the announcement.
 */
class NewCollectionEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Collection $collection)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('New collection: :name', ['name' => $this->collection->name]),
        );
    }

    public function content(): Content
    {
        // Products are resolved here rather than in the view so the queued
        // job does the querying, and the two catalogues arrive already
        // merged in the order the owner arranged them.
        $this->collection->loadMissing(['frames.primaryImage', 'contactLenses']);

        return new Content(view: 'emails.collection', with: [
            'collection' => $this->collection,
            'products' => $this->collection->frames
                ->concat($this->collection->contactLenses)
                ->take(6),
        ]);
    }
}
