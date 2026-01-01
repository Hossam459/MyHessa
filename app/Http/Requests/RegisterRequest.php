<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;


class RegisterRequest extends FormRequest
{
    /**
     * @return array
     */
public function rules()
{
    return [
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|min:6|confirmed',
        'role' => 'required|in:student,teacher',

        // student
        'grade_level' => 'required_if:role,student|exists:grade_levels,id',
        'mobile_number' => [
            'required',
            function ($attribute, $value, $fail) {
                if (request('role') === 'student') {
                    if (\DB::table('students')->where('mobile_number', $value)->exists()) {
                        $fail(__('Mobile number already exists'));
                    }
                }

                if (request('role') === 'teacher') {
                    if (\DB::table('teachers')->where('mobile_number', $value)->exists()) {
                        $fail(__('Mobile number already exists'));
                    }
                }
            }
        ],

        // teacher
        'subjects' => 'required_if:role,teacher|array',
        'subjects.*' => 'exists:subjects,id',
    ];
}

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json(['status' => '','message' =>$validator->errors()->first(),'data'=>null], 422));
    }
}