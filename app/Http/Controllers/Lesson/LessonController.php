<?php

namespace App\Http\Controllers\Lesson;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateOrUpdateLessonRequest;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\Lesson;
use App\Http\Traits\HttpResponses;
use Carbon\Carbon;
use App\Notifications\AppDatabaseNotification;

class LessonController extends Controller {
    use HttpResponses;

    public function createLesson(CreateOrUpdateLessonRequest $req){
        $data = $req->validated();

        $user = auth()->user();
        if (!$user || !$user->teacher || (int) $data['teacher_id'] !== (int) $user->teacher->id) {
            return $this->error(null, __('messages.unauthorized'), 401);
        }

        $group = Group::findOrFail($data['group_id']);
        if ((int) $group->teacher_id !== (int) $user->teacher->id) {
            return $this->error(null, __('group.not_group_teacher'), 403);
        }

        if (!isset($data['lesson_date']) && isset($data['start_time'])) {
            $data['lesson_date'] = Carbon::parse($data['start_time'])->toDateString();
        }

        if (isset($data['start_time'], $data['end_time']) && Carbon::parse($data['end_time'])->lte(Carbon::parse($data['start_time']))) {
            return $this->error(null, __('lesson.end_time_after_start'), 422);
        }

        $lesson = Lesson::create($data + ['attendance_status'=>'pending']);
        $this->notifyApprovedStudents($group, [
            'type' => 'lesson_created',
            'title' => __('notifications.lesson_created_title'),
            'body' => __('notifications.lesson_created_body', ['group' => $group->name]),
            'data' => [
                'group_id' => $group->id,
                'lesson_id' => $lesson->id,
                'teacher_id' => $user->teacher->id,
                'lesson_date' => $lesson->lesson_date,
                'start_time' => $lesson->start_time,
            ],
        ]);

        return $this->success($lesson,__('lesson.created'));
    }

    public function updateLesson(CreateOrUpdateLessonRequest $req,$lessonId){
        $lesson = Lesson::findOrFail($lessonId);
        if($lesson->attendance_status==='closed') return $this->error(null,__('attendance.session_closed'));
        if($lesson->attendance_status==='cancelled') return $this->error(null,__('lesson.already_cancelled'));

        $user = auth()->user();
        if (!$user || !$user->teacher || (int) $lesson->teacher_id !== (int) $user->teacher->id) {
            return $this->error(null, __('group.not_group_teacher'), 403);
        }

        $data = $req->validated();

        if (isset($data['group_id'])) {
            $group = Group::findOrFail($data['group_id']);
            if ((int) $group->teacher_id !== (int) $user->teacher->id) {
                return $this->error(null, __('group.not_group_teacher'), 403);
            }

            $data['teacher_id'] = $user->teacher->id;
        }

        if (isset($data['teacher_id']) && (int) $data['teacher_id'] !== (int) $user->teacher->id) {
            return $this->error(null, __('group.not_group_teacher'), 403);
        }

        if (!isset($data['lesson_date']) && isset($data['start_time'])) {
            $data['lesson_date'] = Carbon::parse($data['start_time'])->toDateString();
        }

        $startTime = $data['start_time'] ?? $lesson->start_time;
        $endTime = $data['end_time'] ?? $lesson->end_time;
        if ($startTime && $endTime && Carbon::parse($endTime)->lte(Carbon::parse($startTime))) {
            return $this->error(null, __('lesson.end_time_after_start'), 422);
        }

        $lesson->update($data);
        $lesson->loadMissing('group');
        if ($lesson->group) {
            $this->notifyApprovedStudents($lesson->group, [
                'type' => 'lesson_updated',
                'title' => __('notifications.lesson_updated_title'),
                'body' => __('notifications.lesson_updated_body', ['group' => $lesson->group->name]),
                'data' => [
                    'group_id' => $lesson->group->id,
                    'lesson_id' => $lesson->id,
                    'teacher_id' => $user->teacher->id,
                    'lesson_date' => $lesson->lesson_date,
                    'start_time' => $lesson->start_time,
                ],
            ]);
        }

        return $this->success($lesson,__('lesson.updated'));
    }

