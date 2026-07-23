<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZKTecoCommand extends Model
{
    protected $table    = 'zkteco_commands';
    protected $fillable = ['sn', 'command', 'status'];
}
