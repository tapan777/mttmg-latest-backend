<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappTemplate extends Model
{
    protected $table = 'tbl_whatsapp_templates';

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'variables_count',
        'variable_labels',
        'used_in',
        'is_active',
    ];

    protected $casts = [
        'variable_labels' => 'array',
        'is_active'       => 'boolean',
        'variables_count' => 'integer',
    ];
}
