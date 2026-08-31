<?php

namespace App\Observers;

use App\Models\Prescription;
use App\Services\Notifier;

class PrescriptionObserver
{
    public function __construct(private readonly Notifier $notifier) {}

    public function created(Prescription $prescription): void
    {
        $this->notifier->prescriptionUploaded($prescription);
    }

    public function updated(Prescription $prescription): void
    {
        if ($prescription->wasChanged('is_verified') && $prescription->is_verified) {
            $this->notifier->prescriptionVerified($prescription);
        }
    }
}
