<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'membership_number',
        'name',
        'email',
        'phone',
        'alternate_phone',
        'image',
        'dob',
        'height',
        'weight',
        'sex',
        'start_date',
        'end_date',
        'wedding_date',
        'occupation',
        'address',
        'status',
        'pause',
        'resume',
        'identification_type',
        'identification_id',
        'joining_date',
        'card_number',
        'on_device',
    ];

    protected $attributes = [
        'status' => 1
    ];

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function pt_payments()
    {
        return $this->hasMany(TrainerPayment::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function trainerPackage()
    {
        return $this->belongsTo(TrainerPackage::class);
    }

    public function invoice()
    {
        return $this->hasMany(Invoice::class);
    }

    public function atendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function steamBaths() // Corrected relationship name
    {
        return $this->hasMany(SteamBath::class, 'member_id'); // 'member_id' is the foreign key in the steam_baths table
    }
}
