<?php

namespace App\Console\Commands;

use App\Events\SendWhatsAppNotification;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendDueReminders extends Command
{
    protected $signature   = 'app:send-due-reminders';
    protected $description = 'Send due_reminder_1 WhatsApp to members with pending due payments';

    public function handle(): void
    {
        $this->info('Checking members with pending dues...');

        $payments = Payment::with('member')
            ->where('payment_type', 0)
            ->where('package_status', 1)
            ->where('due', '>', 0)
            ->get();

        if ($payments->isEmpty()) {
            $this->info('No members with pending dues. Nothing sent.');
            return;
        }

        $sent    = 0;
        $skipped = 0;

        foreach ($payments as $p) {
            $member = $p->member;

            if (!$member || empty($member->phone)) {
                $skipped++;
                continue;
            }

            try {
                event(new SendWhatsAppNotification(
                    'due_reminder_1',
                    [
                        $member->name,  // {{1}} Member name
                        $p->due,        // {{2}} Pending amount
                    ],
                    [$member->phone]
                ));
                $sent++;
                $this->line("  ✓ Queued: {$member->name} — due ₹{$p->due}");
            } catch (\Throwable $e) {
                Log::warning('due_reminder_1 WhatsApp failed', [
                    'member_id' => $member->id,
                    'error'     => $e->getMessage(),
                ]);
                $skipped++;
            }
        }

        $this->info("Done — Sent: $sent | Skipped: $skipped");
    }
}
