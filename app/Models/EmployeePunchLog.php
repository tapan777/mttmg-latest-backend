<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeePunchLog extends Model
{
    protected $table = 'tbl_employee_punch_log';

    protected $fillable = [
        'employee_id',
        'punch_date',
        'punch_time',
        'punch_type',
        'source',
        'device_sn',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
