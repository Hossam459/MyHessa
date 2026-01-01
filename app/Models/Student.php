<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Student extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'user_id',
        'grade_level_id',
        'parent_name',
        'parent_contact',
        'birth_day',
        'mobile_number',
        'first_name',
        'last_name',
        'goverment_id',
        'city_id',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * علاقة الطالب بالمستخدم
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * علاقة الطالب بالصف الدراسي
     */
    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }
}
