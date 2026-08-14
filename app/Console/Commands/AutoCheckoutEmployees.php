<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeePunchLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoCheckoutEmployees extends Command
{
    protected $signature   = 'app:auto-checkout-employees';
    protected $description = 'Auto checkout employees (by slot end) and members (2 hrs after check-in) still checked in today';

    public function handle(): void
    {
        $now  = Carbon::now('Asia/Kolkata');
        $today = $now->toDateString();

        // All employees who are still checked in today
        $openAttendances = Attendance::whereDate('date', $today)
            ->whereNull('check_out')
            ->whereNotNull('check_in')
            ->whereHas('employee')
            ->with('employee')
            ->get();

        // Auto checkout members 2 hours after check-in
        $openMemberAttendances = Attendance::whereDate('date', $today)
            ->whereNull('check_out')
            ->whereNotNull('check_in')
            ->whereHas('members')
            ->with('members')
            ->get();

        foreach ($openMemberAttendances as $attendance) {
            $checkInTime = Carbon::parse($attendance->check_in, 'Asia/Kolkata');
            $autoCheckoutTime = $checkInTime->copy()->addHours(2);

            if ($now->lt($autoCheckoutTime)) {
                continue;
            }

            try {
                $checkOutStr = $autoCheckoutTime->format('H:i:s');
                $workHours   = 2;

                $attendance->check_out  = $checkOutStr;
                $attendance->work_hours = $workHours;
                $attendance->remarks    = ($attendance->remarks ? $attendance->remarks . ' | ' : '') . 'Auto checkout';
                $attendance->save();

                Log::info('Auto checkout member', [
                    'member_id'  => $attendance->user_id,
                    'check_out'  => $checkOutStr,
                ]);
            } catch (\Throwable $e) {
                Log::error('Auto checkout member failed', [
                    'attendance_id' => $attendance->id,
                    'error'         => $e->getMessage(),
                ]);
            }
        }

        if ($openAttendances->isEmpty()) {
            return;
        }

        foreach ($openAttendances as $attendance) {
            $employee = $attendance->employee;
            if (!$employee) {
                continue;
            }

            $checkInTime = Carbon::parse($attendance->check_in, 'Asia/Kolkata');

            // Determine which slot the employee checked in during
            $slotRange   = $this->resolveSlotRange($employee, $checkInTime, $now);
            $outOfSlot   = $slotRange === null;
            $slotEndTime = $outOfSlot ? null : $slotRange[1];

            // Out-of-slot check-in: checkout at midnight, work_hours = 0 (not counted)
            if ($outOfSlot) {
                $midnight = $now->copy()->endOfDay();
                if ($now->lt($midnight->copy()->subMinutes(1))) {
                    continue; // wait until end of day
                }
                $slotEndTime = $midnight;
            } else {
                // A split-shift employee's morning slot ending does NOT mean their
                // day is done — closing the Attendance row right away would freeze
                // work_hours at just that one slot and silently drop the evening
                // session once it happens. Wait until the day's LAST slot ends.
                $allSlots = $this->allSlotRanges($employee, $now);
                $lastSlotEnd = end($allSlots)[1];
                if ($now->lt($lastSlotEnd)) {
                    continue;
                }
                $slotEndTime = $lastSlotEnd;
            }

            // Only checkout if current time has passed slot end time
            if ($now->lt($slotEndTime)) {
                continue;
            }

            try {
                $checkOutStr = $slotEndTime->format('H:i:s');
                $workHours   = $outOfSlot ? 0 : $this->computeWorkedHoursAcrossSlots(
                    $employee->id,
                    $attendance->date,
                    $allSlots
                );

                $attendance->check_out  = $checkOutStr;
                $attendance->work_hours = $workHours;
                $attendance->remarks    = ($attendance->remarks ? $attendance->remarks . ' | ' : '')
                    . ($outOfSlot ? 'Auto checkout (out of slot – 0 hrs)' : 'Auto checkout');
                $attendance->save();

                // Without this, the punch log still looks "unclosed" even
                // though the Attendance row has a check_out — later work_hours
                // recomputation (from the punch log alone) would then fall
                // back to slot-end credit again for a session that's already
                // accounted for here, potentially double-crediting it.
                EmployeePunchLog::create([
                    'employee_id' => $employee->id,
                    'punch_date'  => $attendance->date,
                    'punch_time'  => $checkOutStr,
                    'punch_type'  => 'out',
                    'source'      => 'manual', // system-generated (auto-checkout), not a real device scan
                    'device_sn'   => null,
                ]);

                $this->info("Auto checkout: employee #{$employee->id} ({$employee->name}) at {$checkOutStr}");
                Log::info('Auto checkout employee', [
                    'employee_id' => $employee->id,
                    'name'        => $employee->name,
                    'check_out'   => $checkOutStr,
                    'work_hours'  => $workHours,
                ]);
            } catch (\Throwable $e) {
                Log::error('Auto checkout failed', [
                    'attendance_id' => $attendance->id,
                    'error'         => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * All of an employee's slot ranges for today, sorted by start time.
     */
    private function allSlotRanges(Employee $employee, Carbon $now): array
    {
        $slots = array_filter([$employee->morning_slot, $employee->evening_slot]);
        $ranges = [];
        foreach ($slots as $slot) {
            $range = $this->parseSlotRange($slot, $now);
            if ($range) {
                $ranges[] = $range;
            }
        }
        usort($ranges, fn ($a, $b) => $a[0]->timestamp <=> $b[0]->timestamp);
        return $ranges;
    }

    /**
     * Sum worked minutes across the day, one session per slot. For each slot,
     * takes the first "in" and the last "out" within that slot's window (or
     * the slot's end if never scanned out) — this collapses any number of
     * duplicate/retry punches inside one slot into a single session, so a
     * slot can never be credited more than once no matter how noisy the raw
     * punches are (e.g. two "in" scans 60+ minutes apart from the same
     * arrival, which a simple sequential walk would treat as two sessions).
     * Also caps credit symmetrically at the slot's official start/end — no
     * early-arrival or overtime credit.
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
                $t = Carbon::parse("$date {$p->punch_time}", 'Asia/Kolkata');
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
            // valid attendance, but doesn't earn extra paid minutes — credit
            // from the slot's official start, never earlier.
            $inTime = Carbon::parse("$date {$firstIn->punch_time}", 'Asia/Kolkata');
            $creditedStart = $inTime->lt($start) ? $start : $inTime;

            $lastOut = $slotPunches->where('punch_type', 'out')->sortByDesc('punch_time')->first();
            $endTime = $lastOut ? Carbon::parse("$date {$lastOut->punch_time}", 'Asia/Kolkata') : $end;
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

        return round($totalMinutes / 60, 2);
    }

    /**
     * Find the slot [start, end] that matches when the employee checked in.
     * Slot format examples: "6:00 AM - 2:00 PM", "2:00 PM - 10:00 PM", "08:00 - 16:00"
     */
    private function resolveSlotRange(Employee $employee, Carbon $checkInTime, Carbon $now): ?array
    {
        $slots = array_filter([
            $employee->morning_slot,
            $employee->evening_slot,
        ]);

        foreach ($slots as $slot) {
            $range = $this->parseSlotRange($slot, $now);
            if (!$range) {
                continue;
            }

            [$start, $end] = $range;

            // Match any check-in from 30 minutes before slot start through slot
            // end, so both a slightly early arrival and a late-but-within-slot
            // check-in (e.g. 9:24 AM in a 7:00-11:55 AM slot) resolve to this
            // slot instead of falling through as "out of slot".
            if ($checkInTime->between($start->copy()->subMinutes(30), $end)) {
                return [$start, $end];
            }
        }

        return null;
    }

    /**
     * Parse a slot string into [startCarbon, endCarbon] for today.
     * Handles: "6:00 AM - 2:00 PM", "14:00 - 22:00", "6AM-2PM", etc.
     */
    private function parseSlotRange(string $slot, Carbon $now): ?array
    {
        $slot = trim($slot);
        if ($slot === '') {
            return null;
        }

        // Split on " - ", " to ", "-" (with or without spaces)
        $parts = preg_split('/\s*[-–]\s*(?=\d)|(\s+to\s+)/i', $slot, 2);
        if (!$parts || count($parts) < 2) {
            return null;
        }

        $startStr = trim($parts[0]);
        $endStr   = trim($parts[1]);

        try {
            $date    = $now->toDateString();
            $start   = Carbon::parse("$date $startStr", 'Asia/Kolkata');
            $end     = Carbon::parse("$date $endStr", 'Asia/Kolkata');

            // If end is before start (e.g., overnight shift), add a day to end
            if ($end->lt($start)) {
                $end->addDay();
            }

            return [$start, $end];
        } catch (\Throwable) {
            return null;
        }
    }
}
