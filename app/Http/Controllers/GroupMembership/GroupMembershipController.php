<?php

namespace App\Http\Controllers\GroupMembership;

use App\Models\Group;
use App\Models\Student;
use App\Models\GroupMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Traits\HttpResponses;
use App\Http\Controllers\Controller;
use App\Notifications\AppDatabaseNotification;

class GroupMembershipController extends Controller
{
    use HttpResponses;


     private function assertTeacherOwnsGroup(Group $group, int $teacherId)
    {
        if ((int)$group->teacher_id !== (int)$teacherId) {
            abort(403, __('group.not_group_teacher'));
        }
    }

    // 1) الطالب يطلب الانضمام (pending)
    public function studentRequestJoin(Request $request, $groupId)
    {
        $user = auth()->user();
        $student = $user->student;

        if (!$student) {
            return $this->error(null, __('auth.unauthorized'), 401);
        }

        $group = Group::findOrFail($groupId);

        // Upsert: لو موجود approved/rejected/pending نتعامل
        $membership = GroupMembership::where('group_id', $group->id)
            ->where('student_id', $student->id)
            ->first();

        if ($membership && $membership->status === 'approved') {
            return $this->success($membership, __('group.already_joined'));
        }

        if ($membership && $membership->status === 'pending') {
            return $this->success($membership, __('group.request_already_pending'));
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

        $this->notifyUser($group->teacher?->user, [
            'type' => 'group_join_request',
            'title' => __('notifications.group_join_request_title'),
            'body' => __('notifications.group_join_request_body', [
                'student' => $user->user_name,
                'group' => $group->name,
            ]),
            'data' => [
                'group_id' => $group->id,
                'student_id' => $student->id,
                'membership_id' => $membership->id,
            ],
        ]);

        return $this->success($membership, __('group.join_request_sent'));
    }

    // 2) المدرس يضيف طالب مباشرة (approved)
    public function teacherAddStudent(Request $request, $groupId)
    {
        $user = auth()->user();
        $teacher = $user->teacher;

        if (!$teacher) {
            return $this->error(null, __('auth.unauthorized'), 401);
        }

        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:students,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), __('messages.invalid_data'), 422);
        }

        $group = Group::findOrFail($groupId);
        $this->assertTeacherOwnsGroup($group, $teacher->id);
        $studentId = (int) $request->student_id;

        // (اختياري) تحقق إن المدرس صاحب الجروب أو مرتبط بيه
        // لو عندك group_teacher pivot استخدمه هنا

        $membership = GroupMembership::where('group_id', $group->id)
            ->where('student_id', $studentId)
            ->first();

        if ($membership && $membership->status === 'approved') {
            return $this->success($membership, __('group.student_already_in_group'));
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

        $student = Student::with('user')->find($studentId);
        $this->notifyUser($student?->user, [
            'type' => 'group_student_added',
            'title' => __('notifications.group_student_added_title'),
            'body' => __('notifications.group_student_added_body', [
                'group' => $group->name,
            ]),
            'data' => [
                'group_id' => $group->id,
                'student_id' => $studentId,
                'membership_id' => $membership->id,
            ],
        ]);

        return $this->success($membership, __('group.student_added'));
    }

    // 3) المدرس يقبل طلب
    public function approve(Request $request, $groupId, $studentId)
    {
        $user = auth()->user();
        $teacher = $user->teacher;

        if (!$teacher) {
            return $this->error(null, __('auth.unauthorized'), 401);
        }

        $group = Group::findOrFail($groupId);
        $this->assertTeacherOwnsGroup($group, $teacher->id);

        $membership = GroupMembership::where('group_id', $groupId)
            ->where('student_id', $studentId)
            ->first();

        if (!$membership) {
            return $this->error(null, __('messages.not_found'), 404);
        }

        if ($membership->status === 'approved') {
            return $this->success($membership, __('group.already_approved'));
        }

        $membership->update([
            'status' => 'approved',
            'decided_by_teacher_id' => $teacher->id,
            'decided_at' => now(),
            'joined_at' => now(),
        ]);

        $student = Student::with('user')->find($studentId);
        $this->notifyUser($student?->user, [
            'type' => 'group_join_approved',
            'title' => __('notifications.group_join_approved_title'),
            'body' => __('notifications.group_join_approved_body', [
                'group' => $group->name,
            ]),
            'data' => [
                'group_id' => $group->id,
                'student_id' => (int) $studentId,
                'membership_id' => $membership->id,
            ],
        ]);

        return $this->success($membership, __('group.approved'));
    }

    // 4) المدرس يرفض طلب
    public function reject(Request $request, $groupId, $studentId)
    {
        $user = auth()->user();
        $teacher = $user->teacher;

        if (!$teacher) {
            return $this->error(null, __('auth.unauthorized'), 401);
        }

        $group = Group::findOrFail($groupId);
        $this->assertTeacherOwnsGroup($group, $teacher->id);

        $membership = GroupMembership::where('group_id', $groupId)
            ->where('student_id', $studentId)
            ->first();

        if (!$membership) {
            return $this->error(null, __('messages.not_found'), 404);
        }

        if ($membership->status === 'rejected') {
            return $this->success($membership, __('group.already_rejected'));
        }

        $membership->update([
            'status' => 'rejected',
            'decided_by_teacher_id' => $teacher->id,
            'decided_at' => now(),
            'joined_at' => null,
        ]);

        $student = Student::with('user')->find($studentId);
        $this->notifyUser($student?->user, [
            'type' => 'group_join_rejected',
            'title' => __('notifications.group_join_rejected_title'),
            'body' => __('notifications.group_join_rejected_body', [
                'group' => $group->name,
            ]),
            'data' => [
                'group_id' => $group->id,
                'student_id' => (int) $studentId,
                'membership_id' => $membership->id,
            ],
        ]);

        return $this->success($membership, __('group.rejected'));
    }

    // 5) قائمة الطلبات المعلقة للجروب
    public function listPending($groupId)
    {
        $user = auth()->user();
        $teacher = $user?->teacher;
        if (!$teacher) {
            return $this->error(null, __('auth.unauthorized'), 401);
        }

        $group = Group::findOrFail($groupId);
        $this->assertTeacherOwnsGroup($group, $teacher->id);

        $pending = GroupMembership::with(['student.user'])
            ->where('group_id', $group->id)
            ->where('status', 'pending')
            ->get()
            ->map(function ($m) {
                return [
                    'id' => $m->student_id,
                    'name' => $m->student?->user?->user_name,
                    'image_profile_url' => $m->student?->user?->image_profile_url,
                    'requested_by' => $m->requested_by,
                    'created_at' => $m->created_at,
                ];
            });

        return $this->success($pending, __('group.pending_requests'));
    }

    public function studentPendingRequests(Request $request)
    {
        $user = $request->user();
        $student = $user?->student;

        if (!$student) {
            return $this->error(null, __('auth.unauthorized'), 401);
        }

        $pending = GroupMembership::with([
                'group.subject',
                'group.gradeLevel',
                'group.teacher.user',
            ])
            ->where('student_id', $student->id)
            ->where('status', GroupMembership::STATUS_PENDING)
            ->latest()
            ->get()
            ->map(fn (GroupMembership $membership) => $this->formatStudentPendingRequest($membership));

        return $this->success($pending, __('group.pending_requests'));
    }

    public function cancelRequest(Request $request, $groupId)
    {
        $user = $request->user();
        $student = $user?->student;

        if (!$student) {
            return $this->error(null, __('auth.unauthorized'), 401);
        }

        $group = Group::findOrFail($groupId);

        $membership = GroupMembership::where('group_id', $group->id)
            ->where('student_id', $student->id)
            ->first();

        if (!$membership || $membership->status !== GroupMembership::STATUS_PENDING) {
            return $this->error(null, __('group.no_pending_request'), 400);
        }

        $membership->update([
            'status' => GroupMembership::STATUS_REJECTED,
            'decided_by_teacher_id' => null,
            'decided_at' => null,
            'joined_at' => null,
        ]);

        return $this->success($membership, __('group.request_cancelled'));
    }
    // 6) الطالب يغادر الجروب بنفسه

    public function leave(Request $request, $groupId)
    {
        $user = auth()->user();
        $student = $user->student;

        if (!$student) {
            return $this->error(null, __('auth.unauthorized'), 401);
        }

        $group = Group::findOrFail($groupId);

        $membership = GroupMembership::where('group_id', $group->id)
            ->where('student_id', $student->id)
            ->first();

        if (!$membership || $membership->status !== 'approved') {
            return $this->error(null, __('group.not_member'), 400);
        }

        $membership->update([
            'status' => 'rejected',
            'decided_by_teacher_id' => null,
            'decided_at' => null,
            'joined_at' => null,
        ]);

        $this->notifyUser($group->teacher?->user, [
            'type' => 'group_student_left',
            'title' => __('notifications.group_student_left_title'),
            'body' => __('notifications.group_student_left_body', [
                'student' => $user->user_name,
                'group' => $group->name,
            ]),
            'data' => [
                'group_id' => $group->id,
                'student_id' => $student->id,
                'membership_id' => $membership->id,
            ],
        ]);

        return $this->success($membership, __('group.left'));
    }

    // 7) المدرس يزيل طالب من الجروب
    public function removeStudent(Request $request, $groupId, $studentId)
    {
        $user = auth()->user();
        $teacher = $user->teacher;

        if (!$teacher) {
            return $this->error(null, __('auth.unauthorized'), 401);
        }

        $group = Group::findOrFail($groupId);
        $this->assertTeacherOwnsGroup($group, $teacher->id);

        $membership = GroupMembership::where('group_id', $groupId)
            ->where('student_id', $studentId)
            ->first();

        if (!$membership) {
            return $this->error(null, __('messages.not_found'), 404);
        }

        if ($membership->status === 'removed') {
            return $this->success($membership, __('group.already_removed'));
        }

        $membership->update([
            'status' => 'rejected',
            'decided_by_teacher_id' => $teacher->id,
            'decided_at' => now(),
            'joined_at' => null,
        ]);

        $student = Student::with('user')->find($studentId);
        $this->notifyUser($student?->user, [
            'type' => 'group_student_removed',
            'title' => __('notifications.group_student_removed_title'),
            'body' => __('notifications.group_student_removed_body', [
                'group' => $group->name,
            ]),
            'data' => [
                'group_id' => $group->id,
                'student_id' => (int) $studentId,
                'membership_id' => $membership->id,
            ],
        ]);

        return $this->success($membership, __('group.removed'));
    }

    private function notifyUser($user, array $payload): void
    {
        if ($user) {
            $user->notify(new AppDatabaseNotification($payload));
        }
    }

    private function formatStudentPendingRequest(GroupMembership $membership): array
    {
        $group = $membership->group;
        $locale = app()->getLocale();

        return [
            'membership_id' => $membership->id,
            'group_id' => $membership->group_id,
            'status' => $membership->status,
            'requested_by' => $membership->requested_by,
            'created_at' => $membership->created_at,
            'group' => [
                'id' => $group?->id,
                'name' => $group?->name,
                'description' => $group?->description,
                'price' => $group?->price,
                'max_students' => $group?->max_students,
                'start_date' => $group?->start_date,
                'end_date' => $group?->end_date,
                'subject' => [
                    'id' => $group?->subject?->id,
                    'name' => $locale === 'ar'
                        ? $group?->subject?->name_ar
                        : $group?->subject?->name_en,
                ],
                'grade_level' => [
                    'id' => $group?->gradeLevel?->id,
                    'name' => $locale === 'ar'
                        ? $group?->gradeLevel?->name_ar
                        : $group?->gradeLevel?->name_en,
                ],
                'teacher' => [
                    'id' => $group?->teacher?->id,
                    'name' => $group?->teacher?->user?->user_name,
                    'image_profile_url' => $group?->teacher?->user?->image_profile_url,
                ],
            ],
        ];
    }
}
