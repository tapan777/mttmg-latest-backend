<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Models\YearlyPackage;

class YearlyPackagePaymentObserver
{
    /**
     * Handle the YearlyPackage "created" event.
     */
    public function created(YearlyPackage $yearlyPackage): void
    {
        $member_id = $yearlyPackage->member_id;
        $yearly_package_payment_id = $yearlyPackage->id;
        Invoice::create([
            'member_id' => $member_id,
            'yearly_package_payment_id' => $yearly_package_payment_id
        ]);
    }

    /**
     * Handle the YearlyPackage "updated" event.
     */
    public function updated(YearlyPackage $yearlyPackage): void
    {
        //
    }

    /**
     * Handle the YearlyPackage "deleted" event.
     */
    public function deleted(YearlyPackage $yearlyPackage): void
    {
        //
    }

    /**
     * Handle the YearlyPackage "restored" event.
     */
    public function restored(YearlyPackage $yearlyPackage): void
    {
        //
    }

    /**
     * Handle the YearlyPackage "force deleted" event.
     */
    public function forceDeleted(YearlyPackage $yearlyPackage): void
    {
        //
    }
}
