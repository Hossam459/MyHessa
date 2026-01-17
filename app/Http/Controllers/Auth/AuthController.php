<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Auth;
use Validator;
use App\Models\User;
use App\Notifications\SendPushNotification;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Traits\HttpResponses;
use Illuminate\Http\JsonResponse;
use App\Http\Traits\Access;
use App\Models\Student;
use App\Models\Teacher;

class AuthController extends Controller
{
    use HttpResponses;
    use  Access;


    public function __construct(){
        $this->middleware('auth:api',['except'=>['login','register']]);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $photo=null;
        if($request->image_profile!=null){
        $image=$request->image_profile->store('public/uploads/');
        $photo= $request->image_profile->hashName();
        }
        $user=User::create(
            [
                'user_name'=>$request->name,
                'email'=>$request->email,
                'password'=>bcrypt($request->password),
                'image_profile'=> $photo ?: null ,
                'role'=>$request->role,
                ]
        );

        if ($request->role === 'teacher') {
      $teacher =   Teacher::create([
            'user_id'           => $user->id,
            'bio'               => $request->bio,
            'birth_day'=>$request->birth_day,
            'mobile_number'=>$request->mobile_number,
            'first_name'=>$request->first_name,
            'last_name'=>$request->last_name,
            'goverment_id'=>$request->goverment,
            'city_id'=>$request->city,
        ]);
        $teacher->subjects()->attach($request->subjects);
        $user->profile = $teacher->load('subjects');

    }
    else if ($request->role === 'student') {
       $student= Student::create([
            'user_id'           => $user->id,
            'grade_level_id'       => $request->grade_level,
            'parent_name'       => $request->parent_name,
            'parent_contact'    => $request->parent_contact,
            'birth_day'=>$request->birth_day,
            'mobile_number'=>$request->mobile_number,
            'first_name'=>$request->first_name,
            'last_name'=>$request->last_name,
            'goverment_id'=>$request->goverment,
            'city_id'=>$request->city,
        ]);
        $user->profile = $student->load('gradeLevel');
    }
    
        $user = $user->fresh();

    return $this->success([
        'user' => $user,
        'profile' => $related
    ], __('messages.register_success'));
    }

   public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only(['email', 'password']);

        if (!$token = auth()->attempt($credentials)) {
            return $this->error([], __('messages.invalid_credentials'));
        }

        $user = auth()->user();
        $user->loadMissing(['student.gradeLevel', 'teacher.subjects']);

        return $this->success([
            'access_token' => $token,
            'user' => $user
        ], __('messages.login_success'));
    }
        
    

public function createNewToken($token){
    return [
        'access_token' => $token,
        'token_type' => 'bearer',
        'expires_in' => auth()->factory()->getTTL() * 60,
        'user' => auth()->user()
    ];
}


    public function profile(): JsonResponse
    {
      $user = auth()->user();
        if (!$user) {
            return $this->error(null, __('messages.unauthorized'));
        }else{

        $user->loadMissing(['student.gradeLevel', 'teacher.subjects']);
        $user->profile = $user->role === 'student' ? $user->student : $user->teacher;

        return $this->success($user);
        }

        
    }

    public function logout(): JsonResponse
    {
        $user = auth()->user();
        if($user!=null){
        auth()->logout();
        return $this->success(null,__('messages.logout_success'));    
        }else{
            return $this->error(null,  __('messages.unauthorized')) ;
        }  
    }

        public function updateProfilePhoto(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return $this->error(null, __('messages.unauthorized'), 401);
        }

        $validator = Validator::make($request->all(), [
            'image_profile' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096',
        ], [
            'image_profile.required' => __('profile.image_required'),
            'image_profile.image'    => __('profile.image_invalid'),
            'image_profile.mimes'    => __('profile.image_type_not_allowed'),
            'image_profile.max'      => __('profile.image_too_large'),
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), __('profile.invalid_data'), 422);
        }

        // delete old
        if ($user->image_profile) {
            \Storage::delete('public/users/' . $user->image_profile);
        }

        $request->file('image_profile')->store('public/users');
        $user->image_profile = $request->file('image_profile')->hashName();
        $user->save();

        return $this->success($user->fresh(), __('profile.updated'));
    }

}
