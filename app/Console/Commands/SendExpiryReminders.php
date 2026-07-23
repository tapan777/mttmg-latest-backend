<?php

namespace App\Console\Commands;

use App\Events\SendWhatsAppNotification;
use App\Models\Payment;
use App\Models\TrainerPayment;
use App\Models\TrainerPackage;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendExpiryReminders extends Command
{
    protected $signature   = 'app:send-expiry-reminders {--days=1 : Days ahead to check (1=today, 3=3 days, 7=7 days)}';
    protected $description = 'Send membership_expire_info-name WhatsApp to members expiring in N days';

    public function handle(): void
    {
        $days       = (int) $this->option('days');
        $targetDate = Carbon::now('Asia/Kolkata')->addDays($days)->toDateString();

        $this->info("Checking members expiring on: $targetDate");

        $payments = Payment::with(['member', 'package'])
            ->where('payment_type', 0)
            ->where('package_status', 1)
            ->whereDate('end_date', $targetDate)
            ->get();

        if ($payments->isEmpty()) {
            $this->info("No members expiring in $days day(s). Nothing sent.");
            return;
        }

        $sent    = 0;
        $skipped = 0;

        foreach ($payments as $p) {
            $member      = $p->member;
            $packageName = optional($p->package)->package_name ?? optional($p->package)->name ?? 'Membership';
            $expiryDate  = Carbon::parse($p->end_date)->format('d-m-Y');

            if (!$member || empty($member->phone)) {
                $skipped++;
                continue;
            }

            try {
                event(new SendWhatsAppNotification(
                    'membership_expire_info',
                    [
                        $member->name,  // {{1}} Member name
                        $expiryDate,    // {{2}} Expiry date
                        $packageName,   // {{3}} Package name
                    ],
                    [$member->phone]
                ));

                event(new SendWhatsAppNotification(
                    'renewal_reminder',
                    [
                        $member->name,  // {{1}} Member name
                        $packageName,   // {{2}} Package name
                        $expiryDate,    // {{3}} Expiry date
                    ],
                    [$member->phone]
                ));
                $sent++;
                $this->line("  ✓ Queued: {$member->name} ({$member->phone})");
            } catch (\Throwable $e) {
                Log::warning('Expiry reminder failed', ['member_id' => $member->id, 'error' => $e->getMessage()]);
                $skipped++;
            }
        }

        $this->info("Done — Sent: $sent | Skipped: $skipped");

        // expire_membership fires only for the --days=0 run (members already expired)
        if ($days === 0) {
            $this->info("Checking all already-expired members (end_date <= $targetDate)");

            $expired = Payment::with(['member', 'package'])
                ->where('payment_type', 0)
                ->where('package_status', 1)
                ->whereDate('end_date', $targetDate)
                ->get();

            $expiredSent = 0;
            foreach ($expired as $p) {
                $member      = $p->member;
                $packageName = optional($p->package)->package_name ?? optional($p->package)->name ?? 'Membership';
                $expiryDate  = Carbon::parse($p->end_date)->format('d-m-Y');

                if (!$member || empty($member->phone)) continue;

                try {
                    event(new SendWhatsAppNotification(
                        'expire_membership',
                        [
                            $member->name,  // {{1}} Member name
                            $expiryDate,    // {{2}} Expiry date
                            $packageName,   // {{3}} Package name
                        ],
                        [$member->phone]
                    ));
                    $expiredSent++;
                    $this->line("  ✓ Expired notice queued: {$member->name}");
                } catch (\Throwable $e) {
                    Log::warning('expire_membership WhatsApp failed', ['error' => $e->getMessage()]);
                }
            }
            $this->info("Expired notices — Sent: $expiredSent");
            return;
        }

        // PT due reminders
        $this->info("Checking PT due payments expiring on: $targetDate");
        $ptPayments = TrainerPayment::with(['members', 'trainer_packages'])
            ->where('payment_type', 0)
            ->where('package_status', 1)
            ->whereDate('end_date', $targetDate)
            ->get();

        $ptSent = 0;
        foreach ($ptPayments as $pt) {
            $member      = $pt->members;
            $packageName = optional($pt->trainer_packages)->name ?? 'PT Package';
            $dueDate     = Carbon::parse($pt->end_date)->format('d-m-Y');

            if (!$member || empty($member->phone)) continue;

            try {
                event(new SendWhatsAppNotification(
                    'due_reminder_pt',
                    [
                        $member->name,  // {{1}} Member name
                        $dueDate,       // {{2}} Due date
                        $packageName,   // {{3}} Account / package name
                    ],
                    [$member->phone]
                ));

                event(new SendWhatsAppNotification(
                    'pt_package_expire_reminder',
                    [
                        $packageName,   // {{1}} Package name
                        $dueDate,       // {{2}} Expiry date
                    ],
                    [$member->phone]
                ));

                $ptSent++;
                $this->line("  ✓ PT Due queued: {$member->name} ({$member->phone})");
            } catch (\Throwable $e) {
                Log::warning('PT due reminder failed', ['error' => $e->getMessage()]);
            }
        }

        $this->info("PT Done — Sent: $ptSent");
    }
}