    public function closeLesson($lessonId){
        $lesson = Lesson::findOrFail($lessonId);

        $user = auth()->user();
        if (!$user || !$user->teacher || (int) $lesson->teacher_id !== (int) $user->teacher->id) {
            return $this->error(null, __('group.not_group_teacher'), 403);
        }

        if ($lesson->attendance_status === 'cancelled') {
            return $this->error(null, __('lesson.already_cancelled'));
        }

        $lesson->update(['attendance_status'=>'closed']);
        $lesson->loadMissing('group');
        if ($lesson->group) {
            $this->notifyApprovedStudents($lesson->group, [
                'type' => 'lesson_closed',
                'title' => __('notifications.lesson_closed_title'),
                'body' => __('notifications.lesson_closed_body', ['group' => $lesson->group->name]),
                'data' => [
                    'group_id' => $lesson->group->id,
                    'lesson_id' => $lesson->id,
                    'teacher_id' => $user->teacher->id,
                    'lesson_date' => $lesson->lesson_date,
                    'start_time' => $lesson->start_time,
                ],
            ]);
        }

        return $this->success(null, __('lesson.closed'));
    }

    public function cancelLesson($lessonId){
        $lesson = Lesson::findOrFail($lessonId);

        $user = auth()->user();
        if (!$user || !$user->teacher || (int) $lesson->teacher_id !== (int) $user->teacher->id) {
            return $this->error(null, __('group.not_group_teacher'), 403);
        }

        if ($lesson->attendance_status === 'closed') {
            return $this->error(null, __('attendance.session_closed'));
        }

        if ($lesson->attendance_status === 'cancelled') {
            return $this->success($lesson, __('lesson.already_cancelled'));
        }

        $lesson->update(['attendance_status' => 'cancelled']);
        $lesson->loadMissing('group');
        if ($lesson->group) {
            $this->notifyApprovedStudents($lesson->group, [
                'type' => 'lesson_cancelled',
                'title' => __('notifications.lesson_cancelled_title'),
                'body' => __('notifications.lesson_cancelled_body', ['group' => $lesson->group->name]),
                'data' => [
                    'group_id' => $lesson->group->id,
                    'lesson_id' => $lesson->id,
                    'teacher_id' => $user->teacher->id,
                    'lesson_date' => $lesson->lesson_date,
                    'start_time' => $lesson->start_time,
                ],
            ]);
        }

        return $this->success($lesson, __('lesson.cancelled'));
    }

    public function groupLessons($groupId)
    {
        $user = auth()->user();

        if (!$user) {
            return $this->error(null, __('messages.unauthorized'), 401);
        }

        $group = Group::findOrFail($groupId);

        $isGroupTeacher = $user->teacher && (int) $group->teacher_id === (int) $user->teacher->id;
        $isApprovedStudent = $user->student && GroupMembership::where('group_id', $group->id)
            ->where('student_id', $user->student->id)
            ->where('status', GroupMembership::STATUS_APPROVED)
            ->exists();

        if (!$isGroupTeacher && !$isApprovedStudent) {
            return $this->error(null, __('group.forbidden_group_lessons'), 403);
        }

        $lessons = Lesson::with([
                'group.subject',
                'group.gradeLevel',
                'group.teacher.user',
                'attendances',
            ])
            ->where('group_id', $group->id)
            ->orderBy('lesson_date')
            ->orderBy('start_time')
            ->get();

        $today = now()->toDateString();
        $formatter = $isGroupTeacher
            ? fn($lesson) => $this->formatTeacherLesson($lesson)
            : fn($lesson) => $this->formatLesson($lesson);

        return $this->success([
            'today' => $lessons
                ->where('lesson_date', $today)
                ->values()
                ->map($formatter),

            'upcoming' => $lessons
                ->filter(fn($lesson) => $lesson->lesson_date > $today)
                ->values()
                ->map($formatter),

            'past' => $lessons
                ->filter(fn($lesson) => $lesson->lesson_date < $today)
                ->values()
                ->map($formatter),
        ], __('group.lessons_list'));
    }

    public function teacherLessons(){
      $user = auth()->user();

        if (!$user || !$user->teacher) {

            return $this->error(
                null,
                __('messages.unauthorized'),
                401
            );
        }

        $teacher = $user->teacher;

        $lessons = Lesson::with([
                'group.subject',
                'group.gradeLevel',
            ])
            ->whereHas('group', function ($q) use ($teacher) {

                $q->where(
                    'teacher_id',
                    $teacher->id
                );

            })
            ->orderBy('lesson_date')
            ->orderBy('start_time')
            ->get();

        $today = now()->toDateString();

        return $this->success([

            'today' => $lessons
                ->where('lesson_date', $today)
                ->values()
                ->map(fn($lesson) => $this->formatTeacherLesson($lesson)),

            'upcoming' => $lessons
                ->filter(fn($lesson) =>
                    $lesson->lesson_date > $today
                )
                ->values()
                ->map(fn($lesson) => $this->formatTeacherLesson($lesson)),

            'past' => $lessons
                ->filter(fn($lesson) =>
                    $lesson->lesson_date < $today
                )
                ->values()
                ->map(fn($lesson) => $this->formatTeacherLesson($lesson)),

        ], __('lesson.teacher_lessons_loaded'));
    }

