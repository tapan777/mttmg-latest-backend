<?php

namespace App\Http\Controllers;

use App\Events\BulkEmailRequested;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BulkEmailController extends Controller
{
    public function send(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id'    => 'nullable|integer',
            'subject'    => 'required|string|max:255',
            'message'    => 'required|string',
            'member_ids' => 'nullable|array',
            'member_ids.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'code'    => 422,
            ], 200);
        }

        $data      = $validator->validated();
        $memberIds = $data['member_ids'] ?? null;

        $query = Member::query()
            ->where('status', 1)
            ->whereNotNull('email')
            ->where('email', '!=', '');

        if ($memberIds !== null && $memberIds !== []) {
            $query->whereIn('id', $memberIds);
        }

        $eligibleCount = (clone $query)->count();

        if ($eligibleCount === 0) {
            return response()->json([
                'message' => 'No active members with an email address match this request.',
                'code'    => 422,
            ], 200);
        }

        event(new BulkEmailRequested(
            $data['subject'],
            $data['message'],
            $memberIds,
            $data['user_id'] ?? null,
        ));

        return response()->json([
            'message'        => 'Bulk email has been queued and will be sent shortly.',
            'code'           => 200,
            'eligible_count' => $eligibleCount,
            'subject'        => $data['subject'],
        ], 200);
    }
}
