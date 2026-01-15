<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'session_id' => 'required|exists:lessons,id',
            'students'   => 'required|array|min:1',
            'students.*.student_id' => 'required|exists:students,id',
            'students.*.status'     => 'required|in:present,late,absent,excused'
        ];
    }

    public function messages()
    {
        return [
            'session_id.required' => __('validation.session_required'),
            'session_id.exists'   => __('validation.session_not_found'),

            'students.required'  => __('validation.students_required'),
            'students.array'     => __('validation.students_array'),

            'students.*.student_id.required' => __('validation.student_required'),
            'students.*.student_id.exists'   => __('validation.student_not_found'),

            'students.*.status.required' => __('validation.status_required'),
            'students.*.status.in'       => __('validation.status_invalid'),
        ];
    }
}
