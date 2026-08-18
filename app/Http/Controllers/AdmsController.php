<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\DeviceCommandLog;
use App\Models\Employee;
use App\Models\EmployeePunchLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AdmsController extends Controller
{
    const HEARTBEAT_INTERVAL = 10;

    public function handle(Request $request)
    {
        $sn    = $request->query('SN', 'unknown');
        $table = $request->query('table');
        $body  = $request->getContent();

        Log::info('ZKTeco IN', [
            'method' => $request->method(),
            'SN'     => $sn,
            'table'  => $table,
            'query'  => $request->query(),
            'body'   => $body,
        ]);

        if ($request->isMethod('GET')) {
            return $this->handleRegistration($sn);
        }

        return $this->handlePush($sn, $table, $body, $request);
    }

    private function handleRegistration(string $sn)
    {
        Cache::put("zkteco_last_seen_{$sn}", now()->toDateTimeString(), now()->addDay());

        $key      = "zkteco_commands_{$sn}";
        $commands = Cache::get($key, []);  // read only, don't delete yet

        // One-shot "resync" request (see requestFullResync): reporting a stamp
        // of 0 tells the device the server hasn't seen ANY of its attendance
        // log yet, so it re-pushes its full history on this check-in — the
        // only way to backfill missed punches, since the VPS can't open a
        // direct connection to the device's private LAN IP itself.
        $resyncKey   = "zkteco_force_resync_{$sn}";
        $forceResync = Cache::pull($resyncKey, false); // read-and-clear, one-shot

        Log::info('ZKTeco heartbeat', [
            'sn'               => $sn,
            'pending_commands' => count($commands),
            'commands'         => array_values($commands),
            'force_resync'     => $forceResync,
        ]);

        $body  = "GET OPTION FROM: {$sn}\r\n";
        $body .= 'ATTLOGStamp=' . ($forceResync ? '0' : '9999') . "\r\n";
        $body .= "OPERLOGStamp=9999\r\n";
        $body .= "ATTPHOTOStamp=9999\r\n";
        $body .= "ErrorDelay=30\r\n";
        $body .= "Delay=" . self::HEARTBEAT_INTERVAL . "\r\n";
        $body .= "TransTimes=00:00;14:05\r\n";
        $body .= "TransInterval=1\r\n";
        $body .= "TransFlag=1111000000\r\n";
        $body .= "TimeZone=5.5\r\n";
        $body .= "Realtime=1\r\n";
        $body .= "Encrypt=None\r\n";
        $body .= "ServerVer=2.4.1\r\n";
        $body .= "PushProtVer=2.4.1\r\n";
        $body .= "CommKey=0\r\n";

        foreach ($commands as $cmd) {
            $body .= $cmd . "\r\n";
        }

        Log::info('ZKTeco OUT', ['body' => $body]);

        return response($body, 200)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Length', strlen($body));
    }

    private function handlePush(string $sn, ?string $table, string $body, Request $request)
    {
        Log::info('ZKTeco device push', [
            'sn'    => $sn,
            'table' => $table,
            'query' => $request->query(),
            'body'  => $body,
        ]);

        if ($table === 'ATTLOG') {
            $this->processAttendanceLogs($body);
        }

        // Handle command ACK — device sends: ID=cmdID&Return=0 (success) or Return=-1 (fail)
        if (preg_match('/ID=(\d+).*Return=(-?\d+)/s', $body, $match)) {
            $cmdId  = $match[1];
            $result = (int) $match[2];

            Log::info('ZKTeco command ACK', [
                'sn'     => $sn,
                'cmd_id' => $cmdId,
                'result' => $result === 0 ? 'SUCCESS' : 'FAILED',
                'raw'    => $result,
            ]);

            if ($result === 0) {
                $key      = "zkteco_commands_{$sn}";
                $commands = Cache::get($key, []);
                unset($commands[$cmdId]);
                Cache::put($key, $commands, now()->addHours(24));
            }

            DeviceCommandLog::where('sn', $sn)->where('cmd_id', $cmdId)->update([
                'status'        => $result === 0 ? 'success' : 'failed',
                'error_message' => $result === 0 ? null : "Device returned code {$result}",
                'acked_at'      => now(),
            ]);

            return response("OK", 200)
                ->header('Content-Type', 'text/plain')
                ->header('Content-Length', 2);
        }

        return response("OK", 200)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Length', 2);
    }

    private function processAttendanceLogs(string $body)
    {
        // A forced full resync (ATTLOGStamp=0) can make the device replay its
        // entire stored history in one push — potentially thousands of lines.
        // The default PHP execution limit would kill this mid-way through,
        // silently leaving the backfill incomplete with no indication of
        // where it stopped. Uncapped only for this one request.
        set_time_limit(0);

        foreach (explode("\n", trim($body)) as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $parts = explode("\t", $line);
            if (count($parts) < 3) continue;

            $userId  = trim($parts[0]);
            $attTime = trim($parts[1]);
            $status  = (int) trim($parts[2]);

            try {
                $carbonTime = Carbon::parse($attTime);
                $date = $carbonTime->format('Y-m-d');
                $time = $carbonTime->format('H:i:s');

                // One Attendance row per user per date — an employee with morning +
                // evening slots punches in/out twice, but that's still a single
                // working day, not two. A later check-in on a day that already has
                // a row (e.g. the evening slot) must not create a second row; a
                // check-out always moves the same row's check_out forward.
                $lastAttendance = Attendance::where('user_id', $userId)
                    ->where('date', $date)
                    ->first();

                if ($status === 0) {
                    if (!$lastAttendance) {
                        // Resolve the punch to an employee or a member. The device PIN is
                        // the raw table `id` for BOTH tables with no offset/prefix, so an
                        // id can collide between an Employee and a Member. We keep the
                        // app's existing priority (Employee first) to avoid changing
                        // current behavior, but log loudly when both match so the
                        // collision is visible instead of being silently guessed.
                        // Non-register (walk-in) members are enrolled with PIN = 100000 + id
                        // (see NonRegistreMemberController::DEVICE_PIN_OFFSET), so a punch in
                        // that range is theirs, not an Employee/Member id collision.
                        $isNonRegisterMatch = ((int) $userId) >= \App\Http\Controllers\NonRegistreMemberController::DEVICE_PIN_OFFSET
                            && \App\Models\NonRegistreMember::where('id', ((int) $userId) - \App\Http\Controllers\NonRegistreMemberController::DEVICE_PIN_OFFSET)->exists();

                        $isEmployeeMatch = !$isNonRegisterMatch && Employee::where('id', $userId)->exists();
                        $isMemberMatch   = !$isNonRegisterMatch && \App\Models\Member::where('id', $userId)->exists();

                        if ($isEmployeeMatch && $isMemberMatch) {
                            Log::warning('ZKTeco attendance: ambiguous user_id matches both an Employee and a Member', [
                                'user_id' => $userId,
                                'date'    => $date,
                            ]);
                        }

                        $userType = $isNonRegisterMatch ? 'nonregister' : ($isEmployeeMatch ? 'employee' : ($isMemberMatch ? 'member' : null));

                        Attendance::create([
                            'user_id'   => $userId,
                            'user_type' => $userType,
                            'date'     => $date,
                            'check_in' => $time,
                            'status'   => 'Present',
                        ]);
                    } elseif ($time < $lastAttendance->check_in) {
                        // A resync can replay a genuinely earlier punch than whatever
                        // created today's row first (e.g. the device was down all
                        // morning, so the first LIVE punch after it came back created
                        // this row — then resync backfills the real, earlier morning
                        // check-in). The day's check-in should always be the earliest
                        // punch, not whichever happened to arrive at the server first.
                        $lastAttendance->check_in = $time;
                        $lastAttendance->save();
                    }
                } elseif ($status === 1) {
                    if (!$lastAttendance) {
                        // A resynced check-out with no matching check-in row at all
                        // (e.g. the check-in punch itself was missed/never captured)
                        // — still record what we have rather than silently dropping it.
                        $isNonRegisterMatch = ((int) $userId) >= \App\Http\Controllers\NonRegistreMemberController::DEVICE_PIN_OFFSET
                            && \App\Models\NonRegistreMember::where('id', ((int) $userId) - \App\Http\Controllers\NonRegistreMemberController::DEVICE_PIN_OFFSET)->exists();

                        $isEmployeeMatch = !$isNonRegisterMatch && Employee::where('id', $userId)->exists();
                        $isMemberMatch   = !$isNonRegisterMatch && \App\Models\Member::where('id', $userId)->exists();
                        $userType = $isNonRegisterMatch ? 'nonregister' : ($isEmployeeMatch ? 'employee' : ($isMemberMatch ? 'member' : null));

                        $lastAttendance = Attendance::create([
                            'user_id'   => $userId,
                            'user_type' => $userType,
                            'date'      => $date,
                            'check_out' => $time,
                            'status'    => 'Present',
                        ]);
                    } elseif (!$lastAttendance->check_out || $time > $lastAttendance->check_out) {
                        // Only advance check_out — a resync replaying an earlier
                        // historical check-out must not overwrite a later, already
                        // correct one (e.g. the evening slot's checkout arrived live
                        // before the morning slot's checkout gets backfilled).
                        $lastAttendance->check_out = $time;
                        $lastAttendance->save();
                    }
                }

                Log::info('ZKTeco attendance saved', ['user' => $userId, 'time' => $attTime, 'status' => $status]);

                // Punch log — every swipe for employees only
                if (Employee::where('id', $userId)->exists()) {
                    $punchType = $status === 1 ? 'out' : 'in';

                    // A full resync replays punches the device already sent before —
                    // without this check, every already-synced punch gets re-inserted
                    // as a duplicate row on each resync, bloating the table forever.
                    $alreadyLogged = EmployeePunchLog::where('employee_id', $userId)
                        ->where('punch_date', $date)
                        ->where('punch_time', $time)
                        ->where('punch_type', $punchType)
                        ->exists();

                    if (!$alreadyLogged) {
                        EmployeePunchLog::create([
                            'employee_id' => $userId,
                            'punch_date'  => $date,
                            'punch_time'  => $time,
                            'punch_type'  => $punchType,
                            'source'      => 'device',
                            'device_sn'   => null,
                        ]);
                    }

                    // Recompute total worked hours for the day as the sum of each
                    // in/out session's duration (morning + evening), not the raw
                    // span between first check-in and last check-out.
                    if ($status === 1 && $lastAttendance) {
                        $employee = Employee::find($userId);
                        $slotRanges = $employee ? array_values(array_filter(array_map(
                            fn ($s) => $this->parseSlotRange($s, $date),
                            [$employee->morning_slot, $employee->evening_slot]
                        ))) : [];

                        $dayPunches = EmployeePunchLog::where('employee_id', $userId)
                            ->where('punch_date', $date)
                            ->orderBy('punch_time')
                            ->get();
                        $totalMinutes = 0;
                        $openIn = null;
                        foreach ($dayPunches as $p) {
                            if ($p->punch_type === 'in') {
                                $punchTime = Carbon::parse($p->punch_time);
                                // A duplicate/double-scan "in" arriving seconds or a
                                // couple of minutes after the currently-open one is a
                                // device glitch, not a new session — ignore it rather
                                // than shifting the credited start time.
                                if ($openIn !== null && $openIn->diffInMinutes($punchTime) < 5) {
                                    continue;
                                }
                                $openIn = $punchTime;
                            } elseif ($p->punch_type === 'out' && $openIn) {
                                // An early check-in (within the 30-min grace before slot
                                // start) is valid attendance for the slot, but doesn't earn
                                // extra paid minutes — credit from the slot's official
                                // start, never earlier.
                                $creditedStart = $openIn;
                                foreach ($slotRanges as [$slotStart, $slotEnd]) {
                                    if ($openIn->between($slotStart->copy()->subMinutes(30), $slotEnd) && $openIn->lt($slotStart)) {
                                        $creditedStart = $slotStart;
                                        break;
                                    }
                                }
                                $totalMinutes += $creditedStart->diffInMinutes(Carbon::parse($p->punch_time));
                                $openIn = null;
                            }
                        }
                        $lastAttendance->work_hours = round($totalMinutes / 60, 2);
                        $lastAttendance->save();
                    }
                }
            } catch (\Exception $e) {
                Log::error('ZKTeco attendance error', ['error' => $e->getMessage(), 'line' => $line]);
            }
        }
    }

    /**
     * Mirrors AutoCheckoutEmployees::parseSlotRange — parses a slot string like
     * "6:00 AM - 2:00 PM" into [startCarbon, endCarbon] for the given date.
     */
    private function parseSlotRange(?string $slot, string $date): ?array
    {
        $slot = trim((string) $slot);
        if ($slot === '') {
            return null;
        }

        $parts = preg_split('/\s*[-–]\s*(?=\d)|(\s+to\s+)/i', $slot, 2);
        if (!$parts || count($parts) < 2) {
            return null;
        }

        $startStr = trim($parts[0]);
        $endStr   = trim($parts[1]);

        try {
            $start = Carbon::parse("$date $startStr");
            $end   = Carbon::parse("$date $endStr");

            if ($end->lt($start)) {
                $end->addDay();
            }

            return [$start, $end];
        } catch (\Throwable) {
            return null;
        }
    }

    // ─── APIs ────────────────────────────────────────────────────────────────

    public function deviceStatus(Request $request)
    {
        $sn       = $request->query('sn');
        $lastSeen = Cache::get("zkteco_last_seen_{$sn}");
        $online   = $lastSeen && Carbon::parse($lastSeen)->diffInSeconds(now()) < 60;

        return response()->json([
            'sn'        => $sn,
            'online'    => $online,
            'last_seen' => $lastSeen ?? 'Never',
            'message'   => $online ? 'Device is online' : 'Device offline or not connected',
        ]);
    }

    /**
     * "Refresh" — the VPS can't open a direct connection to the device (it
     * sits on a private LAN IP), so the only way to backfill any punches the
     * live push missed is to ask the device itself to re-send its full
     * attendance history on its next check-in. Sets a one-shot flag that
     * handleRegistration() picks up and clears automatically.
     */
    public function requestFullResync(Request $request)
    {
        $sn = $request->query('sn', config('zkteco.sn', 'HKQ8241900193'));
        Cache::put("zkteco_force_resync_{$sn}", true, now()->addHours(1));

        // The flag above only takes effect once the device itself checks in
        // (handleRegistration()) — if it hasn't been seen recently, it may be
        // hung/offline, in which case queuing this flag won't do anything
        // until the device recovers on its own. Surface that honestly instead
        // of always claiming success.
        $lastSeen = Cache::get("zkteco_last_seen_{$sn}");
        $secondsSinceLastSeen = $lastSeen ? Carbon::parse($lastSeen)->diffInSeconds(now()) : null;
        $isStale = $secondsSinceLastSeen === null || $secondsSinceLastSeen > 60;

        if ($isStale) {
            return response()->json([
                'message' => $lastSeen
                    ? "Resync queued, but the device hasn't checked in for " . (int) ($secondsSinceLastSeen / 60) . " minute(s) — it may be hung or offline. The resync will only run once the device reconnects on its own; no amount of retrying this request will speed that up."
                    : "Resync queued, but this device has never checked in — verify its network connection/SN configuration ({$sn}).",
                'device_online' => false,
                'last_seen'     => $lastSeen ?? 'Never',
                'code'          => 200,
            ], 200);
        }

        return response()->json([
            'message' => 'Resync requested — the device will re-send its full attendance history on its next check-in (within ~' . self::HEARTBEAT_INTERVAL . ' seconds). Any punches missed by the live sync, for both members and employees, will be backfilled automatically once that happens.',
            'device_online' => true,
            'last_seen'     => $lastSeen,
            'code'    => 200,
        ], 200);
    }

    public function addUser(Request $request)
    {
        $sn       = $request->input('sn');
        $uid      = $request->input('user_id');
        $safeName = str_replace(' ', '_', $request->input('name'));
        $card     = $request->input('card', '0');
        $id       = time();

        $params = [
            "PIN={$uid}",
            "Name={$safeName}",
            "Card={$card}",
            "Pri=0",
        ];
        if (!empty($request->input('password'))) {
            $params[] = "Pass={$request->input('password')}";
        }

        $command = "C:{$id}:DATA UPDATE USERINFO\t" . implode("\t", $params);

        self::queueCommand($sn, $id, $command);

        return response()->json([
            'success' => true,
            'message' => 'Add user queued. Device will sync within ' . self::HEARTBEAT_INTERVAL . ' seconds.',
        ]);
    }

    public function deleteUser(Request $request)
    {
        $sn  = $request->input('sn');
        $uid = $request->input('user_id');
        $id  = time();

        self::queueCommand($sn, $id, "C:{$id}:DATA DELETE USERINFO\tPIN={$uid}");

        return response()->json(['success' => true, 'message' => 'Delete user queued.']);
    }

    public function clearAttendance(Request $request)
    {
        $sn = $request->input('sn');
        $id = time();

        self::queueCommand($sn, $id, "C:{$id}:DATA CLEAR ATTLOG");

        return response()->json(['success' => true, 'message' => 'Clear attendance queued.']);
    }

    public function rebootDevice(Request $request)
    {
        $sn = $request->input('sn');
        $id = time();

        self::queueCommand($sn, $id, "C:{$id}:REBOOT");

        return response()->json(['success' => true, 'message' => 'Reboot queued.']);
    }

    public function unlockDoor(Request $request)
    {
        $sn = $request->input('sn', config('zkteco.sn', 'HKQ8241900193'));
        $id = time();

        self::queueCommand($sn, $id, "C:{$id}:AC_UNLOCK");

        return response()->json(['success' => true, 'message' => 'Door unlock queued.']);
    }

    public static function queueCommand(string $sn, int $id, string $command): void
    {
        $key           = "zkteco_commands_{$sn}";
        $commands      = Cache::get($key, []);
        $commands[$id] = $command;  // keyed by ID for ACK-based removal
        Cache::put($key, $commands, now()->addHours(24));

        Log::info('ZKTeco command queued', [
            'sn'             => $sn,
            'id'             => $id,
            'command'        => $command,
            'total_in_queue' => count($commands),
        ]);

        [$action, $pin, $card] = self::parseCommandForLog($command);
        DeviceCommandLog::create([
            'sn'          => $sn,
            'cmd_id'      => $id,
            'action'      => $action,
            'pin'         => $pin,
            'card_number' => $card,
            'command'     => $command,
            'status'      => 'queued',
        ]);
    }

    private static function parseCommandForLog(string $command): array
    {
        $action = 'unknown';
        if (str_contains($command, 'DATA UPDATE USERINFO')) {
            $action = 'add_or_update_user';
        } elseif (str_contains($command, 'DATA DELETE USERINFO')) {
            $action = 'delete_user';
        } elseif (str_contains($command, 'DATA CLEAR ATTLOG')) {
            $action = 'clear_attendance';
        } elseif (str_contains($command, 'REBOOT')) {
            $action = 'reboot';
        } elseif (str_contains($command, 'AC_UNLOCK')) {
            $action = 'unlock_door';
        }

        $pin  = null;
        $card = null;
        if (preg_match('/PIN=([^\t]+)/', $command, $m)) {
            $pin = $m[1];
        }
        if (preg_match('/Card=([^\t]+)/', $command, $m)) {
            $card = $m[1];
        }

        return [$action, $pin, $card];
    }

    // ─── Bridge endpoints (local PC polls these) ─────────────────────────────

    public function bridgePending(Request $request)
    {
        if ($request->query('secret') !== config('zkteco.bridge_secret', 'bridge_secret_key')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $sn  = $request->query('sn', config('zkteco.sn', 'HKQ8241900193'));
        $key = "zkteco_commands_{$sn}";

        $commands = Cache::get($key, []);

        // Remove commands from queue immediately so the bridge doesn't repeat them
        if (!empty($commands)) {
            Cache::forget($key);
            Log::info('ZKTeco bridge: commands dispatched to bridge app', [
                'sn'    => $sn,
                'count' => count($commands),
            ]);

            DeviceCommandLog::where('sn', $sn)
                ->whereIn('cmd_id', array_keys($commands))
                ->where('status', 'queued')
                ->update(['status' => 'dispatched', 'dispatched_at' => now()]);
        }

        $parsed = [];
        foreach ($commands as $id => $cmd) {
            $parsed[] = $this->parseCommandString((int) $id, $cmd);
        }

        return response()->json(['sn' => $sn, 'commands' => $parsed]);
    }

    public function bridgeAck(Request $request)
    {
        if ($request->input('secret') !== config('zkteco.bridge_secret', 'bridge_secret_key')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $sn    = $request->input('sn', config('zkteco.sn', 'HKQ8241900193'));
        $cmdId = $request->input('cmd_id');
        $ok    = (bool) $request->input('success', false);
        $error = $request->input('error');

        Log::info('ZKTeco bridge ACK', ['sn' => $sn, 'cmd_id' => $cmdId, 'success' => $ok, 'error' => $error]);

        if ($ok) {
            $key      = "zkteco_commands_{$sn}";
            $commands = Cache::get($key, []);
            unset($commands[$cmdId]);
            Cache::put($key, $commands, now()->addHours(24));
        }

        DeviceCommandLog::where('sn', $sn)->where('cmd_id', $cmdId)->update([
            'status'        => $ok ? 'success' : 'failed',
            'error_message' => $ok ? null : ($error ?: 'Bridge reported failure'),
            'acked_at'      => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * List device command logs (add/update/delete user, etc.) with filters.
     * POST/GET: status, action, search (pin/card_number/sn), date_from, date_to, limit, index
     */
    public function commandLogs(Request $request)
    {
        $limit  = max(1, min(100, (int) $request->input('limit', 20)));
        $index  = (int) $request->input('index', 0);
        $status = $request->input('status');
        $action = $request->input('action');
        $search = $request->input('search');
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        $query = DeviceCommandLog::query();

        if ($status) {
            $query->where('status', $status);
        }
        if ($action) {
            $query->where('action', $action);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('pin', 'like', "%{$search}%")
                    ->orWhere('card_number', 'like', "%{$search}%")
                    ->orWhere('sn', 'like', "%{$search}%");
            });
        }
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', Carbon::parse($dateFrom)->toDateString());
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', Carbon::parse($dateTo)->toDateString());
        }

        $total = $query->count();
        $logs = $query->orderByDesc('created_at')
            ->offset($index)
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $logs,
            'total_count' => $total,
            'code' => 200,
        ], 200);
    }

    private function parseCommandString(int $id, string $cmd): array
    {
        // Format: C:ID:COMMAND_TYPE\tPARAM=val\t...
        if (preg_match('/^C:\d+:(.+)/', $cmd, $m)) {
            $parts   = explode("\t", $m[1]);
            $type    = array_shift($parts);
            $params  = [];
            foreach ($parts as $part) {
                if (strpos($part, '=') !== false) {
                    [$k, $v]    = explode('=', $part, 2);
                    $params[$k] = $v;
                }
            }
            return ['id' => $id, 'type' => $type, 'params' => $params];
        }
        return ['id' => $id, 'type' => 'UNKNOWN', 'params' => []];
    }
}
