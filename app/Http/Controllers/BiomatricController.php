<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Jmrashed\Zkteco\Lib\Helper\Util;
use Jmrashed\Zkteco\Lib\ZKTeco;

class BiomatricController extends Controller
{
    /** ZKTeco device LAN IP - direct connection requires static public IP or tunnel */
    const ZKTECO_IP = '192.168.1.201';
    const ZKTECO_PORT = 4370;

    protected $zk;

    public function __construct()
    {
        $this->zk = new ZKTeco(self::ZKTECO_IP, self::ZKTECO_PORT);
    }

    /**
     * Check connection with ZKTeco device.
     */
    public function checkConnection()
    {
        try {
            $connected = $this->zk->connect();
            if ($connected) {
                $this->zk->disconnect();
            }
            return response()->json([
                'success' => (bool) $connected,
                'connected' => (bool) $connected,
                'message' => $connected
                    ? 'Successfully connected to ZKTeco device.'
                    : 'Could not connect to ZKTeco device.',
                'device' => [
                    'ip' => self::ZKTECO_IP,
                    'port' => self::ZKTECO_PORT,
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::warning('ZKTeco connection check failed', [
                'ip' => self::ZKTECO_IP,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'connected' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
                'device' => [
                    'ip' => self::ZKTECO_IP,
                    'port' => self::ZKTECO_PORT,
                ],
            ], 200);
        }
    }

    public function zktConnect()
    {
        $result = $this->zk->connect();
        return $result;
    }
    public function receiveFromDevice(Request $request)
    {
        Log::info('ZKTeco ADMS push received', [
            'headers' => $request->headers->all(),
            'body'    => $request->all(),
            'raw'     => $request->getContent(),
        ]);

        try {
            // ZKTeco ADMS sends: UserID, AttTime, Status (0=check-in, 1=check-out)
            $userId    = $request->input('UserID') ?? $request->input('userid');
            $attTime   = $request->input('AttTime') ?? $request->input('timestamp');
            $status    = $request->input('Status') ?? $request->input('status');

            if (!$userId || !$attTime) {
                return response()->json(['message' => 'Invalid data'], 400);
            }

            $carbonTime = Carbon::parse($attTime);
            $date = $carbonTime->format('Y-m-d');
            $time = $carbonTime->format('H:i:s');

            $lastAttendance = Attendance::where('user_id', $userId)
                ->where('date', $date)
                ->latest()
                ->first();

            if ($status == 0 && (!$lastAttendance || $lastAttendance->check_out)) {
                Attendance::create([
                    'user_id' => $userId,
                    'date'     => $date,
                    'check_in' => $time,
                    'status'   => 'Present',
                ]);
            } elseif ($status == 1 && $lastAttendance && !$lastAttendance->check_out) {
                $lastAttendance->check_out  = $time;
                $checkIn  = Carbon::parse($lastAttendance->check_in);
                $checkOut = Carbon::parse($time);
                $lastAttendance->work_hours = $checkIn->diffInHours($checkOut) + round($checkIn->diffInMinutes($checkOut) % 60 / 60, 2);
                $lastAttendance->save();
            }

            return response()->json(['message' => 'Attendance saved'], 200);
        } catch (\Exception $e) {
            Log::error('ZKTeco ADMS error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Server error'], 500);
        }
    }

    //getting attendance
    public function get_attendance()
    {
        // set_time_limit(200);
        try {
            if ($this->zktConnect()) {
                return $this->zk->getAttendance();
            }
        } catch (\Exception $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                'code' => $exception->getCode(),
            ]);
        }
    }

    public function clear_attendance()
    {
        try {
            if ($this->zktConnect()) {
                return $this->zk->clearAttendance();
            }
        } catch (\Exception $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                'code' => $exception->getCode(),
            ]);
        }
    }

    public function setBiomatricData($user_id, $password, $name, $card)
    {
        if (self::zktConnect()) {
            return self::setUser($user_id, $name, $password, $card);
        } else {
            return false;
        }
    }

    public function setUserData(Request $request)
    {
        if (self::zktConnect()) {
            return self::setUser(
                $request->user_id,
                $request->name,
                $request->password,
                Util::LEVEL_USER
            );
        } else {
            return false;
        }
        $this->zk->setUser(
            $request->user_id,
            $request->user_id,
            $request->name,
            $request->password,
            Util::LEVEL_USER
        );
    }

    public function setUser($user_id, $name, $password, $card)
    {
        try {
            $success = $this->zk->setUser(
                $user_id,
                $user_id,
                $name,
                $password,
                Util::LEVEL_USER,
                $card
            );
            $this->zk->testVoice();
            return true;
        } catch (\Exception $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                'code' => $exception->getCode(),
            ]);
        }
    }
}
