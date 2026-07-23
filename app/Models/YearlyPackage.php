<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class YearlyPackage extends Model
{
    use HasFactory;
    protected $fillable = [
        'member_id',
        'package_amount',
        'start_date',
        'end_date',
        'payment_mode',
        'payment_date',
        'included_in_main_payment',
        'admission_value_id'
    ];

    public function invoice()
    {
        return $this->hasMany(Invoice::class, 'yearly_package_payment_id');
    }

    public function members()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }
}
