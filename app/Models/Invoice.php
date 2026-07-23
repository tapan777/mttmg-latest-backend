<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'main_package_payment_id',
        'trainer_package_payment_id',
        'yearly_package_payment_id',
        'non_registre_member_id',
        'steam_bath_id',
        'steam_bath_amount',
        'steam_bath_payment_date',
        'steam_bath_mode_of_payment',
    ];

    protected $casts = [
        'steam_bath_payment_date' => 'date',
        'steam_bath_amount' => 'decimal:2',
    ];

    public function members(){
       return $this->belongsTo(Member::class,'member_id');
    }
    public function mainPackagePayments(){
        return $this->belongsTo(Payment::class,'main_package_payment_id');
    }
    public function trainerPackagePayments(){
        return $this->belongsTo(TrainerPayment::class,'trainer_package_payment_id');
    }

    public function yearlyPackagePayments(){
        return $this->belongsTo(YearlyPackage::class,'yearly_package_payment_id');
    }

    public function nonRegisterMembers(){
        return $this->belongsTo(NonRegistreMember::class,'non_registre_member_id');
    }

    public function steamBath()
    {
        return $this->belongsTo(SteamBath::class, 'steam_bath_id');
    }
}
