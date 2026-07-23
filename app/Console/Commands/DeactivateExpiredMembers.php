<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DeactivateExpiredMembers extends Command
{
    protected $signature   = 'app:deactivate-expired-members';
    protected $description = 'Deactivate members whose main package has expired';

    public function handle(): void
    {
        $today = Carbon::now('Asia/Kolkata')->toDateString();

        $latestPaymentIds = Payment::where('payment_type', 0)
            ->selectRaw('MAX(id) as id')
            ->groupBy('member_id')
            ->pluck('id');

        $expiredPayments = Payment::whereIn('id', $latestPaymentIds)
            ->where('package_status', 1)
            ->whereDate('end_date', '<', $today)
            ->get();

        if ($expiredPayments->isEmpty()) {
            $this->info('No expired main packages found. Nothing to deactivate.');
            return;
        }

        $deactivated = 0;

        foreach ($expiredPayments as $payment) {
            $member = Member::find($payment->member_id);

            if (!$member || (int) $member->status !== 1) {
                continue;
            }

            try {
                $member->update(['status' => 0]);
                $deactivated++;
                $this->line("  ✓ Deactivated: {$member->name} (expired {$payment->end_date})");
            } catch (\Throwable $e) {
                Log::warning('Auto-deactivate member failed', ['member_id' => $member->id, 'error' => $e->getMessage()]);
            }
        }

        $this->info("Done — Deactivated: $deactivated");
    }
}
