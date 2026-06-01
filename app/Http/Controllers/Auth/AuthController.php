<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Auth;
use Validator;
use App\Mail\EmailVerificationMail;
use App\Models\EmailVerificationToken;
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
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    use HttpResponses;
    use  Access;


    public function __construct(){
        $this->middleware('auth:api',['except'=>['login','register']]);
    }

public function register(RegisterRequest $request): JsonResponse
{
    $photo = null;

        
       $photo = $request->file('image_profile')
    ? $request->file('image_profile')->store('users', 'public')
    : null;
    

    $user = User::create([
        'user_name'     => $request->name,
        'email'         => $request->email,
        'password'      => bcrypt($request->password),
        'image_profile' => $photo,
        'role'          => $request->role,
        'is_verified'   => 0,
    ]);

    if ($request->role === 'teacher') {
        $teacher = Teacher::create([
            'user_id'        => $user->id,
            'bio'            => $request->bio,
            'birth_day'      => $request->birth_day,
            'mobile_number'  => $request->mobile_number,
            'first_name'     => $request->first_name,
            'last_name'      => $request->last_name,
            'goverment_id'   => $request->goverment,
            'city_id'        => $request->city,
        ]);

        $teacher->subjects()->attach($request->subjects);
    }

    if ($request->role === 'student') {
        Student::create([
            'user_id'         => $user->id,
            'grade_level_id'  => $request->grade_level,
            'parent_name'     => $request->parent_name,
            'parent_contact'  => $request->parent_contact,
            'birth_day'       => $request->birth_day,
            'mobile_number'   => $request->mobile_number,
            'first_name'      => $request->first_name,
            'last_name'       => $request->last_name,
            'goverment_id'    => $request->goverment,
            'city_id'         => $request->city,
        ]);
    }

    $user->loadMissing(['student.gradeLevel', 'teacher.subjects']);

    try {
        $this->sendVerificationCode($user);
    } catch (\Exception $e) {
        return $this->error([], __('messages.email_send_failed'), 500);
    }

    return $this->success([
        'user' => new UserResource($user)
    ], __('messages.register_success'));
}

private function sendVerificationCode(User $user): void
{
    $code = $this->generateVerificationCode();

    $user->emailVerificationTokens()->delete();
    $user->emailVerificationTokens()->create([
        'token' => $code,
        'expires_at' => now()->addMinutes(15),
    ]);

    Mail::to($user->email)->send(new EmailVerificationMail($user->email, $code));
}

private function generateVerificationCode(): string
{
    do {
        $code = (string) random_int(100000, 999999);
    } while (EmailVerificationToken::where('token', $code)->exists());

    return $code;
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
        'user' => new UserResource($user)
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
    }

    $user->loadMissing(['student.gradeLevel', 'teacher.subjects']);

    return $this->success(
        new UserResource($user)
    );
}

public function updateProfile(Request $request): JsonResponse
{
    $user = auth()->user();

    if (!$user) {
        return $this->error(null, __('messages.unauthorized'), 401);
    }

    $profileTable = $user->role === 'teacher' ? 'teachers' : 'students';
    $profile = $user->role === 'teacher' ? $user->teacher : $user->student;
    $profileId = $profile?->id;

    $validator = Validator::make($request->all(), [
        'name' => ['sometimes', 'string', 'max:255'],
        'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
        'image_profile' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],

        'first_name' => ['sometimes', 'nullable', 'string', 'max:255'],
        'last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
        'mobile_number' => [
            'sometimes',
            'string',
            'max:30',
            Rule::unique($profileTable, 'mobile_number')->ignore($profileId),
        ],
        'birth_day' => ['sometimes', 'nullable', 'date'],
        'goverment' => ['sometimes', 'nullable', 'exists:governorates,id'],
        'city' => ['sometimes', 'nullable', 'exists:cities,id'],

        'grade_level' => [Rule::requiredIf($user->role === 'student' && !$profile), 'sometimes', 'exists:grade_levels,id'],
        'parent_name' => ['sometimes', 'nullable', 'string', 'max:255'],
        'parent_contact' => ['sometimes', 'nullable', 'string', 'max:30'],

        'bio' => ['sometimes', 'nullable', 'string'],
        'subjects' => [Rule::requiredIf($user->role === 'teacher' && !$profile), 'sometimes', 'array'],
        'subjects.*' => ['exists:subjects,id'],
    ]);

    if ($validator->fails()) {
        return $this->error($validator->errors(), __('profile.invalid_data'), 422);
    }

    $userData = [];

    if ($request->has('name')) {
        $userData['user_name'] = $request->name;
    }

    if ($request->has('email')) {
        $userData['email'] = $request->email;
    }

    if ($request->hasFile('image_profile')) {
        if ($user->image_profile) {
            Storage::disk('public')->delete($user->image_profile);
            Storage::disk('public')->delete('users/' . $user->image_profile);
        }

        $userData['image_profile'] = $request->file('image_profile')->store('users', 'public');
    }

    if ($userData) {
        $user->update($userData);
    }

    if ($user->role === 'student') {
        $studentData = $this->profilePayload($request, [
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'mobile_number' => 'mobile_number',
            'birth_day' => 'birth_day',
            'parent_name' => 'parent_name',
            'parent_contact' => 'parent_contact',
            'grade_level' => 'grade_level_id',
            'goverment' => 'goverment_id',
            'city' => 'city_id',
        ]);

        $user->student()->updateOrCreate(['user_id' => $user->id], $studentData);
    }

    if ($user->role === 'teacher') {
        $teacherData = $this->profilePayload($request, [
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'mobile_number' => 'mobile_number',
            'birth_day' => 'birth_day',
            'bio' => 'bio',
            'goverment' => 'goverment_id',
            'city' => 'city_id',
        ]);

        $teacher = $user->teacher()->updateOrCreate(['user_id' => $user->id], $teacherData);

        if ($request->has('subjects')) {
            $teacher->subjects()->sync($request->subjects);
        }
    }

    $user = $user->fresh();
    $user->loadMissing(['student.gradeLevel', 'teacher.subjects']);

    return $this->success(new UserResource($user), __('profile.updated'));
}

private function profilePayload(Request $request, array $fields): array
{
    $payload = [];

    foreach ($fields as $requestKey => $column) {
        if ($request->has($requestKey)) {
            $payload[$column] = $request->input($requestKey);
        }
    }

    return $payload;
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
            Storage::disk('public')->delete($user->image_profile);
            Storage::disk('public')->delete('users/' . $user->image_profile);
            \Storage::delete('public/users/' . $user->image_profile);
        }

        $user->image_profile = $request->file('image_profile')->store('users', 'public');
        $user->save();

        return $this->success($user->fresh(), __('profile.updated'));
    }

}
