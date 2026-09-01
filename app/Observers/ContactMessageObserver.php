<?php

namespace App\Observers;

use App\Models\ContactMessage;
use App\Services\Notifier;

class ContactMessageObserver
{
    public function __construct(private readonly Notifier $notifier) {}

    public function created(ContactMessage $message): void
    {
        $this->notifier->contactMessageReceived($message);
    }
}
