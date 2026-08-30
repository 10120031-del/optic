<?php

namespace App\Mail;

use App\Models\PromotionCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PromotionEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PromotionCampaign $campaign)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->campaign->subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.promotion', with: ['campaign' => $this->campaign]);
    }
}
