<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZKTecoDevice extends Model
{
    protected $table      = 'zkteco_devices';
    protected $primaryKey = 'sn';
    protected $keyType    = 'string';
    public    $incrementing = false;

    protected $fillable = ['sn', 'ip', 'firmware', 'last_seen_at'];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    /** Device is considered online if it polled within the last 60 seconds. */
    public function isOnline(): bool
    {
        return $this->last_seen_at && $this->last_seen_at->diffInSeconds(now()) <= 60;
    }
}
