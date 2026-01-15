<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrUpdateLessonRequest extends FormRequest
{
    public function authorize()
    {
        return true; // هنا ممكن تضيف تحقق إن المستخدم teacher
    }

    public function rules()
    {
        return [
            'title'      => 'required|string|max:255',
            'group_id'   => 'required|exists:groups,id',
            'teacher_id' => 'required|exists:teachers,id',
            'start_time' => 'required|date|after:now',
            'end_time'   => 'required|date|after:start_time',
        ];
    }

    public function messages()
    {
        return [
            'title.required'      => __('lesson.title_required'),
            'group_id.required'   => __('lesson.group_required'),
            'group_id.exists'     => __('lesson.group_not_found'),
            'teacher_id.required' => __('lesson.teacher_required'),
            'teacher_id.exists'   => __('lesson.teacher_not_found'),
            'start_time.required' => __('lesson.start_time_required'),
            'start_time.date'     => __('lesson.start_time_invalid'),
            'start_time.after'    => __('lesson.start_time_after_now'),
            'end_time.required'   => __('lesson.end_time_required'),
            'end_time.date'       => __('lesson.end_time_invalid'),
            'end_time.after'      => __('lesson.end_time_after_start'),
        ];
    }
}
