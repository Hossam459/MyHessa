<?php

namespace App\Http\Controllers\Lesson;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateOrUpdateLessonRequest;
use App\Models\GroupMembership;
use App\Models\Lesson;
use App\Http\Traits\HttpResponses;

class LessonController extends Controller {
    use HttpResponses;

    public function createLesson(CreateOrUpdateLessonRequest $req){
        $lesson = Lesson::create($req->validated()+['attendance_status'=>'pending']);
        return $this->success($lesson,__('lesson.created'));
    }

    public function updateLesson(CreateOrUpdateLessonRequest $req,$lessonId){
        $lesson = Lesson::findOrFail($lessonId);
        if($lesson->attendance_status==='closed') return $this->error(null,__('attendance.session_closed'));
        $lesson->update($req->validated());
        return $this->success($lesson,__('lesson.updated'));
    }

    public function closeLesson($lessonId){
        $lesson = Lesson::findOrFail($lessonId);
        $lesson->update(['attendance_status'=>'closed']);
        return $this->success(null,'تم إغلاق الحصة');
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

        ], 'Teacher lessons loaded successfully');
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
                'group.teacher.user'
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

        ], 'Lessons loaded successfully');
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
            ],

            'date' => $lesson->lesson_date,

            'start_time' => $lesson->start_time,

            'end_time' => $lesson->end_time,

            'status' => $lesson->status,

            'attendance_status' => null,

            'meeting_link' => $lesson->meeting_link,

            'is_live' => $lesson->status === 'ongoing',
        ];
    }
}
