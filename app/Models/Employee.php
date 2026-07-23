<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\EmployeePunchLog;

class Employee extends Model
{
    use HasFactory;

    public function punchLogs()
    {
        return $this->hasMany(EmployeePunchLog::class, 'employee_id');
    }

    protected $fillable = [
        'name',
        'email',
        'phone',
        'image',
        'salary',
        'designation',
        'designation_slug',
        'blood_group',
        'joining_date',
        'address',
        'card_number',
        'morning_slot', // Added morning_slot
        'evening_slot', // Added evening_slot
        'excuse_time',  // Added excuse_time
    ];
}