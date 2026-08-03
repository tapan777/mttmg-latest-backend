<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceCommandLog extends Model
{
    protected $fillable = [
        'sn',
        'cmd_id',
        'action',
        'pin',
        'card_number',
        'command',
        'status',
        'error_message',
        'dispatched_at',
        'acked_at',
    ];
}
