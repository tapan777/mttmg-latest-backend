<?php

namespace App\Http\Controllers;

use App\Models\Salary;
use Illuminate\Http\Request;
use App\Models\Employee;
use Illuminate\Support\Facades\Validator;

class SalaryController extends Controller
{
    public function generateSalary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userId' => 'required',
            'workHours' => 'required', // Remove numeric validation here
            'hoursPerDay' => 'required|numeric|min:1',
            'salaryPerDay' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
                'code' => 500
            ], 500);
        }

        $userId = $request->userId;
        $generatedDate = date('Y-m-01');

        $existing = Salary::where('user_id', $userId)
                          ->whereDate('generated_date', $generatedDate)
                          ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Salary already generated for this month.',
                'code' => 409
            ], 409);
        }

        $workHoursString = $request->workHours;
        $hoursPerDay = $request->hoursPerDay;
        $salaryPerDay = $request->salaryPerDay;

        // Extract numeric portion from workHours string
        preg_match('/([\d.]+)/', $workHoursString, $matches);
        $workHours = isset($matches[1]) ? floatval($matches[1]) : 0; // Default to 0 if extraction fails

        // Ensure hoursPerDay is numeric
        if (!is_numeric($hoursPerDay)) {
            return response()->json([
                'success' => false,
                'message' => 'Hours per day is not numeric.',
                'code' => 400,
            ], 400);
        }

        if ($hoursPerDay == 0) {
            return response()->json([
                'success' => false,
                'message' => 'Hours per day cannot be zero.',
                'code' => 400,
            ], 400);
        }

        $totalDays = $workHours / $hoursPerDay;
        $totalSalary = $totalDays * $salaryPerDay;

        $salary = new Salary();
        $salary->user_id = $userId;
        $salary->work_hours = $workHours;
        $salary->hours_per_day = $hoursPerDay;
        $salary->salary_per_day = $salaryPerDay;
        $salary->total_salary = $totalSalary;
        $salary->generated_date = $generatedDate;
        $salary->working_days = $totalDays; // Store working days
        $salary->save();

        // Fetch employee data
        $employee = Employee::find($userId); // Assuming userId is the employee ID

        // Include employee data in the response
        $responseData = [
            'user_id' => $salary->user_id,
            'work_hours' => $salary->work_hours,
            'hours_per_day' => $salary->hours_per_day,
            'salary_per_day' => $salary->salary_per_day,
            'total_salary' => $salary->total_salary,
            'generated_date' => $salary->generated_date,
            'working_days' => $salary->working_days, // Include working days in response
            'updated_at' => $salary->updated_at,
            'created_at' => $salary->created_at,
            'id' => $salary->id,
        ];

        if ($employee) {
            $responseData['address'] = $employee->address;
            $responseData['name'] = $employee->name;
            $responseData['email'] = $employee->email;
            $responseData['phone'] = $employee->phone;
        }

        return response()->json([
            'success' => true,
            'message' => 'Salary generated and stored successfully.',
            'data' => $responseData,
            'code' => 200
        ], 200);
    }
}