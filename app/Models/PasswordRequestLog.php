<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PasswordRequestLog extends Model
{
    use HasFactory;
    
    protected $table = 'tbl_password_request_log'; // Table name

    protected $fillable = [
        'req_type',
        'org_id',
        'user_id',
        'email',
        'token'
    ];
}
