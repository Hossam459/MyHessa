<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Subject extends Model
{
    const STAGE_PRIMARY   = 'ابتدائي';
    const STAGE_PREP      = 'اعدادي';
    const STAGE_SECONDARY = 'ثانوي';

    protected $table = 'subjects';

    protected $fillable = [
        'stage',
        'grade',
        'name_ar',
        'name_en',
    ];

    public $timestamps = false;



    public function scopeStage($query, $stage)
    {
        return $query->where('stage', $stage);
    }

    public function scopeGrade($query, $grade)
    {
        return $query->where('grade', $grade);
    }



    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(
            Teacher::class,
            'teacher_subject',
            'subject_id',
            'teacher_id'
        );
    }

      public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class, 'grade_level_id');
    }
}