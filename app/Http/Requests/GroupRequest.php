<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GroupRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

   public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'description' => ['nullable', 'string', 'max:1000'],

        'subject_id' => ['required', 'exists:subjects,id'],
        'grade_level_id' => ['required', 'exists:grade_levels,id'],
        'teacher_id' => ['required', 'exists:teachers,id'],

        'max_students' => ['nullable', 'integer', 'min:1', 'max:200'],
        'price' => ['nullable', 'numeric', 'min:0'],

        'schedules' => ['required', 'array'],
        'schedules.*.day_of_week' => ['required'],
        'schedules.*.start_time' => ['required'],
        'schedules.*.end_time' => ['required'],
    ];
}

    /**
     * Custom messages (optional but useful for API)
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Group name is required',
            'subject_id.required' => 'Subject is required',
            'subject_id.exists' => 'Selected subject does not exist',
            'grade_level_id.required' => 'Grade level is required',
            'grade_level_id.exists' => 'Selected grade level does not exist',
        ];
    }
}