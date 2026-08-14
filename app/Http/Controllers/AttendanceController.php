<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeePunchLog;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $type = $request->input('type');
            $searchText = $request->input('search_text', '');
            $limit = $request->input('limit', 10);
            $index = $request->input('index', 0);
            $dateFilter = $request->input('date', null);
    
            $date_filter_from = '';
            $date_filter_to = '';
    
            $query = Attendance::query();
    
            // Apply Relationship Conditions Based on `user_id`
            if ($type === 'employees') {
                $query->where(function ($query) {
                    $query->whereNotNull('user_id')->where(function($query){
                        $query->where(function($q){
                            $q->whereHas('employee');
                        });
                    });
                })->with('employee');
            } else  {
                $query->where(function ($query) {
                    $query->whereNotNull('user_id')->where(function($query){
                        $query->where(function($q){
                            $q->whereHas('members');
                        });
                    });
                })->with('members');
            }
            // Apply Search Filter
            if ($searchText) {
                if ($type === 'employees') {
                    $query->whereHas('employee', function ($qry) use ($searchText) {
                        $qry->where('name', 'LIKE', "%{$searchText}%");
                    });
                } elseif ($type === 'members') {
                    $query->whereHas('members', function ($qry) use ($searchText) {
                        $qry->where('name', 'LIKE', "%{$searchText}%");
                    });
                }
            }
    
            // Apply Date Filter
            if ($dateFilter && isset($dateFilter['type']) && isset($dateFilter['value'])) {
                if ($dateFilter['type'] == 1) {
                    switch ($dateFilter['value']) {
                        case 'today':
                            $date_filter_from = $date_filter_to = date('Y-m-d');
                            break;
                        case '7days':
                            $date_filter_from = date('Y-m-d', strtotime('-7 days'));
                            $date_filter_to = date('Y-m-d');
                            break;
                        case '30days':
                            $date_filter_from = date('Y-m-d', strtotime('-30 days'));
                            $date_filter_to = date('Y-m-d');
                            break;
                        case 'thisYear':
                            $date_filter_from = date('Y-01-01');
                            $date_filter_to = date('Y-12-31');
                            break;
                        case 'lastYear':
                            $date_filter_from = date('Y-01-01', strtotime('last year'));
                            $date_filter_to = date('Y-12-31', strtotime('last year'));
                            break;
                    }
                } elseif ($dateFilter['type'] == 2) {
                    $custom_date_arr = explode(',', $dateFilter['value']);
                    if (count($custom_date_arr) == 2) {
                        $date_filter_from = date('Y-m-d', strtotime($custom_date_arr[0]));
                        $date_filter_to = date('Y-m-d', strtotime($custom_date_arr[1]));
                    }
                }
            }
    
            if ($date_filter_from && $date_filter_to) {
                $query->whereBetween('date', [$date_filter_from, $date_filter_to]);
            }

            $total_count = $query->count();

            // Fetch Data
            $attendance_data = $query->orderBy('id', 'desc')
                ->offset($index)
                ->limit($limit)
                ->get()
                ->map(function ($item) use ($type) {
                    $rawDate = $item->date;
                    if ($item->employee) {
                        $item->user_name = $item->employee->name;
                        $item->phone = $item->employee->phone;
                        $item->user_type = 'employee';
                        $item->designation = $item->employee->designation;
                        $item->morning_slot = $item->employee->morning_slot;
                        $item->evening_slot = $item->employee->evening_slot;

                        // Give split-shift employees their per-session punches so
                        // the UI can show Morning/Evening separately instead of
                        // one collapsed check_in/check_out span for the whole day.
                        $logs = EmployeePunchLog::where('employee_id', $item->user_id)
                            ->where('punch_date', $rawDate)
                            ->orderBy('punch_time')
                            ->get();
                        $deduped = [];
                        foreach ($logs as $log) {
                            $last = end($deduped);
                            if ($last && $last['type'] === $log->punch_type
                                && (strtotime($log->punch_time) - strtotime($last['time'])) / 60 < 5) {
                                continue;
                            }
                            $deduped[] = ['time' => $log->punch_time, 'type' => $log->punch_type];
                        }
                        $item->punches = array_map(fn ($l) => [
                            'time' => date('h:i A', strtotime($l['time'])),
                            'type' => $l['type'],
                        ], $deduped);
                    } elseif ($item->members) {
                        $item->user_name = $item->members->name;
                        $item->phone = $item->members->phone;
                        $item->user_type = 'member';
                    }

                    $item->date = date('d-m-Y', strtotime($item->date));
                    $item->check_in = date('h:i A', strtotime($item->check_in));
                    $item->check_out = $item->check_out ? date('h:i A', strtotime($item->check_out)) : null;

                    unset($item->members, $item->employee);
                    return $item;
                });
    
            return response()->json([
                'data' => $attendance_data->isNotEmpty() ? $attendance_data : ['message' => 'No Record Found'],
                'total_count' => $total_count,
                'code' => 200,

            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong!',
                'error' => $e->getMessage(),
                'code' => 500,
            ], 200);
        }
    }
    public function getMonthlyAttendance(Request $request)
    {
        try {
            $userId = $request->input('user_id');
            $month = $request->input('month'); // Expecting 'YYYY-MM' format
            $limit = $request->input('limit', 10);
            $index = $request->input('index', 0);

            if (!$userId || !$month) {
                return response()->json([
                    'message' => 'User ID and month are required.',
                    'code' => 400,
                ], 400);
            }

            // Calculate start and end dates for the month
            $startDate = date('Y-m-01', strtotime($month));
            $endDate = date('Y-m-t', strtotime($month));

            // Check if this user is an employee (for punch log)
            $isEmployee = Employee::where('id', $userId)->exists();

            // Pre-load all punch logs for this employee for the month (single query)
            $punchLogsByDate = [];
            if ($isEmployee) {
                $rawPunchLogsByDate = [];
                EmployeePunchLog::where('employee_id', $userId)
                    ->whereBetween('punch_date', [$startDate, $endDate])
                    ->orderBy('punch_time')
                    ->get()
                    ->each(function ($log) use (&$rawPunchLogsByDate) {
                        $rawPunchLogsByDate[$log->punch_date][] = [
                            'time' => $log->punch_time,
                            'type' => $log->punch_type,
                        ];
                    });

                // Collapse duplicate/double-scan punches (same type, a few
                // minutes apart — a device glitch, not two real events) into
                // one, so the punch timeline and its counts (and anything
                // downstream, like salary calculation) only ever see one.
                foreach ($rawPunchLogsByDate as $date => $logs) {
                    $deduped = [];
                    foreach ($logs as $log) {
                        $last = end($deduped);
                        if ($last && $last['type'] === $log['type']
                            && (strtotime($log['time']) - strtotime($last['time'])) / 60 < 5) {
                            continue;
                        }
                        $deduped[] = $log;
                    }
                    $punchLogsByDate[$date] = array_map(fn ($l) => [
                        'time' => date('h:i A', strtotime($l['time'])),
                        'type' => $l['type'],
                    ], $deduped);
                }
            }

            $totalCount = Attendance::where('user_id', $userId)
                ->whereBetween('date', [$startDate, $endDate])
                ->count();

            $attendanceData = Attendance::where('user_id', $userId)
                ->whereBetween('date', [$startDate, $endDate])
                ->orderBy('date')
                ->offset($index)
                ->limit($limit)
                ->get()
                ->map(function ($item) use ($punchLogsByDate, $isEmployee) {
                    $rawDate        = $item->date;
                    $item->date     = date('d-m-Y', strtotime($rawDate));
                    $item->check_in = date('h:i A', strtotime($item->check_in));
                    $item->check_out = $item->check_out ? date('h:i A', strtotime($item->check_out)) : null;

                    // Attach all punch events for this day
                    $item->punches        = $isEmployee ? ($punchLogsByDate[$rawDate] ?? []) : [];
                    $item->total_punches  = count($item->punches);
                    $item->total_checkins = collect($item->punches)->where('type', 'in')->count();

                    return $item;
                });

            return response()->json([
                'message'     => 'success',
                'total_count' => $totalCount,
                'data'        => $attendanceData->isNotEmpty() ? $attendanceData : [],
                'code'        => 200,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong!',
                'error' => $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }
    



    public function check_out(Request $request)
    {
        $attendance = Attendance::where('id', $request->id)->first();
    
        if (!$attendance) {
            return response()->json(['message' => 'Attendance record not found', 'code' => 404], 200);
        }
    
        // Prevent multiple check-outs
        if ($attendance->check_out) {
            return response()->json(['message' => 'Already checked out', 'code' => 400], 200);
        }
    
        $checkOutTime = Carbon::now()->setTimezone('Asia/Kolkata')->format('H:i:s');
    
        // Update check-out time
        $attendance->check_out = $checkOutTime;
    
        if ($attendance->check_in) {
            $checkInTime = Carbon::parse($attendance->check_in);
            $checkOutTimeParsed = Carbon::parse($checkOutTime);
    
            // Calculate total work hours (hours + fractional minutes)
            $workHours = $checkInTime->diffInHours($checkOutTimeParsed)
                + round($checkInTime->diffInMinutes($checkOutTimeParsed) % 60 / 60, 2);
    
            $attendance->work_hours = $workHours;
        }
    
        if ($attendance->save()) {
            return response()->json([
                'message' => 'Check-out updated successfully',
                'check_out' => $attendance->check_out,
                'work_hours' => $attendance->work_hours,
                'code' => 200
            ], 200);
        } else {
            return response()->json([
                'message' => 'Failed to update check-out. Please try again.',
                'code' => 500
            ], 500);
        }
    }
    

    public function send_notification_for_checkout()
    {
        $attendance = Attendance::whereNull('check_out')
            ->whereDate('date', Carbon::today())
            ->get();

        foreach ($attendance as $record) {
            $checkInTime = Carbon::parse($record->check_in); // Convert check-in to Carbon instance
            $now = Carbon::now(); // Get the current time

            // Check if 1.5 hours (90 minutes) have passed since check-in
            if ($checkInTime->diffInMinutes($now) >= 90) {
                // Do something (e.g., notify the user, update status, etc.)
                echo "User {$record->user_id} checked in 1.5 hours ago.\n";
            }
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function getCurrentMonthWorkHours(Request $request)
{
    try {
        $request->validate([
            'user_id' => 'required|integer'
        ]);

        $userId = $request->user_id;
        $currentMonth = date('Y-m'); // Get the current month in 'YYYY-MM' format

        // Fetch total work hours for the given user in the current month
        $totalWorkHours = Attendance::where('user_id', $userId)
            ->where('date', 'like', "$currentMonth%") // Matches 'YYYY-MM%'
            ->sum('work_hours');

        // Convert work hours to minutes if less than 1 hour
        $workHoursFormatted = $totalWorkHours >= 1 
            ? round($totalWorkHours, 2) . " hours" 
            : round($totalWorkHours * 60) . " minutes";

        return response()->json([
            'user_id' => $userId,
            'month' => $currentMonth,
            'total_work_hours' => $workHoursFormatted,
            'message' => 'Total work hours retrieved successfully',
            'code' => 200
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error retrieving work hours',
            'error' => $e->getMessage(),
            'code' => 500
        ], 500);
    }
}

    

}
