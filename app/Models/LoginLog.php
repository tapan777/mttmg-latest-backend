<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoginLog extends Model
{
    use HasFactory;
    protected $table = 'login_logs'; // This line is optional if the table name matches the plural form of the model name

    protected $fillable = [
        'user_id',       // User ID
        'email',         // Email address
        'ip_address',    // IP Address
        'browser_name',  // Browser Name
        'city',          // City
        'country',       // Country
        'login_time',    // Login Time
        'logout_at',     // Logout Time
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
