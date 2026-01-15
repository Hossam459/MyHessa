<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Lesson extends Model {
    protected $fillable = ['group_id','teacher_id','title','start_time','end_time','attendance_status'];
    public function group() { return $this->belongsTo(Group::class); }
    public function teacher() { return $this->belongsTo(Teacher::class); }
    public function attendance() { return $this->hasMany(Attendance::class,'session_id'); }
}
