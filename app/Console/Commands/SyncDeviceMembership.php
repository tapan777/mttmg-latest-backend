<?php

namespace App\Console\Commands;

use App\Http\Controllers\AdmsController;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncDeviceMembership extends Command
{
    protected $signature = 'app:sync-device-membership';

    protected $description = 'Remove expired members from biometric device; re-add renewed members';

    public function handle(): void
    {
        $today       = Carbon::today()->toDateString();
        $graceCutoff = Carbon::today()->subDays(5)->toDateString(); // remove only after 5 days past expiry
        $sn    = config('zkteco.sn', 'HKQ8241900193');
        $cmdId = time();

        // Members currently on device whose latest main-package expired more than 5 days ago
        // (matched by card_number rather than on_device alone, since on_device can drift out of
        // sync with the real device state — e.g. a card added via member edit)
        $expiredMembers = Member::where(function ($q) {
                $q->where('on_device', 1)
                    ->orWhere(function ($q2) {
                        $q2->whereNotNull('card_number')->where('card_number', '!=', '');
                    });
            })
            ->whereRaw('(
                SELECT end_date FROM payments
                WHERE payments.member_id = members.id
                  AND payments.payment_type = 0
                ORDER BY created_at DESC
                LIMIT 1
            ) < ?', [$graceCutoff])
            ->get();

        foreach ($expiredMembers as $member) {
            $id = $cmdId++;
            AdmsController::queueCommand(
                $sn,
                $id,
                "C:{$id}:DATA DELETE USERINFO\tPIN={$member->id}"
            );
            $member->update(['on_device' => 0]);
            Log::info("Device sync: removed expired member #{$member->id} ({$member->name})");
        }

        // Members not on device whose latest main-package is still active
        $renewedMembers = Member::where('on_device', 0)
            ->whereRaw('(
                SELECT end_date FROM payments
                WHERE payments.member_id = members.id
                  AND payments.payment_type = 0
                ORDER BY created_at DESC
                LIMIT 1
            ) >= ?', [$today])
            ->get();

        foreach ($renewedMembers as $member) {
            $id       = $cmdId++;
            $safeName = str_replace(' ', '_', $member->name);
            $card     = $member->card_number ?? '0';
            AdmsController::queueCommand(
                $sn,
                $id,
                "C:{$id}:DATA UPDATE USERINFO\tPIN={$member->id}\tName={$safeName}\tCard={$card}\tPri=0"
            );
            $member->update(['on_device' => 1]);
            Log::info("Device sync: re-added renewed member #{$member->id} ({$member->name})");
        }

        $removed  = $expiredMembers->count();
        $readded  = $renewedMembers->count();

        if ($removed > 0 || $readded > 0) {
            $this->info("Device sync: removed={$removed}, re-added={$readded}");
        }
    }
}
