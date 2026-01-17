<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Student;
use App\Models\GroupMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Traits\HttpResponses;

class GroupMembershipController extends Controller
{
    use HttpResponses;

    // 1) الطالب يطلب الانضمام (pending)
    public function studentRequestJoin(Request $request, $groupId)
    {
        $user = auth()->user();
        $student = $user->student;

        if (!$student) {
            return $this->error(null, 'Unauthorized', 401);
        }

        $group = Group::findOrFail($groupId);

        // Upsert: لو موجود approved/rejected/pending نتعامل
        $membership = GroupMembership::where('group_id', $group->id)
            ->where('student_id', $student->id)
            ->first();

        if ($membership && $membership->status === 'approved') {
            return $this->success($membership, 'Already joined');
        }

        if ($membership && $membership->status === 'pending') {
            return $this->success($membership, 'Request already pending');
        }

        // لو كان rejected قبل كده، نخليه pending تاني
        $membership = GroupMembership::updateOrCreate(
            ['group_id' => $group->id, 'student_id' => $student->id],
            [
                'status' => 'pending',
                'requested_by' => 'student',
                'requested_by_user_id' => $user->id,
                'decided_by_teacher_id' => null,
                'decided_at' => null,
                'joined_at' => null,
            ]
        );

        return $this->success($membership, 'Join request sent');
    }

    // 2) المدرس يضيف طالب مباشرة (approved)
    public function teacherAddStudent(Request $request, $groupId)
    {
        $user = auth()->user();
        $teacher = $user->teacher;

        if (!$teacher) {
            return $this->error(null, 'Unauthorized', 401);
        }

        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:students,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 'Invalid data', 422);
        }

        $group = Group::findOrFail($groupId);
        $studentId = (int) $request->student_id;

        // (اختياري) تحقق إن المدرس صاحب الجروب أو مرتبط بيه
        // لو عندك group_teacher pivot استخدمه هنا

        $membership = GroupMembership::where('group_id', $group->id)
            ->where('student_id', $studentId)
            ->first();

        if ($membership && $membership->status === 'approved') {
            return $this->success($membership, 'Student already in group');
        }

        $membership = GroupMembership::updateOrCreate(
            ['group_id' => $group->id, 'student_id' => $studentId],
            [
                'status' => 'approved',
                'requested_by' => 'teacher',
                'requested_by_user_id' => $user->id,
                'decided_by_teacher_id' => $teacher->id,
                'decided_at' => now(),
                'joined_at' => now(),
            ]
        );

        return $this->success($membership, 'Student added');
    }

    // 3) المدرس يقبل طلب
    public function approve(Request $request, $groupId, $studentId)
    {
        $user = auth()->user();
        $teacher = $user->teacher;

        if (!$teacher) {
            return $this->error(null, 'Unauthorized', 401);
        }

        $membership = GroupMembership::where('group_id', $groupId)
            ->where('student_id', $studentId)
            ->first();

        if (!$membership) {
            return $this->error(null, 'Request not found', 404);
        }

        if ($membership->status === 'approved') {
            return $this->success($membership, 'Already approved');
        }

        $membership->update([
            'status' => 'approved',
            'decided_by_teacher_id' => $teacher->id,
            'decided_at' => now(),
            'joined_at' => now(),
        ]);

        return $this->success($membership, 'Approved');
    }

    // 4) المدرس يرفض طلب
    public function reject(Request $request, $groupId, $studentId)
    {
        $user = auth()->user();
        $teacher = $user->teacher;

        if (!$teacher) {
            return $this->error(null, 'Unauthorized', 401);
        }

        $membership = GroupMembership::where('group_id', $groupId)
            ->where('student_id', $studentId)
            ->first();

        if (!$membership) {
            return $this->error(null, 'Request not found', 404);
        }

        if ($membership->status === 'rejected') {
            return $this->success($membership, 'Already rejected');
        }

        $membership->update([
            'status' => 'rejected',
            'decided_by_teacher_id' => $teacher->id,
            'decided_at' => now(),
            'joined_at' => null,
        ]);

        return $this->success($membership, 'Rejected');
    }

    // 5) قائمة الطلبات المعلقة للجروب
    public function listPending($groupId)
    {
        $group = Group::findOrFail($groupId);

        $pending = GroupMembership::with(['student.user'])
            ->where('group_id', $group->id)
            ->where('status', 'pending')
            ->get()
            ->map(function ($m) {
                return [
                    'student_id' => $m->student_id,
                    'name' => $m->student?->user?->user_name,
                    'requested_by' => $m->requested_by,
                    'created_at' => $m->created_at,
                ];
            });

        return $this->success($pending, 'Pending requests');
    }
}
