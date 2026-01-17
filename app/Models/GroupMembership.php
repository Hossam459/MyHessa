<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupMembership extends Model
{
    protected $fillable = [
        'group_id','student_id','status',
        'requested_by','requested_by_user_id',
        'decided_by_teacher_id','decided_at',
        'joined_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
        'joined_at'  => 'datetime',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
