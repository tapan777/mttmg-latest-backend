<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Models\NonRegistreMember;

class NonRegisterMemberObserver
{
    /**
     * Handle the NonRegistreMember "created" event.
     */
    public function created(NonRegistreMember $nonRegistreMember): void
    {
        // dd($nonRegistreMember->id);
        Invoice::create([
            'non_registre_member_id' => $nonRegistreMember->id
        ]);
    }

    /**
     * Handle the NonRegistreMember "updated" event.
     */
    public function updated(NonRegistreMember $nonRegistreMember): void
    {
        //
    }

    /**
     * Handle the NonRegistreMember "deleted" event.
     */
    public function deleted(NonRegistreMember $nonRegistreMember): void
    {
        //
    }

    /**
     * Handle the NonRegistreMember "restored" event.
     */
    public function restored(NonRegistreMember $nonRegistreMember): void
    {
        //
    }

    /**
     * Handle the NonRegistreMember "force deleted" event.
     */
    public function forceDeleted(NonRegistreMember $nonRegistreMember): void
    {
        //
    }
}
