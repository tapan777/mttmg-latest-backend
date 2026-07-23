<?php

namespace App\Http\Controllers;

use App\Events\BulkHolidayWhatsAppRequested;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BulkHolidayWhatsAppController extends Controller
{
    /** Testing: only the first N active members with phone receive WhatsApp (one API call per number). */
    private const TEST_SEND_MEMBER_LIMIT = 10;

    public function send(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|integer',
            'variable_1' => 'required|string|max:500',
            'variable_2' => 'required|string|max:500',
            'variable_3' => 'required|string|max:500',
            'template_key' => ['required', 'string', 'max:120', 'regex:/^[a-zA-Z0-9_\-]+$/'],
            'member_ids' => 'nullable|array',
            'member_ids.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'code' => 422,
            ], 200);
        }

        $data = $validator->validated();
        $memberIds = $data['member_ids'] ?? null;

        $query = Member::query()
            ->where('status', 1)
            ->whereNotNull('phone')
            ->where('phone', '!=', '');

        if ($memberIds !== null && $memberIds !== []) {
            $query->whereIn('id', $memberIds);
        }

        $eligibleCount = (clone $query)->count();
        if ($eligibleCount === 0) {
            return response()->json([
                'message' => 'No active members with a phone number match this request.',
                'code' => 422,
            ], 200);
        }

        $sendMemberIds = (clone $query)
            ->orderBy('id')
            ->limit(self::TEST_SEND_MEMBER_LIMIT)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();

        $recipients = Member::query()
            ->whereIn('id', $sendMemberIds)
            ->orderBy('id')
            ->get(['id', 'name', 'phone'])
            ->map(static fn (Member $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'phone' => $m->phone,
            ])
            ->values()
            ->all();

        event(new BulkHolidayWhatsAppRequested(
            $data['template_key'],
            [$data['variable_1'], $data['variable_2'], $data['variable_3']],
            $sendMemberIds,
            $data['user_id'] ?? null,
        ));

        return response()->json([
            'message' => 'Bulk holiday WhatsApp has been queued (testing: up to ' . self::TEST_SEND_MEMBER_LIMIT . ' members, one message per number).',
            'code' => 200,
            'recipient_count' => count($sendMemberIds),
            'eligible_member_count' => $eligibleCount,
            'send_limit' => self::TEST_SEND_MEMBER_LIMIT,
            'template_key' => $data['template_key'],
            'recipients' => $recipients,
        ], 200);
    }
}
