<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Group extends Model {
    protected $fillable = ['name','description','subject_id','grade_level_id','max_students','price'];

    public function schedules() { return $this->hasMany(GroupSchedule::class); }
    public function students() { return $this->hasMany(Student::class, 'group_id'); }
    public function lessons() { return $this->hasMany(Lesson::class); }
}