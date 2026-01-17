<?php

namespace App\Http\Controllers\Group;

use Illuminate\Http\Request;
use Auth;
use Validator;
use App\Models\User;
use App\Notifications\SendPushNotification;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Traits\HttpResponses;
use Illuminate\Http\JsonResponse;
use App\Http\Traits\Access;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\GroupMembership;
use App\Models\GroupSchedule;
use App\Models\Group;

class GroupController extends Controller {
    use HttpResponses;

    public function create(GroupRequest $request) {
        DB::transaction(function() use ($request, &$group) {
            $group = Group::create($request->only(['name','description','subject_id','grade_level_id','max_students','price']));
            foreach ($request->schedules as $s) {
                $this->checkScheduleConflict($group->id,$s['day_of_week'],$s['start_time'],$s['end_time']);
                GroupSchedule::create(['group_id'=>$group->id]+$s);
            }
        });
        return $this->success($group->load('schedules'), __('group.created'));
    }

    public function update(GroupRequest $request,$groupId) {
        $group = Group::findOrFail($groupId);
        DB::transaction(function() use ($request,$group) {
            $group->update($request->only(['name','description','subject_id','grade_level_id','max_students','price']));
            $group->schedules()->delete();
            foreach($request->schedules as $s){
                $this->checkScheduleConflict($group->id,$s['day_of_week'],$s['start_time'],$s['end_time']);
                GroupSchedule::create(['group_id'=>$group->id]+$s);
            }
        });
        return $this->success($group->load('schedules'), __('group.updated'));
    }

    public function delete($groupId) {
        $group = Group::findOrFail($groupId);
        $group->delete();
        return $this->success(null, __('group.deleted'));
    }

    private function checkScheduleConflict($groupId,$day,$start,$end){
        $conflict = GroupSchedule::where('group_id',$groupId)->where('day_of_week',$day)
            ->where(function($q) use ($start,$end){
                $q->whereBetween('start_time',[$start,$end])
                  ->orWhereBetween('end_time',[$start,$end])
                  ->orWhereRaw('? BETWEEN start_time AND end_time',[$start])
                  ->orWhereRaw('? BETWEEN start_time AND end_time',[$end]);
            })->exists();
        if($conflict) abort(422, __('group.schedule_conflict'));
    }

    public function students($groupId)
{
    $group = Group::findOrFail($groupId);

    $students = GroupMembership::with(['student.user'])
        ->where('group_id', $group->id)
        ->where('status', 'approved')
        ->orderBy('id')
        ->get()
        ->map(function ($m) {
            return [
                'student_id' => $m->student_id,
                'name'       => $m->student?->user?->user_name,
            ];
        });

    return $this->success($students, __('group.students_list'));
}
}
