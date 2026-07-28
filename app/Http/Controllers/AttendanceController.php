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
                    if ($item->employee) {
                        $item->user_name = $item->employee->name;
                        $item->phone = $item->employee->phone;
                        $item->user_type = 'employee';
                        $item->designation = $item->employee->designation;
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
                EmployeePunchLog::where('employee_id', $userId)
                    ->whereBetween('punch_date', [$startDate, $endDate])
                    ->orderBy('punch_time')
                    ->get()
                    ->each(function ($log) use (&$punchLogsByDate) {
                        $punchLogsByDate[$log->punch_date][] = [
                            'time' => date('h:i A', strtotime($log->punch_time)),
                            'type' => $log->punch_type,
                        ];
                    });
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
    


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $attendance_data = new BiomatricController;
            $attendanceLog = $attendance_data->get_attendance();
    
            if (!is_array($attendanceLog)) {
                return response()->json([
                    'message' => 'Invalid attendance log data',
                    'code' => 500
                ], 500);
            }
    
            $todayDate = date('Y-m-d');
            $todayRecords = [];
            $result = false;
    
            // Filter attendance records for today
            foreach ($attendanceLog as $record) {
                if (!isset($record['id'], $record['timestamp'])) {
                    continue; // Skip if ID or timestamp is missing
                }
    
                $recordDate = substr($record['timestamp'], 0, 10);
                if ($recordDate === $todayDate) {
                    $todayRecords[] = $record;
                }
            }
    
            foreach ($todayRecords as $record) {
                $userId = $record['id'];
                $timestamp = Carbon::parse($record['timestamp']);
                $date = $timestamp->format('Y-m-d');
                $time = $timestamp->format('H:i:s');
    
                // Find the latest record for the user on the same day
                $lastAttendance = Attendance::where('user_id', $userId)
                    ->where('date', $date)
                    ->latest()
                    ->first();
    
                if (!$lastAttendance || ($lastAttendance->check_out !== null)) {
                    // Create a new record only if:
                    // - No record exists for today OR
                    // - Last record has a check-out (previous session ended)
                    $data = Attendance::create([
                        'user_id' => $userId,
                        'date' => $date,
                        'check_in' => $time,
                        'status' => "Present",
                    ]);
                    $result = $data ? true : $result;
                } elseif (isset($record['type']) && $record['type'] == 1) {
                    // Update check-out if last entry has check-in but no check-out
                    $lastAttendance->check_out = $time;
                    $checkInTime = Carbon::parse($lastAttendance->check_in);
                    $checkOutTime = Carbon::parse($lastAttendance->check_out);
    
                    $lastAttendance->work_hours = $checkInTime->diffInHours($checkOutTime)
                        + round($checkInTime->diffInMinutes($checkOutTime) % 60 / 60, 2);
    
                    $data = $lastAttendance->save();
                    $result = $data ? true : $result;
                }
            }
    
            $attendance_data->clear_attendance();
    
            if ($result) {
                return response()->json([
                    'message' => 'Attendance added successfully',
                    'code' => 200
                ], 200);
            }
    
            return response()->json([
                'message' => 'No new attendance records added',
                'code' => 400
            ], 400);
    
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error processing attendance',
                'error' => $e->getMessage(),
                'code' => 500
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
