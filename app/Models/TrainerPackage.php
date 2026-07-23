<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainerPackage extends Model
{
    use HasFactory;

    protected $fillable=[
        'name',
        'duration', // in days
        'package_amount',
        'admission_value',
        'status'
    ];

    protected $attributes=[
        'status' => 1
    ];
}
