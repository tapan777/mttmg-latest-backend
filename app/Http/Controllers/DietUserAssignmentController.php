<?php

namespace App\Http\Controllers;

use App\Jobs\SendDietAttachmentEmailJob;
use App\Models\DietPlan;
use App\Models\DietUserAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DietUserAssignmentController extends Controller
{
    /**
     * Retrieve diet assignments: which members have which diet plans.
     * Optional: diet_id, member_id, search_text (member name / diet name), limit, index.
     */
    public function retrieve(Request $request)
    {
        try {
            $limit = (int) ($request->input('limit', 10));
            $index = (int) ($request->input('index', 0));
            $dietId = $request->input('diet_id');
            $memberId = $request->input('member_id');
            $searchText = $request->input('search_text', '');

            $query = DietUserAssignment::with(['dietPlan:id,diet_name,days', 'member:id,name,membership_number,phone,email'])
                ->orderBy('id', 'desc');

            if ($dietId !== null && $dietId !== '') {
                $query->where('diet_id', $dietId);
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
                    })->orWhereHas('dietPlan', function ($dq) use ($searchText) {
                        $dq->where('diet_name', 'like', "%{$searchText}%");
                    });
                });
            }

            $total = $query->count();
            $assignments = $query->skip($index)->take($limit)->get()->map(function ($a) {
                return [
                    'id' => $a->id,
                    'diet_id' => $a->diet_id,
                    'member_id' => $a->member_id,
                    'diet_plan' => $a->dietPlan ? [
                        'id' => $a->dietPlan->id,
                        'diet_name' => $a->dietPlan->diet_name,
                        'days' => $a->dietPlan->days,
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
                'message' => 'Diet assignments retrieved successfully.',
                'data' => $assignments,
                'total' => $total,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function assignMemberToDiet(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'diet_id'      => 'required|integer',
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
                $assignments[] = DietUserAssignment::updateOrCreate(
                    ['diet_id' => $request->diet_id, 'member_id' => $memberId],
                    []
                );
            }

            // Send attachment email to members via queue (attachment not saved to DB)
            if ($request->hasFile('attachment')) {
                $file         = $request->file('attachment');
                $originalName = $file->getClientOriginalName();
                $tempDir      = storage_path('app/temp/diet');
                if (!is_dir($tempDir)) {
                    mkdir($tempDir, 0775, true);
                }
                $tempPath = $tempDir . '/' . Str::uuid() . '.' . $file->getClientOriginalExtension();
                $file->move(dirname($tempPath), basename($tempPath));

                $diet     = DietPlan::find($request->diet_id);
                $dietName = $diet->diet_name ?? 'Diet Plan';

                SendDietAttachmentEmailJob::dispatch(
                    array_map('intval', $request->member_id),
                    $dietName,
                    $tempPath,
                    $originalName,
                )->afterResponse();
            }

            return response()->json([
                'code'    => 200,
                'message' => 'Members assigned successfully to diet.' . ($request->hasFile('attachment') ? ' Diet plan email will be sent shortly.' : ''),
                'data'    => $assignments,
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
}
