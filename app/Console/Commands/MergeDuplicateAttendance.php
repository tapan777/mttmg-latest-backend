<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeePunchLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MergeDuplicateAttendance extends Command
{
    protected $signature = 'app:merge-duplicate-attendance {--dry-run : Preview changes without writing to the database}';

    protected $description = 'Merge duplicate split-shift Attendance rows (same user + date) into one row per day';

    public function handle(): void
    {
        $dryRun = (bool) $this->option('dry-run');

        $duplicateGroups = Attendance::select('user_id', 'date', DB::raw('COUNT(*) as cnt'))
            ->groupBy('user_id', 'date')
            ->having('cnt', '>', 1)
            ->get();

        if ($duplicateGroups->isEmpty()) {
            $this->info('No duplicate attendance rows found.');
            return;
        }

        $this->info("Found {$duplicateGroups->count()} user/date pairs with duplicate rows." . ($dryRun ? ' (dry run — no changes will be made)' : ''));

        $mergedCount = 0;
        $deletedCount = 0;

        foreach ($duplicateGroups as $group) {
            $rows = Attendance::where('user_id', $group->user_id)
                ->where('date', $group->date)
                ->orderBy('id')
                ->get();

            $earliestCheckIn = $rows->pluck('check_in')->filter()->sort()->first();
            $latestCheckOut  = $rows->pluck('check_out')->filter()->sort()->last();

            $employee   = Employee::find($group->user_id);
            $workHours  = null;

            $dayPunches = $employee
                ? EmployeePunchLog::where('employee_id', $group->user_id)
                    ->where('punch_date', $group->date)
                    ->orderBy('punch_time')
                    ->get()
                : collect();

            // Priority 1: one session per slot — first "in" to last "out" within
            // that slot's window (or slot end if never scanned out), capped at
            // the slot's official start/end. Same algorithm as
            // AutoCheckoutEmployees::computeWorkedHoursAcrossSlots, so a
            // consolidated row's hours match what any other recompute would give.
            if ($employee && $dayPunches->isNotEmpty()) {
                $slotRanges = array_values(array_filter(array_map(
                    fn ($s) => $this->parseSlotRange($s, $group->date),
                    [$employee->morning_slot, $employee->evening_slot]
                )));
                if (!empty($slotRanges)) {
                    $computed = $this->computeWorkedHoursAcrossSlots($group->user_id, $group->date, $slotRanges);
                    if ($computed > 0) {
                        $workHours = $computed;
                    }
                }
            }

            // Priority 2: one of the duplicate rows may already carry a correct
            // work_hours value set directly by AutoCheckoutEmployees.
            if ($workHours === null) {
                $existingWorkHours = $rows->pluck('work_hours')->filter(fn ($v) => $v !== null && (float) $v > 0)->sort()->last();
                if ($existingWorkHours !== null) {
                    $workHours = (float) $existingWorkHours;
                }
            }

            // Priority 3: raw check_in/check_out span — ONLY safe for a single
            // "in" punch that day; otherwise the span spans across the gap
            // between shifts and re-inflates the exact bug being fixed here.
            $inPunchCount = $dayPunches->where('punch_type', 'in')->count();
            if ($workHours === null && $inPunchCount <= 1 && $earliestCheckIn && $latestCheckOut) {
                $workHours = round(Carbon::parse($earliestCheckIn)->diffInMinutes(Carbon::parse($latestCheckOut)) / 60, 2);
            }

            $keepRow = $rows->first();
            $extraRowIds = $rows->slice(1)->pluck('id');

            $this->line(sprintf(
                'user_id=%s date=%s: keeping #%d (check_in=%s, check_out=%s, work_hours=%s), removing %d row(s) [%s]',
                $group->user_id,
                $group->date,
                $keepRow->id,
                $earliestCheckIn,
                $latestCheckOut,
                $workHours,
                $extraRowIds->count(),
                $extraRowIds->implode(', ')
            ));

            if (!$dryRun) {
                DB::transaction(function () use ($keepRow, $earliestCheckIn, $latestCheckOut, $workHours, $extraRowIds) {
                    $keepRow->check_in   = $earliestCheckIn;
                    $keepRow->check_out  = $latestCheckOut;
                    $keepRow->work_hours = $workHours;
                    $keepRow->status     = 'Present';
                    $keepRow->save();

                    Attendance::whereIn('id', $extraRowIds)->delete();
                });
            }

            $mergedCount++;
            $deletedCount += $extraRowIds->count();
        }

        $this->info(($dryRun ? '[DRY RUN] Would merge ' : 'Merged ') . "{$mergedCount} day(s), " . ($dryRun ? 'would remove ' : 'removed ') . "{$deletedCount} duplicate row(s).");
    }

    /**
     * Sum worked minutes across the day, one session per slot. For each slot,
     * takes the first "in" and the last "out" within that slot's window (or
     * the slot's end if never scanned out), capped at the slot's official
     * start/end. Mirrors AutoCheckoutEmployees::computeWorkedHoursAcrossSlots.
     */
    private function computeWorkedHoursAcrossSlots(int $employeeId, string $date, array $slotRanges): float
    {
        $dayPunches = EmployeePunchLog::where('employee_id', $employeeId)
            ->where('punch_date', $date)
            ->orderBy('punch_time')
            ->get();

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
            $inTime = Carbon::parse("$date {$firstIn->punch_time}");
            $creditedStart = $inTime->lt($start) ? $start : $inTime;

            $lastOut = $slotPunches->where('punch_type', 'out')->sortByDesc('punch_time')->first();
            $endTime = $lastOut ? Carbon::parse("$date {$lastOut->punch_time}") : $end;
            if ($endTime->gt($end)) {
                $endTime = $end;
            }
            if ($endTime->lt($creditedStart)) {
                $endTime = $end;
            }

            $totalMinutes += max(0, $creditedStart->diffInMinutes($endTime));
        }

        return round($totalMinutes / 60, 2);
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
