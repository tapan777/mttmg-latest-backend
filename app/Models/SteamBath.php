<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SteamBath extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'package_name',
        'total_bath',
        'used_bath',
        'amount',
        'payment_date',
    ];

    protected $casts = [
        'payment_date' => 'date',
    ];

    public function members()
    {
        return $this->belongsTo(Member::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'steam_bath_id');
    }
}
