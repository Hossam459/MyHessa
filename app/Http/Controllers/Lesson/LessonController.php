<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateOrUpdateLessonRequest;
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
}
