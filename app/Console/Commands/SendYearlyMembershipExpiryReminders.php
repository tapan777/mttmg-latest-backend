<?php

namespace App\Console\Commands;

use App\Events\SendEmailNotification;
use App\Mail\YearlyMembershipExpiryMail;
use App\Models\YearlyPackage;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendYearlyMembershipExpiryReminders extends Command
{
    protected $signature   = 'app:send-yearly-expiry-reminders {--days=7 : Days before expiry to notify (7, 4, 2), or 0 for already expired}';
    protected $description = 'Email members about upcoming or already-expired yearly membership (soft reminder only, never blocks payments)';

    public function handle(): void
    {
        $days       = (int) $this->option('days');
        $targetDate = Carbon::now('Asia/Kolkata')->addDays($days)->toDateString();

        $this->info("Checking yearly memberships " . ($days === 0 ? "already expired on: $targetDate" : "expiring on: $targetDate"));

        $latestYearlyIds = YearlyPackage::selectRaw('MAX(id) as id')
            ->groupBy('member_id')
            ->pluck('id');

        $yearlyPackages = YearlyPackage::with('members')
            ->whereIn('id', $latestYearlyIds)
            ->whereDate('end_date', $targetDate)
            ->get();

        if ($yearlyPackages->isEmpty()) {
            $this->info('No members found. Nothing sent.');
            return;
        }

        $sent    = 0;
        $skipped = 0;

        foreach ($yearlyPackages as $yearlyPackage) {
            $member = $yearlyPackage->members;

            if (!$member || empty($member->email)) {
                $skipped++;
                continue;
            }

            try {
                event(new SendEmailNotification(
                    new YearlyMembershipExpiryMail(
                        $member->name,
                        Carbon::parse($yearlyPackage->end_date)->format('d-m-Y'),
                        $days === 0,
                        $days
                    ),
                    $member->email
                ));
                $sent++;
                $this->line("  ✓ Queued: {$member->name} ({$member->email})");
            } catch (\Throwable $e) {
                Log::warning('Yearly expiry reminder email failed', ['member_id' => $member->id, 'error' => $e->getMessage()]);
                $skipped++;
            }
        }

        $this->info("Done — Sent: $sent | Skipped: $skipped");
    }
}
