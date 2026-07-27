<?php

namespace App\Console\Commands;

use App\Http\Controllers\AdmsController;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncDeviceYearlyMembership extends Command
{
    protected $signature = 'app:sync-device-yearly-membership';

    protected $description = 'Remove members from biometric device 5 days after their yearly membership expires';

    public function handle(): void
    {
        $graceCutoff = Carbon::today()->subDays(5)->toDateString(); // remove only after 5 days past expiry
        $sn    = config('zkteco.sn', 'HKQ8241900193');
        $cmdId = time();

        // Members currently on device whose latest yearly membership expired more than 5 days ago
        $expiredMembers = Member::where('on_device', 1)
            ->whereRaw('(
                SELECT end_date FROM yearly_packages
                WHERE yearly_packages.member_id = members.id
                ORDER BY yearly_packages.id DESC
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
            Log::info("Device sync: removed member #{$member->id} ({$member->name}) - yearly membership expired");
        }

        $removed = $expiredMembers->count();

        if ($removed > 0) {
            $this->info("Yearly membership device sync: removed={$removed}");
        }
    }
}
