<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Employee;
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
            $slotEndTime  = $this->resolveSlotEndTime($employee, $checkInTime, $now);
            $outOfSlot    = $slotEndTime === null;

            // Out-of-slot check-in: checkout at midnight, work_hours = 0 (not counted)
            if ($outOfSlot) {
                $midnight = $now->copy()->endOfDay();
                if ($now->lt($midnight->copy()->subMinutes(1))) {
                    continue; // wait until end of day
                }
                $slotEndTime = $midnight;
            }

            // Only checkout if current time has passed slot end time
            if ($now->lt($slotEndTime)) {
                continue;
            }

            try {
                $checkOutStr = $slotEndTime->format('H:i:s');
                $workHours   = $outOfSlot ? 0 : (
                    Carbon::parse($attendance->check_in, 'Asia/Kolkata')->diffInHours($slotEndTime)
                    + round(Carbon::parse($attendance->check_in, 'Asia/Kolkata')->diffInMinutes($slotEndTime) % 60 / 60, 2)
                );

                $attendance->check_out  = $checkOutStr;
                $attendance->work_hours = $workHours;
                $attendance->remarks    = ($attendance->remarks ? $attendance->remarks . ' | ' : '')
                    . ($outOfSlot ? 'Auto checkout (out of slot – 0 hrs)' : 'Auto checkout');
                $attendance->save();

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
     * Parse the slot end time that matches when the employee checked in.
     * Slot format examples: "6:00 AM - 2:00 PM", "2:00 PM - 10:00 PM", "08:00 - 16:00"
     */
    private function resolveSlotEndTime(Employee $employee, Carbon $checkInTime, Carbon $now): ?Carbon
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

            // Match any check-in from 2 hours before slot start through slot end,
            // so late check-ins within the slot (e.g. 9:24 AM in a 7:00-11:55 AM slot)
            // still resolve to that slot's end time instead of falling through as "out of slot".
            if ($checkInTime->between($start->copy()->subHours(2), $end)) {
                return $end;
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
