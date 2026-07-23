<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeePunchLog;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;

class EmployeePunchLogController extends Controller
{
    /**
     * Get punch log for an employee on a given date (default today).
     * Also returns total check-in count for the day.
     */
    public function getByEmployee(Request $request)
    {
        try {
            $employeeId = $request->employee_id;
            $date       = $request->date ?? Carbon::today()->toDateString();

            $employee = Employee::find($employeeId);
            if (!$employee) {
                return response()->json(['message' => 'Employee not found', 'code' => 500]);
            }

            $logs = EmployeePunchLog::where('employee_id', $employeeId)
                ->whereDate('punch_date', $date)
                ->orderBy('punch_time')
                ->get()
                ->map(function ($log) {
                    return [
                        'id'         => $log->id,
                        'punch_time' => date('h:i A', strtotime($log->punch_time)),
                        'punch_type' => $log->punch_type,
                        'source'     => $log->source,
                    ];
                });

            $checkInCount = $logs->where('punch_type', 'in')->count();

            return response()->json([
                'employee_id'    => $employee->id,
                'employee_name'  => $employee->name,
                'date'           => $date,
                'total_checkins' => $checkInCount,
                'data'           => $logs,
                'code'           => 200,
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 500]);
        }
    }

    /**
     * Get punch log for all employees on a given date with daily checkin count.
     */
    public function getByDate(Request $request)
    {
        try {
            $date   = $request->date ?? Carbon::today()->toDateString();
            $limit  = $request->limit > 0 ? (int) $request->limit : 20;
            $offset = $request->index > 0 ? (int) $request->index : 0;

            $employeesQuery = Employee::whereHas('punchLogs', function ($q) use ($date) {
                $q->whereDate('punch_date', $date);
            })->with(['punchLogs' => function ($q) use ($date) {
                $q->whereDate('punch_date', $date)->orderBy('punch_time');
            }]);

            $total      = $employeesQuery->count();
            $employees  = $employeesQuery->offset($offset)->limit($limit)->get();

            $data = $employees->map(function ($employee) {
                $logs         = $employee->punchLogs;
                $checkInCount = $logs->where('punch_type', 'in')->count();
                $firstIn      = $logs->where('punch_type', 'in')->first();
                $lastOut      = $logs->where('punch_type', 'out')->last();

                return [
                    'employee_id'    => $employee->id,
                    'employee_name'  => $employee->name,
                    'total_checkins' => $checkInCount,
                    'first_checkin'  => $firstIn  ? date('h:i A', strtotime($firstIn->punch_time))  : null,
                    'last_checkout'  => $lastOut  ? date('h:i A', strtotime($lastOut->punch_time)) : null,
                    'punches'        => $logs->map(fn ($l) => [
                        'time' => date('h:i A', strtotime($l->punch_time)),
                        'type' => $l->punch_type,
                    ])->values(),
                ];
            });

            return response()->json([
                'date'        => $date,
                'total_count' => $total,
                'data'        => $data,
                'code'        => 200,
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 500]);
        }
    }
}
