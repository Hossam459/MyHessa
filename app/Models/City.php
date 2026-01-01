<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class City extends Model
{
    protected $fillable = [
        'governorate_id',
        'name_ar',
        'name_en',
        'latitude',
        'longitude',
    ];

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }
}
