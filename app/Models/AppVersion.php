<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppVersion extends Model
{
    protected $fillable = [
        'platform',
        'latest_version',
        'min_supported_version',
        'force_update',
        'maintenance_mode',
        'update_url',
        'message_ar',
        'message_en',
    ];

    protected $casts = [
        'force_update' => 'boolean',
        'maintenance_mode' => 'boolean',
    ];
}