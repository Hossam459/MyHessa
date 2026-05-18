<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupPostAttachment extends Model
{
    public $timestamps = false;
    protected $fillable = ['post_id','file_name','file_path','mime_type','file_size'];

    public function post() { return $this->belongsTo(GroupPost::class, 'post_id'); }
}