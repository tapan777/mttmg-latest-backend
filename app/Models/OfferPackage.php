<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfferPackage extends Model
{
    use HasFactory;

    protected $fillable=[
        'name',
        'value',
        'description',
        'quantity',
        'duration',
        'status'
    ];

    public function nonRegisterMember(){
        return $this->hasMany(NonRegistreMember::class);
    }
}
