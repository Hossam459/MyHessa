<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Attendance;
use App\Models\GroupMembership;
use App\Models\Lesson;
use App\Http\Traits\HttpResponses;
use App\Http\Traits\Access;
use App\Http\Requests\AttendanceRequest;

class AttendanceController extends Controller
{
    use HttpResponses, Access;

    public function startSession($lessonId)
    {
        $lesson = Lesson::with('group.students')->findOrFail($lessonId);

        if ($lesson->attendance_status === 'closed') {
            return $this->error(null, __('attendance.session_closed'));
        }

        if ($lesson->attendance_status === 'cancelled') {
            return $this->error(null, __('lesson.already_cancelled'));
        }

        foreach ($lesson->group->students as $student) {
            Attendance::firstOrCreate([
                'lessons_id' => $lesson->id,
                'student_id' => $student->id
            ]);
        }

        return $this->success(null, __('attendance.session_started'));
    }

    public function markBulk(AttendanceRequest $request)
    {
        $lesson = Lesson::with('group.students')->findOrFail($request->lessons_id);

        if ($lesson->attendance_status === 'closed') {
            return $this->error(null, __('attendance.session_closed'));
        }

        if ($lesson->attendance_status === 'cancelled') {
            return $this->error(null, __('lesson.already_cancelled'));
        }

        DB::transaction(function () use ($request, $lesson) {
            foreach ($request->students as $row) {
                if (!$lesson->group->students->contains('id', $row['student_id'])) {
                    continue; // تجاهل أي طالب غريب
                }

                Attendance::updateOrCreate(
                    [
                        'lessons_id' => $lesson->id,
                        'student_id' => $row['student_id'],
                    ],
                    [
                        'status'    => $row['status'],
                        'marked_at' => now()
                    ]
                );
            }
        });

        $attendance = Attendance::with('student.user')
            ->where('lessons_id', $lesson->id)
            ->get()
            ->map(function ($row) {
                return [
                    'student_id' => $row->student->id,
                    'name'       => $row->student->user->user_name,
                    'status'     => $row->status,
                    'marked_at'  => $row->marked_at,
                ];
            });

        return $this->success($attendance, __('attendance.saved'));
    }


    public function closeSession($lessonId)
    {
        $lesson = Lesson::findOrFail($lessonId);

        if ($lesson->attendance_status === 'cancelled') {
            return $this->error(null, __('lesson.already_cancelled'));
        }

        $lesson->update(['attendance_status' => 'closed']);

        return $this->success(null, __('attendance.session_closed'));
    }

    public function lessonDetails($lessonId)
    {
        $user = auth()->user();
        if (!$user) return $this->error(null, __('messages.unauthorized'), 401);

        $lesson = Lesson::with([
                'group.subject',
                'group.gradeLevel',
                'group.teacher.user',
                'attendances.student.user',
            ])
            ->findOrFail($lessonId);

        $group = $lesson->group;
        $isGroupTeacher = $user->teacher && (int) $group->teacher_id === (int) $user->teacher->id;
        $isApprovedStudent = $user->student && GroupMembership::where('group_id', $group->id)
            ->where('student_id', $user->student->id)
            ->where('status', GroupMembership::STATUS_APPROVED)
            ->exists();

        if (!$isGroupTeacher && !$isApprovedStudent) {
            return $this->error(null, __('group.forbidden_group_lessons'), 403);
        }

        $attendanceByStudent = $lesson->attendances->keyBy('student_id');

        // For students: always return only his/her attendance for this lesson.
        if ($isApprovedStudent && !$isGroupTeacher) {
            $membership = GroupMembership::with('student.user')
                ->where('group_id', $group->id)
                ->where('student_id', $user->student->id)
                ->where('status', GroupMembership::STATUS_APPROVED)
                ->first();

            if (!$membership) {
                return $this->error(null, __('group.forbidden_group_lessons'), 403);
            }

            $student = $membership->student;
            $attendance = $attendanceByStudent->get($student->id);

            $students = collect([
                [
                    'student_id' => $student->id,
                    'name' => $student->user?->user_name,
                    'image_profile_url' => $student->user?->image_profile_url,
                    'status' => $attendance?->status,
                    'marked_at' => $attendance?->marked_at,
                ]
            ]);
        } else {
            // For teachers: return all approved members of the group.
            $memberships = GroupMembership::with('student.user')
                ->where('group_id', $group->id)
                ->where('status', GroupMembership::STATUS_APPROVED)
                ->orderBy('id')
                ->get();

            $students = $memberships->map(function ($membership) use ($attendanceByStudent) {
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
        }


        $summary = [
            'total_students' => $students->count(),
            'marked' => $students->whereNotNull('status')->count(),
            'unmarked' => $students->whereNull('status')->count(),
            'present' => $students->where('status', 'present')->count(),
            'late' => $students->where('status', 'late')->count(),
            'absent' => $students->where('status', 'absent')->count(),
            'excused' => $students->where('status', 'excused')->count(),
        ];

        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';

        return $this->success([
            'lesson' => [
                'id' => $lesson->id,
                'title' => $lesson->title ?: $group?->name,
                'date' => $lesson->lesson_date,
                'start_time' => $lesson->start_time,
                'end_time' => $lesson->end_time,
                'attendance_status' => $lesson->attendance_status,
                'group' => [
                    'id' => $group?->id,
                    'name' => $group?->name,
                ],
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
            ],
            'summary' => $summary,
            'students' => $students,
        ], __('attendance.details'));
    }

    public function sessionAttendance($lessonId)
    {
        return $this->lessonDetails($lessonId);
    }

public function studentsForLesson($lessonId)
{
    $lesson = Lesson::with('group.students.user')->findOrFail($lessonId);

    $students = $lesson->group->students->map(function ($student) {
        return [
            'student_id' => $student->id,
            'name'       => $student->user->user_name,
            'image'      =>  $student->user->image_profile_url,
        ];
    });

    return $this->success($students, __('attendance.students_list'));
}

}
