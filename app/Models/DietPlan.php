<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DietPlan extends Model
{
    use HasFactory;
    protected $fillable = [
        'diet_name', 'days',
        'preworkout', 'preworkout_time',
        'post_workout', 'post_workout_time',
        'breakfast', 'breakfast_time',
        'morning_snaks', 'morning_snaks_time',
        'evening_snaks1', 'evening_snaks1_time',
        'evening_snaks2', 'evening_snaks2_time',
        'dinner', 'dinner_time',
        'meal1', 'meal1_time',
        'meal2', 'meal2_time',
    ];
    protected $casts = [
        'days' => 'array',
    ];
}
