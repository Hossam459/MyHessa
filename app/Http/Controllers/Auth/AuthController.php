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

class AuthController extends Controller
{
    use HttpResponses;
    use  Access;


    public function _construct(){
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
                'name'=>$request->name,
                'email'=>$request->email,
                'birth_day'=>$request->birth_day,
                'mobile_number'=>$request->mobile_number,
                'first_name'=>$request->first_name,
                'last_name'=>$request->last_name,
                'password'=>bcrypt($request->password),
                'image_profile'=> $photo ?: null ,
                'goverment'=>$request->goverment,
                'city'=>$request->city,
                'role'=>$request->role,
                'grade'=>$request->grade ?: null,
                ]
        );

        if ($request->role === 'teacher') {
        Teacher::create([
            'user_id'           => $user->id,
            'subject'           => $request->subject,
            'experience_years'  => $request->experience_years,
            'bio'               => $request->bio,
        ]);
    }
    
        return $this->success($user,'User successfully registered');
    }

    public function login(LoginRequest $request): JsonResponse
    {

        
        if ($token=auth()->attempt($request->all())) {
            $user = auth()->user();

            $user->tokens()->delete();

            $success = $this->createNewToken($token);

            return $this->success($success,'User Login successfully.');
        }

        return $this->error([], 'Email or Password wrong.');
    }
        
    

    public function createNewToken($token){
        
        return [
            'access_token'=>$token,
            'user'=>auth()->user()
        ];
    }

    public function profile(): JsonResponse
    {
        $user = auth()->user();
        if($user!=null){
        return $this->success($user, '') ;
    }
    else{
        return $this->error(null, 'unuthrized') ;
    }   
    }

    public function logout(): JsonResponse
    {
        $user = auth()->user();
        if($user!=null){
        auth()->logout();
        return $this->success(null,'User successfully logout');    
        }else{
            return $this->error(null, 'unuthrized') ;
        }  
    }

}
