<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DietUserAssignment extends Model
{
    use HasFactory;
    protected $fillable = [
        'diet_id',
        'member_id',
    ];

    public function dietPlan()
    {
        return $this->belongsTo(DietPlan::class, 'diet_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }
}
