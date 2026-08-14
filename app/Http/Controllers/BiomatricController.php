<?php

namespace App\Http\Controllers;

use Jmrashed\Zkteco\Lib\Helper\Util;
use Jmrashed\Zkteco\Lib\ZKTeco;

class BiomatricController extends Controller
{
    /**
     * ZKTeco device LAN IP. NOTE: this only works if this code runs on a
     * machine on the SAME local network as the device — a direct connection
     * from the VPS to this private IP is not possible. Device attendance
     * sync goes through AdmsController's ADMS push protocol instead (see
     * AdmsController::requestFullResync for backfilling missed punches).
     */
    const ZKTECO_IP = '192.168.1.201';
    const ZKTECO_PORT = 4370;

    protected $zk;

    public function __construct()
    {
        $this->zk = new ZKTeco(self::ZKTECO_IP, self::ZKTECO_PORT);
    }

    public function zktConnect()
    {
        $result = $this->zk->connect();
        return $result;
    }

    public function setBiomatricData($user_id, $password, $name, $card)
    {
        if (self::zktConnect()) {
            return self::setUser($user_id, $name, $password, $card);
        } else {
            return false;
        }
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
