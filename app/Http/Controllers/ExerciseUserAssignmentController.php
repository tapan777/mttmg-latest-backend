<?php

namespace App\Http\Controllers;

use App\Jobs\SendExerciseAttachmentEmailJob;
use App\Models\Exercise;
use App\Models\ExerciseUserAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ExerciseUserAssignmentController extends Controller
{
    /**
     * Assign one or more members to an exercise.
     */
    public function assignMemberToExercise(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'exercise_id'  => 'required|integer|exists:exercises,id',
                'member_id'    => 'required|array|min:1',
                'member_id.*'  => 'required|integer|exists:members,id',
                'attachment'   => 'nullable|file|max:10240', // max 10MB
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 422,
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            $assignments = [];

            foreach ($request->member_id as $memberId) {
                $assignments[] = ExerciseUserAssignment::updateOrCreate(
                    ['exercise_id' => $request->exercise_id, 'member_id' => $memberId],
                    []
                );
            }

            // Send attachment email to members via queue (attachment not saved to DB)
            if ($request->hasFile('attachment')) {
                $file             = $request->file('attachment');
                $originalName     = $file->getClientOriginalName();
                $tempDir          = storage_path('app/temp/exercise');
                if (!is_dir($tempDir)) {
                    mkdir($tempDir, 0775, true);
                }
                $tempPath = $tempDir . '/' . Str::uuid() . '.' . $file->getClientOriginalExtension();
                $file->move(dirname($tempPath), basename($tempPath));

                $exercise     = Exercise::find($request->exercise_id);
                $exerciseName = $exercise->exercise_name ?? 'Exercise Plan';

                SendExerciseAttachmentEmailJob::dispatch(
                    array_map('intval', $request->member_id),
                    $exerciseName,
                    $tempPath,
                    $originalName,
                )->afterResponse();
            }

            return response()->json([
                'code'    => 200,
                'message' => 'Members assigned successfully.' . ($request->hasFile('attachment') ? ' Exercise plan email will be sent shortly.' : ''),
                'data'    => $assignments,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retrieve exercise assignments: which members have which exercises.
     * Optional: exercise_id, member_id, search_text (member name / exercise name), limit, index.
     */
    public function retrieve(Request $request)
    {
        try {
            $limit = (int) $request->input('limit', 10);
            $index = (int) $request->input('index', 0);
            $exerciseId = $request->input('exercise_id');
            $memberId = $request->input('member_id');
            $searchText = $request->input('search_text', '');

            $query = ExerciseUserAssignment::with([
                'exercise:id,exercise_name,description,sets,reps,duration',
                'member:id,name,membership_number,phone,email',
            ])->orderBy('id', 'desc');

            if ($exerciseId !== null && $exerciseId !== '') {
                $query->where('exercise_id', $exerciseId);
            }
            if ($memberId !== null && $memberId !== '') {
                $query->where('member_id', $memberId);
            }
            if ($searchText !== '') {
                $query->where(function ($q) use ($searchText) {
                    $q->whereHas('member', function ($mq) use ($searchText) {
                        $mq->where('name', 'like', "%{$searchText}%")
                            ->orWhere('membership_number', 'like', "%{$searchText}%")
                            ->orWhere('phone', 'like', "%{$searchText}%");
                    })->orWhereHas('exercise', function ($eq) use ($searchText) {
                        $eq->where('exercise_name', 'like', "%{$searchText}%")
                            ->orWhere('description', 'like', "%{$searchText}%");
                    });
                });
            }

            $total = $query->count();
            $assignments = $query->skip($index)->take($limit)->get()->map(function ($a) {
                return [
                    'id' => $a->id,
                    'exercise_id' => $a->exercise_id,
                    'member_id' => $a->member_id,
                    'exercise' => $a->exercise ? [
                        'id' => $a->exercise->id,
                        'exercise_name' => $a->exercise->exercise_name,
                        'description' => $a->exercise->description,
                        'sets' => $a->exercise->sets,
                        'reps' => $a->exercise->reps,
                        'duration' => $a->exercise->duration,
                    ] : null,
                    'member' => $a->member ? [
                        'id' => $a->member->id,
                        'name' => $a->member->name,
                        'membership_number' => $a->member->membership_number,
                        'phone' => $a->member->phone,
                        'email' => $a->member->email,
                    ] : null,
                    'assigned_at' => $a->created_at ? $a->created_at->format('Y-m-d H:i:s') : null,
                ];
            });

            return response()->json([
                'code' => 200,
                'message' => 'Exercise assignments retrieved successfully.',
                'data' => $assignments,
                'total' => $total,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove a member from an exercise assignment.
     */
    public function unassign(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'exercise_id' => 'required|integer|exists:exercises,id',
                'member_id' => 'required|integer|exists:members,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 422,
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            $deleted = ExerciseUserAssignment::where('exercise_id', $request->exercise_id)
                ->where('member_id', $request->member_id)
                ->delete();

            if (!$deleted) {
                return response()->json([
                    'code' => 404,
                    'message' => 'Assignment not found.',
                ], 200);
            }

            return response()->json([
                'code' => 200,
                'message' => 'Member unassigned from exercise successfully.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
