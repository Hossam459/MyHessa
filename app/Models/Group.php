<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Group extends Model {
    protected $fillable = ['name','description','subject_id','grade_level_id','max_students','price','teacher_id'];

    public function schedules() { return $this->hasMany(GroupSchedule::class); }
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

public function pendingRequests()
{
    return $this->hasMany(GroupMembership::class)->where('status','pending');
}

    public function teacher(): BelongsTo { return $this->belongsTo(Teacher::class); }
    public function subject(): BelongsTo { return $this->belongsTo(Subject::class); }
    public function gradeLevel(): BelongsTo { return $this->belongsTo(GradeLevel::class); }

    public function favoritedBy()
{
    return $this->belongsToMany(
        Student::class,
        'favorite_groups'
    )->withTimestamps();
}
}