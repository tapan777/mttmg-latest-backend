<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Local EXE / desktop agent → shared hosting bridge
    |--------------------------------------------------------------------------
    |
    | Your Windows EXE (on the LAN with the ZKTeco device) calls these HTTPS
    | endpoints on shared hosting. Set the same secret in the EXE and here.
    |
    */
    'bridge_token' => env('BIOMETRIC_BRIDGE_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Connection mode
    |--------------------------------------------------------------------------
    | local — direct TCP to device (LAN only, uses jmrashed/zkteco SDK)
    | adms  — device pushes to this server (shared hosting, internet-facing)
    */
    'mode' => env('BIOMETRIC_MODE', 'adms'),

];