    private function formatTeacherLesson($lesson): array
    {
        return [

            'id' => $lesson->id,

            'title' => $lesson->group?->name,

            'subject' => [
                'id' => $lesson->group?->subject?->id,

                'name' => app()->getLocale() === 'ar'
                    ? $lesson->group?->subject?->name_ar
                    : $lesson->group?->subject?->name_en,
            ],

            'grade_level' => [
                'id' => $lesson->group?->gradeLevel?->id,

                'name' => app()->getLocale() === 'ar'
                    ? $lesson->group?->gradeLevel?->name_ar
                    : $lesson->group?->gradeLevel?->name_en,
            ],

            'date' => $lesson->lesson_date,

            'start_time' => $lesson->start_time,

            'end_time' => $lesson->end_time,

            'status' => $lesson->status,

            'attendance_status' => $this->studentAttendanceStatus($lesson),

            'meeting_link' => $lesson->meeting_link,

            'is_live' => $lesson->status === 'ongoing',

            'students_count' => $lesson->group
                ? $lesson->group
                    ->approvedStudents()
                    ->count()
                : 0,
        ];
    }
    public function studentLessons(){
      $user = auth()->user();

        if (!$user || !$user->student) {

            return $this->error(
                null,
                __('messages.unauthorized'),
                401
            );
        }

        $student = $user->student;

        $groupIds = GroupMembership::where(
                'student_id',
                $student->id
            )
            ->where(
                'status',
                'approved'
            )
            ->pluck('group_id');

        $lessons = Lesson::with([
                'group.subject',
                'group.teacher.user',
                'attendances',
            ])
            ->whereIn('group_id', $groupIds)
            ->orderBy('lesson_date')
            ->orderBy('start_time')
            ->get();

        $today = now()->toDateString();

        return $this->success([

            'today' => $lessons
                ->where('lesson_date', $today)
                ->values()
                ->map(fn($lesson) => $this->formatLesson($lesson)),

            'upcoming' => $lessons
                ->filter(fn($lesson) =>
                    $lesson->lesson_date > $today
                )
                ->values()
                ->map(fn($lesson) => $this->formatLesson($lesson)),

            'past' => $lessons
                ->filter(fn($lesson) =>
                    $lesson->lesson_date < $today
                )
                ->values()
                ->map(fn($lesson) => $this->formatLesson($lesson)),

        ], __('lesson.lessons_loaded'));
    }

     private function formatLesson($lesson): array
    {
        return [

            'id' => $lesson->id,

            'title' => $lesson->group?->name,

            'subject' => [
                'id' => $lesson->group?->subject?->id,
                'name' => app()->getLocale() === 'ar'
                    ? $lesson->group?->subject?->name_ar
                    : $lesson->group?->subject?->name_en,
            ],

            'teacher' => [
                'id' => $lesson->group?->teacher?->id,
                'name' => $lesson->group?->teacher?->user?->user_name,
                'image' => $lesson->group?->teacher?->user?->image_profile_url,
                'rating' => $lesson->group?->teacher?->averageRating() ?? 0,
                'ratings_count' => $lesson->group?->teacher?->ratingsCount() ?? 0,
            ],

            'date' => $lesson->lesson_date,

            'start_time' => $lesson->start_time,

            'end_time' => $lesson->end_time,

            'status' => $lesson->status,

            'attendance_status' => $this->studentAttendanceStatus($lesson),

            'meeting_link' => $lesson->meeting_link,

            'is_live' => $lesson->status === 'ongoing',
            'is_can_join' => $lesson->group ? $lesson->group->isCanJoin : true,
            'is_already_joined' => $lesson->group ? $lesson->group->isJoinedByStudent(auth()->user()?->student?->id) : false,
        ];
    }

    private function studentAttendanceStatus($lesson): string
    {
        $studentId = auth()->user()?->student?->id;

        if (!$studentId) {
            return $lesson->attendance_status ?? 'pending';
        }

        if ($lesson->attendance_status === 'cancelled') {
            return 'cancelled';
        }

        $attendance = $lesson->relationLoaded('attendances')
            ? $lesson->attendances->firstWhere('student_id', $studentId)
            : $lesson->attendances()->where('student_id', $studentId)->first();

        return $attendance?->status ?? 'unmarked';
    }

    private function notifyApprovedStudents(Group $group, array $payload): void
    {
        GroupMembership::with('student.user')
            ->where('group_id', $group->id)
            ->where('status', GroupMembership::STATUS_APPROVED)
            ->get()
            ->pluck('student.user')
            ->filter()
            ->each(fn ($user) => $user->notify(new AppDatabaseNotification($payload)));
    }
}
