<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradeLevel extends Model
{
    protected $table = 'grade_levels';

    protected $fillable = [
        'stage',
        'name_ar',
        'name_en',
        'sort_order',
    ];

    public $timestamps = false;


    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }


    const STAGE_PRIMARY   = 'ابتدائي';
    const STAGE_PREP      = 'اعدادي';
    const STAGE_SECONDARY = 'ثانوي';


    public function scopeStage($query, string $stage)
    {
        return $query->where('stage', $stage);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    public function gradeLevel()
{
    return $this->belongsTo(GradeLevel::class);
}
}
