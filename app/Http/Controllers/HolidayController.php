<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HolidayController extends Controller
{
    // List all holidays, or just one year's if `year` is passed — Salary
    // Generation uses the year filter so it only pulls the dates it needs.
    public function index(Request $request)
    {
        $query = Holiday::query()->orderBy('date');

        if ($request->filled('year')) {
            $query->whereYear('date', (int) $request->input('year'));
        }

        $holidays = $query->get(['id', 'date', 'name'])->map(function ($h) {
            $h->date = date('Y-m-d', strtotime($h->date));
            return $h;
        });

        return response()->json([
            'data' => $holidays,
            'code' => 200,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors(),
                'code'    => 422,
            ], 200);
        }

        if (Holiday::where('date', $request->date)->exists()) {
            return response()->json([
                'message' => 'A holiday is already set for this date.',
                'code'    => 409,
            ], 200);
        }

        $holiday = Holiday::create([
            'date' => $request->date,
            'name' => $request->name,
        ]);

        return response()->json([
            'message' => 'Holiday added.',
            'data'    => $holiday,
            'code'    => 200,
        ], 200);
    }

    public function update(Request $request, string $id)
    {
        $holiday = Holiday::find($id);
        if (!$holiday) {
            return response()->json(['message' => 'Holiday not found.', 'code' => 404], 200);
        }

        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors(),
                'code'    => 422,
            ], 200);
        }

        $duplicate = Holiday::where('date', $request->date)->where('id', '!=', $id)->exists();
        if ($duplicate) {
            return response()->json([
                'message' => 'A holiday is already set for this date.',
                'code'    => 409,
            ], 200);
        }

        $holiday->update([
            'date' => $request->date,
            'name' => $request->name,
        ]);

        return response()->json([
            'message' => 'Holiday updated.',
            'data'    => $holiday,
            'code'    => 200,
        ], 200);
    }

    public function destroy(string $id)
    {
        $holiday = Holiday::find($id);
        if (!$holiday) {
            return response()->json(['message' => 'Holiday not found.', 'code' => 404], 200);
        }

        $holiday->delete();

        return response()->json([
            'message' => 'Holiday deleted.',
            'code'    => 200,
        ], 200);
    }
}
