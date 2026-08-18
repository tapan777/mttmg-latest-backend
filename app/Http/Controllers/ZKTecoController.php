<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeePunchLog;
use App\Models\ZKTecoCommand;
use App\Models\ZKTecoDevice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ZKTecoController extends Controller
{
    // -------------------------------------------------------------------------
    // Heartbeat helper — called on every device contact
    // -------------------------------------------------------------------------

    private function heartbeat(Request $request, string $sn, array $extra = []): void
    {
        ZKTecoDevice::updateOrCreate(
            ['sn' => $sn],
            array_merge([
                'ip'           => $request->ip(),
                'last_seen_at' => now(),
            ], $extra)
        );
    }

    // -------------------------------------------------------------------------
    // Device registration — GET /iclock/cdata
    // -------------------------------------------------------------------------

    public function register(Request $request)
    {
        $sn       = $request->query('SN', 'UNKNOWN');
        $firmware = $request->query('PushVer') ?? $request->query('FirmVer') ?? null;

        $this->heartbeat($request, $sn, ['firmware' => $firmware]);

        Log::info("ZKTeco device registered: {$sn}", [
            'ip'     => $request->ip(),
            'params' => $request->query(),
        ]);

        $body = implode("\n", [
            "GET OPTION FROM: {$sn}",
            "ATTLOGStamp=9999999999",
            "OPERLOGStamp=9999999999",
            "ATTPHOTOStamp=9999999999",
            "ErrorDelay=30",
            "Delay=10",
            "TransFlag=TransData AttLog",
            "TimeOut=30",
            "ServerVer=2.4.1 2015-04-27",
            "PushOptionsFlag=1",
        ]);

        return response($body, 200)->header('Content-Type', 'text/plain');
    }

    // -------------------------------------------------------------------------
    // Attendance push — POST /iclock/cdata
    // -------------------------------------------------------------------------

    public function push(Request $request)
    {
        $table = strtoupper($request->query('table', ''));
        $sn    = $request->query('SN', 'UNKNOWN');
        $body  = $request->getContent();

        $this->heartbeat($request, $sn);

        Log::info("ZKTeco push received from {$sn}", ['table' => $table, 'body' => $body]);

        if ($table !== 'ATTLOG') {
            return response("OK", 200)->header('Content-Type', 'text/plain');
        }

        $lines = preg_split('/\r?\n/', trim($body));
        $saved = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = preg_split('/\t/', $line);
            if (count($parts) < 2) {
                continue;
            }

            $pin         = trim($parts[0]);
            $datetime    = trim($parts[1]);
            $inOutStatus = isset($parts[3]) ? (int) trim($parts[3]) : 0;

            if ($pin === '' || $datetime === '') {
                continue;
            }

            try {
                $timestamp = Carbon::parse($datetime);
                $date      = $timestamp->format('Y-m-d');
                $time      = $timestamp->format('H:i:s');

                $last = Attendance::where('user_id', $pin)
                    ->where('date', $date)
                    ->latest()
                    ->first();

                if ($inOutStatus === 1) {
                    if ($last && $last->check_out === null) {
                        $checkIn  = Carbon::parse($last->check_in);
                        $checkOut = Carbon::parse($time);

                        $last->check_out  = $time;
                        $last->work_hours = round($checkIn->diffInMinutes($checkOut) / 60, 2);
                        $last->device_id  = $sn;
                        $last->save();
                        $saved++;
                    }
                } else {
                    if (!$last || $last->check_out !== null) {
                        // Device PIN is the raw id for both Employee and Member with no
                        // offset, so ids can collide between the two tables. Keep the
                        // app's existing priority (Employee first) but log loudly on
                        // collision so it's visible instead of silently guessed.
                        $isNonRegisterMatch = ((int) $pin) >= \App\Http\Controllers\NonRegistreMemberController::DEVICE_PIN_OFFSET
                            && \App\Models\NonRegistreMember::where('id', ((int) $pin) - \App\Http\Controllers\NonRegistreMemberController::DEVICE_PIN_OFFSET)->exists();

                        $isEmployeeMatch = !$isNonRegisterMatch && Employee::where('id', $pin)->exists();
                        $isMemberMatch   = !$isNonRegisterMatch && \App\Models\Member::where('id', $pin)->exists();

                        if ($isEmployeeMatch && $isMemberMatch) {
                            Log::warning('ZKTeco attendance: ambiguous user_id matches both an Employee and a Member', [
                                'user_id' => $pin,
                                'date'    => $date,
                            ]);
                        }

                        $userType = $isNonRegisterMatch ? 'nonregister' : ($isEmployeeMatch ? 'employee' : ($isMemberMatch ? 'member' : null));

                        Attendance::create([
                            'user_id'    => $pin,
                            'user_type'  => $userType,
                            'date'       => $date,
                            'check_in'   => $time,
                            'status'     => 'Present',
                            'device_id'  => $sn,
                            'ip_address' => $request->ip(),
                        ]);
                        $saved++;
                    }
                }

                // Punch log — record every swipe for employees only (does not affect work hours)
                if (Employee::where('id', $pin)->exists()) {
                    EmployeePunchLog::create([
                        'employee_id' => $pin,
                        'punch_date'  => $date,
                        'punch_time'  => $time,
                        'punch_type'  => $inOutStatus === 1 ? 'out' : 'in',
                        'source'      => 'device',
                        'device_sn'   => $sn,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("ZKTeco attendance parse error", ['line' => $line, 'error' => $e->getMessage()]);
            }
        }

        Log::info("ZKTeco: saved {$saved} records from {$sn}");

        return response("OK: {$saved}", 200)->header('Content-Type', 'text/plain');
    }

    // -------------------------------------------------------------------------
    // Command poll — POST /iclock/getrequest
    // -------------------------------------------------------------------------

    public function getRequest(Request $request)
    {
        $sn = $request->query('SN', $request->input('SN', 'UNKNOWN'));

        $this->heartbeat($request, $sn);

        $cmd = ZKTecoCommand::where('status', 'pending')
            ->where(function ($q) use ($sn) {
                $q->where('sn', $sn)->orWhereNull('sn');
            })
            ->oldest()
            ->first();

        if (!$cmd) {
            return response("OK", 200)->header('Content-Type', 'text/plain');
        }

        $cmd->update(['status' => 'sent']);

        return response("C:{$cmd->id}:{$cmd->command}", 200)
            ->header('Content-Type', 'text/plain');
    }

    // -------------------------------------------------------------------------
    // Command acknowledgement — POST /iclock/devicecmd
    // -------------------------------------------------------------------------

    public function cmdResponse(Request $request)
    {
        $sn   = $request->query('SN', 'UNKNOWN');
        $body = $request->getContent();

        $this->heartbeat($request, $sn);

        parse_str($body, $params);
        $id     = $params['ID']     ?? null;
        $result = $params['Return'] ?? null;

        if ($id) {
            ZKTecoCommand::where('id', $id)->update(['status' => 'done']);
        }

        Log::info("ZKTeco cmdResponse from {$sn}", ['id' => $id, 'return' => $result]);

        return response("OK", 200)->header('Content-Type', 'text/plain');
    }

    // -------------------------------------------------------------------------
    // Device status — GET /api/biomatric/device-status
    // -------------------------------------------------------------------------

    public function deviceStatus()
    {
        $devices = ZKTecoDevice::all()->map(function ($d) {
            $online = $d->isOnline();
            return [
                'sn'               => $d->sn,
                'ip'               => $d->ip,
                'firmware'         => $d->firmware,
                'online'           => $online,
                'status_label'     => $online ? 'Online' : 'Offline',
                'last_seen_at'     => $d->last_seen_at?->toDateTimeString(),
                'last_seen_ago'    => $d->last_seen_at ? $d->last_seen_at->diffForHumans() : 'Never',
                'pending_commands' => ZKTecoCommand::where('status', 'pending')
                    ->where(function ($q) use ($d) {
                        $q->where('sn', $d->sn)->orWhereNull('sn');
                    })
                    ->count(),
            ];
        });

        return response()->json([
            'code'    => 200,
            'devices' => $devices,
        ]);
    }
}
