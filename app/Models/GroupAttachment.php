<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupAttachment extends Model
{
    protected $table = 'group_attachments';

    protected $fillable = [
        'group_id','teacher_id','title',
        'file_name','file_path','mime_type','file_size'
    ];
}
