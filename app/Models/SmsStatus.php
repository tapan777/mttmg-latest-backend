<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsStatus extends Model
{
    use HasFactory;

    // Define the table associated with the model (optional if the table name follows Laravel's convention)
    protected $table = 'sms_status';

    // Specify the fields that are mass assignable
    protected $fillable = [
        'amount',
    ];

    // You can also specify the timestamps if you don't want them to be included (Laravel includes them by default)
    public $timestamps = true;
}
