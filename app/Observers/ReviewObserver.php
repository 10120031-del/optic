<?php

namespace App\Observers;

use App\Models\Review;
use App\Services\Notifier;

class ReviewObserver
{
    public function __construct(private readonly Notifier $notifier) {}

    public function created(Review $review): void
    {
        $this->notifier->reviewSubmitted($review);
    }

    public function updated(Review $review): void
    {
        // Only the moderation gate opening is worth a word — and only the
        // once, so an already-approved review edited later stays quiet.
        if ($review->wasChanged('is_approved') && $review->is_approved) {
            $this->notifier->reviewApproved($review);
        }
    }
}
