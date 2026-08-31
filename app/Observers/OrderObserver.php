<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\Notifier;

class OrderObserver
{
    public function __construct(private readonly Notifier $notifier) {}

    public function created(Order $order): void
    {
        $this->notifier->orderPlaced($order);
    }

    public function updated(Order $order): void
    {
        // Staff editing a carrier or tracking number on an already-shipped
        // order is not news; the pipeline moving on is.
        if ($order->wasChanged('status')) {
            $this->notifier->orderStatusChanged($order);
        }
    }
}
