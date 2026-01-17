<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\TeacherRating;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Teacher extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'user_id',
        'subject',
        'bio',
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
     * علاقة المدرس بالمستخدم
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

        public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'teacher_subject', 'teacher_id', 'subject_id');
    }

    public function ratings()
{
    return $this->hasMany(\App\Models\TeacherRating::class);
}

}
