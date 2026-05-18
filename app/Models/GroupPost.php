<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupPost extends Model
{
    protected $fillable = ['group_id','teacher_id','content','is_pinned'];

    public function group() { return $this->belongsTo(Group::class); }
    public function teacher() { return $this->belongsTo(Teacher::class); }
    public function attachments() { return $this->hasMany(GroupPostAttachment::class, 'post_id'); }
}