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
    public function rules(): array
    {
        return [
            'name'=>'required',
            'email'=>'required|email|unique:users',
            'password'=>'required|string|confirmed|min:6',
            // 'image_profile'=>'required|image|mimes:png,jpg,jpeg|max:2048',
            'birth_day'=>'required',
            'mobile_number'=>'required|string|min:11|unique:users',
            'address'=>'required|string',
            'first_name'=>'required|string',
            'last_name'=>'required|string',
            'goverment'=>'required|string',
            'city'=>'required|string',
            'role'=>'required|string',
            'grade'=>'required|string',

        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json(['status' => '','message' =>$validator->errors()->first(),'data'=>null], 422));
    }
}