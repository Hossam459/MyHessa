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
    $required = $this->isMethod('post') ? 'required' : 'sometimes';

    return [
        'name' => [$required, 'string', 'max:255'],
        'description' => ['nullable', 'string', 'max:1000'],

        'subject_id' => [$required, 'exists:subjects,id'],
        'grade_level_id' => [$required, 'exists:grade_levels,id'],
        'teacher_id' => [$required, 'exists:teachers,id'],

        'max_students' => ['nullable', 'integer', 'min:1', 'max:200'],
        'price' => ['nullable', 'numeric', 'min:0'],

        'schedules' => [$required, 'array'],
        'schedules.*.day_of_week' => ['required_with:schedules'],
        'schedules.*.start_time' => ['required_with:schedules'],
        'schedules.*.end_time' => ['required_with:schedules'],
    ];
}

    /**
     * Custom messages (optional but useful for API)
     */
    public function messages(): array
    {
        return [
            'name.required' => __('group.name_required'),
            'subject_id.required' => __('group.subject_required'),
            'subject_id.exists' => __('group.subject_not_found'),
            'grade_level_id.required' => __('group.grade_level_required'),
            'grade_level_id.exists' => __('group.grade_level_not_found'),
        ];
    }
}
