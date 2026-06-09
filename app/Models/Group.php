<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
class Group extends Model {
    protected $fillable = ['name','description','subject_id','grade_level_id','max_students','price','teacher_id','start_date','end_date'];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
    ];

    public function schedules() { return $this->hasMany(GroupSchedule::class); }
    public function latestLesson(): HasOne { return $this->hasOne(Lesson::class)->latestOfMany('lesson_date'); }
public function students()
{
    return $this->belongsToMany(
        Student::class,
        'group_memberships',
        'group_id',
        'student_id'
    );
}
    public function lessons() { return $this->hasMany(Lesson::class); }
    public function memberships()
{
    return $this->hasMany(GroupMembership::class);
}

public function approvedStudents()
{
    return $this->hasMany(GroupMembership::class)->where('status','approved');
}

    public function activeStudentsCount(): int
    {
        return $this->approvedStudents()->count();
    }

    public function getIsCanJoinAttribute(): bool
    {
        if ($this->max_students === null) {
            return true;
        }

        return $this->activeStudentsCount() < $this->max_students;
    }

    public function isJoinedByStudent(?int $studentId): bool
    {
        if (!$studentId) {
            return false;
        }

        return $this->memberships()
            ->where('student_id', $studentId)
            ->where('status', '!=', GroupMembership::STATUS_REJECTED)
            ->where('status', '!=', GroupMembership::STATUS_PENDING)
            ->exists();
    }

    public function pendingRequests()
    {
        return $this->hasMany(GroupMembership::class)->where('status','pending');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function favoritedBy()
    {
        return $this->belongsToMany(
            Student::class,
            'favorite_groups'
        )->withTimestamps();
    }
}
