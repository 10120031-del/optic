<?php

namespace App\Observers;

use App\Models\OrderReturn;
use App\Services\Notifier;

class OrderReturnObserver
{
    public function __construct(private readonly Notifier $notifier) {}

    public function created(OrderReturn $return): void
    {
        $this->notifier->returnRequested($return);
    }

    public function updated(OrderReturn $return): void
    {
        // Staff notes and refund amounts get edited while a case is worked;
        // the customer only wants to hear when the decision moves.
        if ($return->wasChanged('status')) {
            $this->notifier->returnStatusChanged($return);
        }
    }
}
