<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExerciseUserAssignment extends Model
{
    use HasFactory;

    protected $table = 'exercise_user_assignments';

    protected $fillable = [
        'exercise_id',
        'member_id',
    ];

    public function exercise()
    {
        return $this->belongsTo(Exercise::class, 'exercise_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }
}
