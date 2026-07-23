<?php

namespace App\Http\Controllers;

use App\Models\OperationLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OperationLogController extends Controller
{
    /**
     * List all operation logs with optional filters.
     * POST/GET: user_id, date_from, date_to, search (path/action), limit, index
     */
    public function index(Request $request)
    {
        $limit = max(1, min(100, (int) $request->input('limit', 20)));
        $index = (int) $request->input('index', 0);
        $userId = $request->input('user_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $search = $request->input('search');

        $query = OperationLog::with('user:id,name,email,user_name,role');

        if ($userId !== null && $userId !== '') {
            $query->where('user_id', (int) $userId);
        }
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', Carbon::parse($dateFrom)->toDateString());
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', Carbon::parse($dateTo)->toDateString());
        }
        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('path', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('method', 'like', "%{$search}%");
            });
        }

        $total = $query->count();
        $logs = $query->orderByDesc('created_at')
            ->offset($index)
            ->limit($limit)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'user_id' => $log->user_id,
                    'user_name' => $log->user ? $log->user->name : null,
                    'user_email' => $log->user ? $log->user->email : null,
                    'role' => $log->user ? $log->user->role : null,
                    'action' => $log->action,
                    'method' => $log->method,
                    'path' => $log->path,
                    'request_summary' => $log->request_summary,
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent,
                    'created_at' => $log->created_at?->toIso8601String(),
                ];
            });

        return response()->json([
            'data' => $logs,
            'total_count' => $total,
            'code' => 200,
        ], 200);
    }

    /**
     * Get list of users for filter dropdown (users who have logs).
     */
    public function usersWithLogs(Request $request)
    {
        $userIds = OperationLog::whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');
        $users = User::whereIn('id', $userIds)
            ->select('id', 'name', 'email', 'user_name', 'role')
            ->orderBy('name')
            ->get();
        return response()->json([
            'data' => $users,
            'code' => 200,
        ], 200);
    }

    /**
     * Delete operation logs older than 1 month (previous 1 month data).
     * Call when user clicks "Delete previous 1 month data".
     */
    public function deleteOldLogs(Request $request)
    {
        $before = Carbon::now()->subMonth();
        $deleted = OperationLog::where('created_at', '<', $before)->delete();
        return response()->json([
            'message' => 'Operation logs older than 1 month have been deleted.',
            'deleted_count' => $deleted,
            'code' => 200,
        ], 200);
    }
}
