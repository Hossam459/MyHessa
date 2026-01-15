<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Attendance;
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
            return $this->error(null, __('validation.session_closed'));
        }

        foreach ($lesson->group->students as $student) {
            Attendance::firstOrCreate([
                'session_id' => $lesson->id,
                'student_id' => $student->id
            ]);
        }

        return $this->success(null, __('validation.session_started'));
    }

    public function markBulk(AttendanceRequest $request)
    {
        $lesson = Lesson::with('group.students')->findOrFail($request->session_id);

        if ($lesson->attendance_status === 'closed') {
            return $this->error(null, __('validation.session_closed'));
        }

        DB::transaction(function () use ($request, $lesson) {
            foreach ($request->students as $row) {
                if (!$lesson->group->students->contains('id', $row['student_id'])) {
                    continue; // تجاهل أي طالب غريب
                }

                Attendance::updateOrCreate(
                    [
                        'session_id' => $lesson->id,
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
            ->where('session_id', $lesson->id)
            ->get()
            ->map(function ($row) {
                return [
                    'student_id' => $row->student->id,
                    'name'       => $row->student->user->user_name,
                    'status'     => $row->status,
                    'marked_at'  => $row->marked_at,
                ];
            });

        return $this->success($attendance, __('validation.saved'));
    }


    public function closeSession($lessonId)
    {
        Lesson::where('id', $lessonId)
            ->update(['attendance_status' => 'closed']);

        return $this->success(null, __('validation.session_closed'));
    }

    public function sessionAttendance($lessonId)
    {
        $attendance = Attendance::with('student.user')
            ->where('session_id', $lessonId)
            ->get()
            ->map(function ($row) {
                return [
                    'student_id' => $row->student->id,
                    'name'       => $row->student->user->user_name,
                    'status'     => $row->status,
                    'marked_at'  => $row->marked_at,
                ];
            });

        return $this->success($attendance, '');
    }

public function studentsForLesson($lessonId)
{
    $lesson = Lesson::with('group.students.user')->findOrFail($lessonId);

    $students = $lesson->group->students->map(function ($student) {
        return [
            'student_id' => $student->id,
            'name'       => $student->user->user_name,
        ];
    });

    return $this->success($students, __('attendance.students_list'));
}

}
