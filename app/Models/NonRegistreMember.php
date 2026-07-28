<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NonRegistreMember extends Model
{
    use HasFactory;
    
    protected $fillable=[
        'name',
        'phone',
        'email',
        'offer_package_id',
        'membership_number',
        'offer',
        'payble_amount',
        'paying_amount',
        'due',
        'start_date',
        'end_date',
        'payment_date',
        'card_number',
        'on_device'
    ];

    public function offerPackages(){
        return $this->belongsTo(OfferPackage::class,'offer_package_id');
    }

    public function invoice(){
        return $this->hasMany(Invoice::class);
    }

}
