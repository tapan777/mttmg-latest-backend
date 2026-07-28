<?php

namespace App\Console\Commands;

use App\Http\Controllers\AdmsController;
use App\Http\Controllers\NonRegistreMemberController;
use App\Models\NonRegistreMember;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupExpiredNonRegistreMembers extends Command
{
    protected $signature = 'app:cleanup-expired-non-registre-members';

    protected $description = 'Remove expired non-registered (walk-in) members from the biometric device and delete their record, immediately after expiry (no grace period)';

    public function handle(): void
    {
        $today = Carbon::today()->toDateString();
        $sn    = config('zkteco.sn', 'HKQ8241900193');
        $cmdId = time();

        $expiredMembers = NonRegistreMember::whereNotNull('end_date')
            ->where('end_date', '<', $today)
            ->get();

        foreach ($expiredMembers as $member) {
            if ($member->on_device) {
                $id  = $cmdId++;
                $pin = NonRegistreMemberController::DEVICE_PIN_OFFSET + $member->id;
                AdmsController::queueCommand(
                    $sn,
                    $id,
                    "C:{$id}:DATA DELETE USERINFO\tPIN={$pin}"
                );
            }
            Log::info("Non-registered member cleanup: removed expired member #{$member->id} ({$member->name})");
            $member->delete();
        }

        $removed = $expiredMembers->count();
        if ($removed > 0) {
            $this->info("Non-registered member cleanup: removed={$removed}");
        }
    }
}
