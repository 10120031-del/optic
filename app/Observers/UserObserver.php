<?php

namespace App\Observers;

use App\Models\User;
use App\Services\Notifier;

class UserObserver
{
    public function __construct(private readonly Notifier $notifier) {}

    public function created(User $user): void
    {
        // A staff account is created by hand (see CreateAdminUser) — there is
        // no shopper to welcome and nothing for the owner to be told about.
        if ($user->isAdmin()) {
            return;
        }

        $this->notifier->customerRegistered($user);
    }
}
