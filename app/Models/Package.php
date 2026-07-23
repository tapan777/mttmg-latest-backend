<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasFactory;

    protected $fillable=[
        'name',
        'duration', // in days
        'package_amount',
        'admission_value',
        'status',
        'package_type',
    ];

    protected $attributes=[
        'status' => 1,
        'package_type' => 0
    ];

    public function payments(){
        return $this->hasOne(Payment::class);
    }
}
