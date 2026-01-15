<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class GroupSchedule extends Model {
    protected $fillable = ['group_id','day_of_week','start_time','end_time'];
    public function group() { return $this->belongsTo(Group::class); }
}