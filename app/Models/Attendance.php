<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'user_type',
        'date',
        'check_in',
        'check_out',
        'status',
        'work_hours',
        'remarks',
        'location',
        'device_id',
        'ip_address'
    ];

    public function members(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'user_id', 'id');
    }
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'user_id', 'id');
    }
    
}
