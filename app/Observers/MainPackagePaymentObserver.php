<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Models\Payment;

class MainPackagePaymentObserver
{
    /**
     * Handle the Payment "created" event.
     */
    public function created(Payment $payment): void
    {
        $member_id = $payment->member_id;
        $payment_id = $payment->id;
        Invoice::create([
            'member_id' => $member_id,
            'main_package_payment_id' => $payment_id
        ]);
    }

    /**
     * Handle the Payment "updated" event.
     */
    public function updated(Payment $payment): void
    {
        //
    }

    /**
     * Handle the Payment "deleted" event.
     */
    public function deleted(Payment $payment): void
    {
        //
    }

    /**
     * Handle the Payment "restored" event.
     */
    public function restored(Payment $payment): void
    {
        //
    }

    /**
     * Handle the Payment "force deleted" event.
     */
    public function forceDeleted(Payment $payment): void
    {
        //
    }
}
