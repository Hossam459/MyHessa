<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Lesson extends Model {
    protected $fillable = ['group_id','teacher_id','schedule_id','title','lesson_date','start_time','end_time','attendance_status'];
    public function group() { return $this->belongsTo(Group::class); }
    public function teacher() { return $this->belongsTo(Teacher::class); }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'lessons_id');
    }

    public function attendance(): HasMany
    {
        return $this->attendances();
    }

    public function getStatusAttribute(): string
    {
        $now = Carbon::now();
        $start = $this->lessonDateTime($this->start_time);
        $end = $this->lessonDateTime($this->end_time);

        if (!$start || !$end) {
            return 'upcoming';
        }

        if ($now->lt($start)) {
            return 'upcoming';
        }

        if ($now->between($start, $end)) {
            return 'ongoing';
        }

        return 'completed';
    }

    private function lessonDateTime($time): ?Carbon
    {
        if (!$this->lesson_date || !$time) {
            return null;
        }

        $timeString = (string) $time;

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $timeString)) {
            return Carbon::parse($timeString);
        }

        return Carbon::parse($this->lesson_date . ' ' . $timeString);
    }
}
