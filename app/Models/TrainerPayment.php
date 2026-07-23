<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainerPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'trainer_package_id',
        'employee_id',
        'offer',
        'payble_amount',
        'total_payble_amount',
        'mode_of_payment',
        'paying_amount',
        'payment_type',
        'due',
        'date_of_payment',
        'start_date',
        'end_date',
        'package_status',
        'remarks',
        'package_status',
        'slot' // Add this to the fillable array
    ];

    public function members()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function trainer_packages() // Ensure this matches the relationship name used
    {
        return $this->belongsTo(TrainerPackage::class, 'trainer_package_id');
    }

    public function invoice()
    {
        return $this->hasMany(Invoice::class, 'trainer_package_payment_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
