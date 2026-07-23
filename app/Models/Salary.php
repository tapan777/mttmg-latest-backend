<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Salary extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'work_hours',
        'hours_per_day',
        'salary_per_day',
        'total_salary',
        'generated_date',
        'working_days', // Add working_days here
    ];

    // Optional: Add relationship to User model
  
}
