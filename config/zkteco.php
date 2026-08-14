<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ZKTeco Biometric Device Connection
    |--------------------------------------------------------------------------
    |
    | Used when your Laravel app runs on the SAME network as the device (e.g.
    | local server or office PC). On a remote VPS, the device is usually not
    | reachable; use device push or a local sync agent instead (see docs).
    |
    */

    'host' => env('ZK_TECO_HOST', '202.141.11.112'),
    'port' => env('ZK_TECO_PORT', 4370),

    /*
    |--------------------------------------------------------------------------
    | Connection timeout (seconds)
    |--------------------------------------------------------------------------
    */
    'timeout' => env('ZK_TECO_TIMEOUT', 5),

];
