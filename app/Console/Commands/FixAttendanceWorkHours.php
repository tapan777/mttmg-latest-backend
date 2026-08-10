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
     * Walk the day's punches in order and sum each session's real duration.
     * A session with a matching "out" punch uses the actual diff. A session
     * left open (no "out" — e.g. AutoCheckoutEmployees never got a chance to
     * close it in the historical data, or the employee simply forgot to
     * swipe out) is only credited up to the end of whichever slot its "in"
     * time falls into — never a flat full-slot credit just because *some*
     * check-in exists somewhere in a broad window. Returns null when there's
     * no session to credit at all.
     */
    private function computeWorkedHours(Employee $employee, $dayPunches, string $date): ?float
    {
        $slots = array_values(array_filter([$employee->morning_slot, $employee->evening_slot]));
        $slotRanges = array_values(array_filter(array_map(
            fn ($s) => $this->parseSlotRange($s, $date),
            $slots
        )));

        $totalMinutes = 0;
        $openIn = null;

        $closeOpenSession = function (?Carbon $atTime = null) use (&$openIn, &$totalMinutes, $slotRanges) {
            if ($openIn === null) {
                return;
            }
            // An early check-in (within the 30-min grace before slot start) is
            // valid attendance for the slot, but doesn't earn extra paid minutes
            // — credit from the slot's official start, never earlier.
            $creditedStart = $openIn;
            foreach ($slotRanges as [$start, $end]) {
                if ($openIn->between($start->copy()->subMinutes(30), $end) && $openIn->lt($start)) {
                    $creditedStart = $start;
                    break;
                }
            }
            if ($atTime !== null) {
                $totalMinutes += max(0, $creditedStart->diffInMinutes($atTime));
            } else {
                // No closing "out" punch — only credit up to the end of the
                // slot this check-in belongs to (mirrors what the slot-end
                // auto-checkout would have done), never a blanket full day.
                foreach ($slotRanges as [$start, $end]) {
                    $windowStart = $start->copy()->subMinutes(30);
                    if ($openIn->between($windowStart, $end)) {
                        $totalMinutes += max(0, $creditedStart->diffInMinutes($end));
                        break;
                    }
                }
            }
            $openIn = null;
        };

        foreach ($dayPunches as $p) {
            $time = Carbon::parse("$date {$p->punch_time}");
            if ($p->punch_type === 'in') {
                // A duplicate/double-scan "in" arriving seconds or a couple of
                // minutes after the currently-open one is a device glitch, not
                // a new session — closing-and-reopening for it would credit a
                // whole extra slot for a tap that lasted a few seconds. Ignore it.
                if ($openIn !== null && $openIn->diffInMinutes($time) < 5) {
                    continue;
                }
                // A genuine second "in" without a prior "out" — close the
                // previous open session using the slot-end estimate before
                // starting the new one, instead of silently discarding it.
                $closeOpenSession();
                $openIn = $time;
            } elseif ($p->punch_type === 'out' && $openIn) {
                $closeOpenSession($time);
            }
        }
        // Any session still open at the end of the day's punches.
        $closeOpenSession();

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
