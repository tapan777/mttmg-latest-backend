<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeePunchLog;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FixAttendanceWorkHours extends Command
{
    protected $signature = 'app:fix-attendance-work-hours {--dry-run : Preview changes without writing to the database} {--all : Recompute every employee attendance row, not just missing/zero ones}';

    protected $description = 'Recompute work_hours for Attendance rows from actual punch sessions (with slot-end fallback only for genuinely unclosed sessions)';

    public function handle(): void
    {
        $dryRun = (bool) $this->option('dry-run');
        $all    = (bool) $this->option('all');

        $query = Attendance::whereNotNull('check_in')->whereNotNull('check_out');
        if (!$all) {
            $query->where(function ($q) {
                $q->whereNull('work_hours')->orWhere('work_hours', 0);
            });
        }
        $rows = $query->get();

        if ($rows->isEmpty()) {
            $this->info('No matching attendance rows found.');
            return;
        }

        $this->info("Found {$rows->count()} row(s) to check." . ($dryRun ? ' (dry run — no changes will be made)' : ''));

        $fixed = 0;
        $unchanged = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $employee = Employee::find($row->user_id);

            $dayPunches = $employee
                ? EmployeePunchLog::where('employee_id', $row->user_id)
                    ->where('punch_date', $row->date)
                    ->orderBy('punch_time')
                    ->get()
                : collect();

            $workHours = null;
            $source = null;

            if ($employee && $dayPunches->isNotEmpty()) {
                $workHours = $this->computeWorkedHours($employee, $dayPunches, $row->date);
                if ($workHours !== null) {
                    $source = 'sessions';
                }
            }

            // Fallback — only safe for a single "in" punch that day (one
            // session): the raw check_in/check_out span. For multi-session
            // days with no usable punch data we deliberately leave it alone
            // rather than guess and risk re-inflating the original bug.
            $inPunchCount = $dayPunches->where('punch_type', 'in')->count();
            if ($workHours === null && $inPunchCount <= 1) {
                $workHours = round(
                    Carbon::parse($row->check_in)->diffInMinutes(Carbon::parse($row->check_out)) / 60,
                    2
                );
                $source = 'span';
            }

            if ($workHours === null || $workHours <= 0) {
                $this->line("id={$row->id} user_id={$row->user_id} date={$row->date}: SKIPPED — {$inPunchCount} check-in(s), no reliable source");
                $skipped++;
                continue;
            }

            $current = $row->work_hours !== null ? (float) $row->work_hours : null;
            if ($current !== null && abs($current - $workHours) < 0.01) {
                $unchanged++;
                continue;
            }

            $this->line("id={$row->id} user_id={$row->user_id} date={$row->date}: work_hours {$row->work_hours} -> {$workHours} (via {$source})");

            if (!$dryRun) {
                $row->work_hours = $workHours;
                $row->save();
            }

            $fixed++;
        }

        $this->info(
            ($dryRun ? '[DRY RUN] Would fix ' : 'Fixed ') . "{$fixed} row(s), "
            . "{$unchanged} already correct, {$skipped} skipped (review manually)."
        );
    }

    /**
     * Sum worked minutes across the day, one session per slot. For each slot,
     * takes the first "in" and the last "out" within that slot's window (or
     * the slot's end if never scanned out) — this collapses any number of
     * duplicate/retry punches inside one slot into a single session, so a
     * slot can never be credited more than once no matter how noisy the raw
     * punches are (e.g. two "in" scans an hour apart from the same arrival,
     * which a sequential open/close walk would wrongly treat as two separate
     * sessions and credit twice). Also caps credit symmetrically at the
     * slot's official start/end — no early-arrival or overtime credit.
     * Returns null when there's no session to credit at all.
     */
    private function computeWorkedHours(Employee $employee, $dayPunches, string $date): ?float
    {
        $slots = array_values(array_filter([$employee->morning_slot, $employee->evening_slot]));
        $slotRanges = array_values(array_filter(array_map(
            fn ($s) => $this->parseSlotRange($s, $date),
            $slots
        )));

        $totalMinutes = 0;

        foreach ($slotRanges as [$start, $end]) {
            $windowStart = $start->copy()->subMinutes(30);
            $slotPunches = $dayPunches->filter(function ($p) use ($date, $windowStart, $end) {
                $t = Carbon::parse("$date {$p->punch_time}");
                return $t->between($windowStart, $end);
            });
            if ($slotPunches->isEmpty()) {
                continue;
            }

            $firstIn = $slotPunches->firstWhere('punch_type', 'in');
            if (!$firstIn) {
                continue;
            }
            // An early check-in (within the 30-min grace before slot start) is
            // valid attendance for the slot, but doesn't earn extra paid minutes
            // — credit from the slot's official start, never earlier.
            $inTime = Carbon::parse("$date {$firstIn->punch_time}");
            $creditedStart = $inTime->lt($start) ? $start : $inTime;

            $lastOut = $slotPunches->where('punch_type', 'out')->sortByDesc('punch_time')->first();
            $endTime = $lastOut ? Carbon::parse("$date {$lastOut->punch_time}") : $end;
            // Symmetric with the early-arrival cap above — staying late past the
            // slot's official end doesn't earn overtime, so cap credit there too.
            if ($endTime->gt($end)) {
                $endTime = $end;
            }
            if ($endTime->lt($creditedStart)) {
                $endTime = $end;
            }

            $totalMinutes += max(0, $creditedStart->diffInMinutes($endTime));
        }

        return $totalMinutes > 0 ? round($totalMinutes / 60, 2) : null;
    }

    /**
     * Mirrors AutoCheckoutEmployees::parseSlotRange — parses a slot string like
     * "6:00 AM - 2:00 PM" into [startCarbon, endCarbon] for the given date.
     */
    private function parseSlotRange(string $slot, string $date): ?array
    {
        $slot = trim($slot);
        if ($slot === '') {
            return null;
        }

        $parts = preg_split('/\s*[-–]\s*(?=\d)|(\s+to\s+)/i', $slot, 2);
        if (!$parts || count($parts) < 2) {
            return null;
        }

        $startStr = trim($parts[0]);
        $endStr   = trim($parts[1]);

        try {
            $start = Carbon::parse("$date $startStr");
            $end   = Carbon::parse("$date $endStr");

            if ($end->lt($start)) {
                $end->addDay();
            }

            return [$start, $end];
        } catch (\Throwable) {
            return null;
        }
    }
}
