<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Models\TrainerPayment;

class TrainerPackagePaymentObserver
{
    /**
     * Handle the TrainerPayment "created" event.
     */
    public function created(TrainerPayment $trainerPayment): void
    {
        $member_id = $trainerPayment->member_id;
        $trainer_payment_id = $trainerPayment->id;
        Invoice::create([
            'member_id' => $member_id,
            'trainer_package_payment_id' => $trainer_payment_id
        ]);
    }

    /**
     * Handle the TrainerPayment "updated" event.
     */
    public function updated(TrainerPayment $trainerPayment): void
    {
        //
    }

    /**
     * Handle the TrainerPayment "deleted" event.
     */
    public function deleted(TrainerPayment $trainerPayment): void
    {
        //
    }

    /**
     * Handle the TrainerPayment "restored" event.
     */
    public function restored(TrainerPayment $trainerPayment): void
    {
        //
    }

    /**
     * Handle the TrainerPayment "force deleted" event.
     */
    public function forceDeleted(TrainerPayment $trainerPayment): void
    {
        //
    }
}
