<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExerciseController extends Controller
{
    /**
     * Create a new exercise.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'exercise_name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'instructions' => 'nullable|string',
                'sets' => 'nullable|string|max:100',
                'reps' => 'nullable|string|max:100',
                'duration' => 'nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                    'code' => 422,
                ], 422);
            }

            $exercise = Exercise::create($validator->validated());

            return response()->json([
                'message' => 'Exercise created successfully.',
                'data' => $exercise,
                'code' => 200,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong.',
                'error' => $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    /**
     * List exercises with pagination.
     */
    public function index(Request $request)
    {
        try {
            $limit = (int) $request->input('limit', 10);
            $index = (int) $request->input('index', 0);
            $searchText = $request->input('search_text', '');

            $query = Exercise::orderBy('id', 'desc');

            if ($searchText !== '') {
                $query->where(function ($q) use ($searchText) {
                    $q->where('exercise_name', 'like', "%{$searchText}%")
                        ->orWhere('description', 'like', "%{$searchText}%");
                });
            }

            $total = $query->count();
            $exercises = $query->skip($index)->take($limit)->get();

            return response()->json([
                'message' => 'Exercises fetched successfully.',
                'code' => 200,
                'data' => $exercises,
                'total' => $total,
                'pagination' => [
                    'index' => $index,
                    'limit' => $limit,
                    'total_pages' => $limit > 0 ? (int) ceil($total / $limit) : 0,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error fetching exercises.',
                'code' => 500,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an exercise.
     */
    public function delete(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|exists:exercises,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                    'code' => 422,
                ], 422);
            }

            $exercise = Exercise::find($request->id);
            $exercise->delete();

            return response()->json([
                'message' => 'Exercise deleted successfully.',
                'code' => 200,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error deleting exercise.',
                'code' => 500,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Dropdown list of exercises (id, exercise_name) for assign UI.
     */
    public function dropdown(Request $request)
    {
        try {
            $exercises = Exercise::orderBy('exercise_name')->get(['id', 'exercise_name']);
            return response()->json([
                'message' => 'Exercises dropdown fetched successfully.',
                'code' => 200,
                'data' => $exercises,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error fetching exercises.',
                'code' => 500,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
