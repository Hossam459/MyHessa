<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Traits\HttpResponses;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\Lesson;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReportsController extends Controller
{
    use HttpResponses;

    private const ATTENDANCE_STATUSES = ['present', 'late', 'absent', 'excused'];

    public function teacherOverview(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user || $user->role !== 'teacher' || !$user->teacher) {
            return $this->error(null, __('messages.unauthorized'), 403);
        }

        $filters = $this->validatedFilters($request);
        $teacher = $user->teacher;

        if (!empty($filters['group_id']) && !$this->teacherOwnsGroup($teacher->id, (int) $filters['group_id'])) {
            return $this->error(null, __('messages.forbidden_teacher_group'), 403);
        }

        $groupsQuery = Group::with(['subject', 'gradeLevel'])
            ->withCount('approvedStudents')
            ->where('teacher_id', $teacher->id);

        if (!empty($filters['group_id'])) {
            $groupsQuery->where('id', $filters['group_id']);
        }

        $groups = $groupsQuery->get();
        $groupIds = $groups->pluck('id');

        $lessonsQuery = Lesson::whereIn('group_id', $groupIds);
        $this->applyLessonDateFilters($lessonsQuery, $filters);

        $lessons = $lessonsQuery->get();
        $lessonIds = $lessons->pluck('id');
        $expectedAttendanceRows = $groups->sum(function (Group $group) use ($filters) {
            $groupLessonsQuery = Lesson::where('group_id', $group->id);
            $this->applyLessonDateFilters($groupLessonsQuery, $filters);

            return $group->approved_students_count * $groupLessonsQuery->count();
        });
        $attendanceSummary = $this->attendanceSummary(
            Attendance::whereIn('lessons_id', $lessonIds)->get(),
            $expectedAttendanceRows
        );

        $groupReports = $groups->map(function (Group $group) use ($filters) {
            $groupLessonsQuery = Lesson::where('group_id', $group->id);
            $this->applyLessonDateFilters($groupLessonsQuery, $filters);

            $groupLessons = $groupLessonsQuery->get();
            $groupAttendance = Attendance::whereIn('lessons_id', $groupLessons->pluck('id'))->get();

            $expectedAttendanceRows = $group->approved_students_count * $groupLessons->count();

            return [
                'id' => $group->id,
                'name' => $group->name,
                'subject' => $this->localizedName($group->subject),
                'grade_level' => $this->localizedName($group->gradeLevel),
                'students_count' => $group->approved_students_count,
                'lessons_count' => $groupLessons->count(),
                'attendance' => $this->attendanceSummary($groupAttendance, $expectedAttendanceRows),
            ];
        })->values();

        return $this->success([
            'filters' => $filters,
            'statistics' => [
                'groups_count' => $groups->count(),
                'students_count' => $groups->sum('approved_students_count'),
                'lessons_count' => $lessons->count(),
                'completed_lessons_count' => $lessons->where('status', 'completed')->count(),
                'attendance' => $attendanceSummary,
            ],
            'groups' => $groupReports,
        ], __('messages.success'));
    }

    public function groupAttendance(Request $request, int $groupId): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return $this->error(null, __('messages.unauthorized'), 401);
        }

        $filters = $this->validatedFilters($request);
        $group = Group::with(['subject', 'gradeLevel', 'teacher.user'])->findOrFail($groupId);
        $isTeacher = $user->teacher && (int) $group->teacher_id === (int) $user->teacher->id;
        $isStudent = $user->student && $this->isApprovedStudent($group->id, $user->student->id);

        if (!$isTeacher && !$isStudent) {
            return $this->error(null, __('group.forbidden_group_overview'), 403);
        }

        $lessonsQuery = Lesson::where('group_id', $group->id)
            ->with(['attendances.student.user'])
            ->orderBy('lesson_date')
            ->orderBy('start_time');
        $this->applyLessonDateFilters($lessonsQuery, $filters);

        $lessons = $lessonsQuery->get();
        $approvedMembers = GroupMembership::with('student.user')
            ->where('group_id', $group->id)
            ->where('status', GroupMembership::STATUS_APPROVED)
            ->orderBy('id')
            ->get();

        if ($isStudent && !$isTeacher) {
            $approvedMembers = $approvedMembers->where('student_id', $user->student->id)->values();
        }

        $studentsReport = $approvedMembers->map(function (GroupMembership $membership) use ($lessons) {
            $student = $membership->student;
            $studentAttendance = $lessons
                ->flatMap->attendances
                ->where('student_id', $student->id);

            return [
                'student_id' => $student->id,
                'name' => $student->user?->user_name,
                'image_profile_url' => $student->user?->image_profile_url,
                'attendance' => $this->attendanceSummary($studentAttendance, $lessons->count()),
            ];
        })->values();

        $lessonReports = $lessons->map(function (Lesson $lesson) use ($approvedMembers) {
            $attendanceByStudent = $lesson->attendances->keyBy('student_id');
            $students = $approvedMembers->map(function (GroupMembership $membership) use ($attendanceByStudent) {
                $student = $membership->student;
                $attendance = $attendanceByStudent->get($student->id);

                return [
                    'student_id' => $student->id,
                    'name' => $student->user?->user_name,
                    'image_profile_url' => $student->user?->image_profile_url,
                    'status' => $attendance?->status,
                    'marked_at' => $attendance?->marked_at,
                ];
            })->values();

            return [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'date' => $lesson->lesson_date,
                'start_time' => $lesson->start_time,
                'end_time' => $lesson->end_time,
                'attendance_status' => $lesson->attendance_status,
                'summary' => $this->studentRowsSummary($students),
                'students' => $students,
            ];
        })->values();

        return $this->success([
            'filters' => $filters,
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'subject' => $this->localizedName($group->subject),
                'grade_level' => $this->localizedName($group->gradeLevel),
                'teacher' => [
                    'id' => $group->teacher?->id,
                    'name' => $group->teacher?->user?->user_name,
                ],
            ],
            'summary' => $this->studentRowsSummary($lessonReports->flatMap->students),
            'students' => $studentsReport,
            'lessons' => $lessonReports,
        ], __('messages.success'));
    }

    public function studentAttendance(Request $request, int $studentId): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return $this->error(null, __('messages.unauthorized'), 401);
        }

        $filters = $this->validatedFilters($request);
        $student = Student::with('user')->findOrFail($studentId);
        $isSameStudent = $user->student && (int) $user->student->id === $student->id;
        $teacherId = $user->teacher?->id;

        if (!$isSameStudent && !$teacherId) {
            return $this->error(null, __('messages.unauthorized'), 403);
        }

        if (!empty($filters['group_id'])) {
            $hasMembership = GroupMembership::where('student_id', $student->id)
                ->where('group_id', $filters['group_id'])
                ->where('status', GroupMembership::STATUS_APPROVED)
                ->when(!$isSameStudent, function ($query) use ($teacherId) {
                    $query->whereHas('group', fn ($groupQuery) => $groupQuery->where('teacher_id', $teacherId));
                })
                ->exists();

            if (!$hasMembership) {
                return $this->error(null, __('group.forbidden_group_lessons'), 403);
            }
        }

        $lessonsQuery = Lesson::with(['group.subject', 'group.gradeLevel', 'attendances' => function ($query) use ($student) {
                $query->where('student_id', $student->id);
            }])
            ->whereHas('group.memberships', function ($query) use ($student) {
                $query->where('student_id', $student->id)
                    ->where('status', GroupMembership::STATUS_APPROVED);
            })
            ->when(!$isSameStudent, function ($query) use ($teacherId) {
                $query->whereHas('group', fn ($groupQuery) => $groupQuery->where('teacher_id', $teacherId));
            })
            ->when(!empty($filters['group_id']), fn ($query) => $query->where('group_id', $filters['group_id']))
            ->orderBy('lesson_date')
            ->orderBy('start_time');
        $this->applyLessonDateFilters($lessonsQuery, $filters);

        $lessons = $lessonsQuery->get();
        $lessonReports = $lessons->map(function (Lesson $lesson) {
            $attendance = $lesson->attendances->first();

            return [
                'lesson_id' => $lesson->id,
                'title' => $lesson->title,
                'date' => $lesson->lesson_date,
                'start_time' => $lesson->start_time,
                'end_time' => $lesson->end_time,
                'group' => [
                    'id' => $lesson->group?->id,
                    'name' => $lesson->group?->name,
                    'subject' => $this->localizedName($lesson->group?->subject),
                    'grade_level' => $this->localizedName($lesson->group?->gradeLevel),
                ],
                'status' => $attendance?->status,
                'marked_at' => $attendance?->marked_at,
            ];
        })->values();

        return $this->success([
            'filters' => $filters,
            'student' => [
                'id' => $student->id,
                'name' => $student->user?->user_name,
                'image_profile_url' => $student->user?->image_profile_url,
            ],
            'summary' => $this->studentRowsSummary($lessonReports->map(fn ($lesson) => [
                'status' => $lesson['status'],
            ])),
            'lessons' => $lessonReports,
        ], __('messages.success'));
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'group_id' => ['sometimes', 'integer', 'exists:groups,id'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ]);
    }

    private function applyLessonDateFilters($query, array $filters): void
    {
        if (!empty($filters['from'])) {
            $query->whereDate('lesson_date', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->whereDate('lesson_date', '<=', $filters['to']);
        }
    }

    private function attendanceSummary(Collection $attendances, ?int $expectedTotal = null): array
    {
        $summary = $this->emptySummary(max($expectedTotal ?? 0, $attendances->count()));

        foreach ($attendances as $attendance) {
            $status = $attendance->status;

            if (in_array($status, self::ATTENDANCE_STATUSES, true)) {
                $summary[$status]++;
                $summary['marked']++;
            } else {
                $summary['unmarked']++;
            }
        }

        if ($expectedTotal !== null) {
            $summary['unmarked'] += max(0, $expectedTotal - $attendances->count());
        }

        $attended = $summary['present'] + $summary['late'];
        $summary['attendance_rate'] = $summary['total'] > 0
            ? round(($attended / $summary['total']) * 100, 2)
            : 0;

        return $summary;
    }

    private function studentRowsSummary(Collection $rows): array
    {
        $summary = $this->emptySummary($rows->count());

        foreach ($rows as $row) {
            $status = is_array($row) ? $row['status'] ?? null : $row->status ?? null;

            if (in_array($status, self::ATTENDANCE_STATUSES, true)) {
                $summary[$status]++;
                $summary['marked']++;
            } else {
                $summary['unmarked']++;
            }
        }

        $attended = $summary['present'] + $summary['late'];
        $summary['attendance_rate'] = $summary['total'] > 0
            ? round(($attended / $summary['total']) * 100, 2)
            : 0;

        return $summary;
    }

    private function emptySummary(int $total): array
    {
        return [
            'total' => $total,
            'marked' => 0,
            'unmarked' => 0,
            'present' => 0,
            'late' => 0,
            'absent' => 0,
            'excused' => 0,
            'attendance_rate' => 0,
        ];
    }

    private function localizedName($model): ?array
    {
        if (!$model) {
            return null;
        }

        return [
            'id' => $model->id,
            'name' => app()->getLocale() === 'ar' ? $model->name_ar : $model->name_en,
        ];
    }

    private function teacherOwnsGroup(int $teacherId, int $groupId): bool
    {
        return Group::where('id', $groupId)
            ->where('teacher_id', $teacherId)
            ->exists();
    }

    private function isApprovedStudent(int $groupId, int $studentId): bool
    {
        return GroupMembership::where('group_id', $groupId)
            ->where('student_id', $studentId)
            ->where('status', GroupMembership::STATUS_APPROVED)
            ->exists();
    }
}
