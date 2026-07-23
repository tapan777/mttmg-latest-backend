<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'exercise_name',
        'description',
        'instructions',
        'sets',
        'reps',
        'duration',
    ];

    public function members()
    {
        return $this->belongsToMany(Member::class, 'exercise_user_assignments', 'exercise_id', 'member_id')
            ->withTimestamps();
    }

    public function assignments()
    {
        return $this->hasMany(ExerciseUserAssignment::class, 'exercise_id');
    }
}
