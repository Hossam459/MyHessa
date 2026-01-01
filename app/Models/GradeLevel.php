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

    /**
     * علاقة المواد الدراسية
     */
    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    /* =====================
       Constants (اختياري)
    ====================== */

    const STAGE_PRIMARY   = 'ابتدائي';
    const STAGE_PREP      = 'اعدادي';
    const STAGE_SECONDARY = 'ثانوي';

    /* =====================
       Scopes (اختياري)
    ====================== */

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
