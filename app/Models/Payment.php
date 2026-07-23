<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'package_id',
        'member_id',
        'bill_no',
        'offer',
        'payble_amount',
        'total_payble_amount',
        'mode_of_payment',
        'paying_amount',
        'yearly_membership_included',
        'due',
        'payment_type',
        'date_of_payment',
        'start_date',
        'end_date',
        'package_status',
        'remarks',
        'admission_payment'
    ];

    public function members()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function packages()
    {
        return $this->belongsTo(Package::class, 'package_id');
    }

    public function invoice()
    {
        return $this->hasMany(Invoice::class, 'main_package_payment_id');
    }
}
