<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ActivateMembersWithActivePackage extends Command
{
    protected $signature   = 'app:activate-members-with-active-package';
    protected $description = 'Activate members whose main package is currently active (not expired)';

    public function handle(): void
    {
        $today = Carbon::now('Asia/Kolkata')->toDateString();

        $latestPaymentIds = Payment::where('payment_type', 0)
            ->selectRaw('MAX(id) as id')
            ->groupBy('member_id')
            ->pluck('id');

        $activePayments = Payment::whereIn('id', $latestPaymentIds)
            ->where('package_status', 1)
            ->whereDate('end_date', '>=', $today)
            ->get();

        if ($activePayments->isEmpty()) {
            $this->info('No members with an active main package found. Nothing to activate.');
            return;
        }

        $activated = 0;

        foreach ($activePayments as $payment) {
            $member = Member::find($payment->member_id);

            if (!$member || (int) $member->status === 1) {
                continue;
            }

            try {
                $member->update(['status' => 1]);
                $activated++;
                $this->line("  ✓ Activated: {$member->name} (package valid till {$payment->end_date})");
            } catch (\Throwable $e) {
                Log::warning('Auto-activate member failed', ['member_id' => $member->id, 'error' => $e->getMessage()]);
            }
        }

        $this->info("Done — Activated: $activated");
    }
}
