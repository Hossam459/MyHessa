<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Pages\PageController;
use App\Http\Controllers\Language\LanguageController;
use App\Http\Controllers\Regions\RegionsController;
use App\Http\Controllers\Lesson\LessonController;
use App\Http\Controllers\Attendance\AttendanceController;
use App\Http\Controllers\Group\GroupController;
use App\Http\Controllers\Group\GroupMembershipController;
use App\Http\Controllers\Groups\GroupAttachmentController;
use App\Http\Controllers\Teachers\TeacherRatingController;
Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['middleware'=>'api','prefix'=>'auth'],function($router){
    Route::post('/register',[AuthController::class,'register']);
    Route::post('/login',[AuthController::class,'login']);
    Route::get('/profile',[AuthController::class,'profile']);
    Route::post('/logout',[AuthController::class,'logout']);
});

Route::group(['middlewar'=>'api','prefix'=>'pages'],function($router){
    Route::get('/about',[PageController::class,'about']);
    Route::get('/terms_conditions',[PageController::class,'termsAndCondition']);
    Route::get('/privacy_policy',[PageController::class,'privacyPolicy']);
});

Route::get('/{lang}/lang-test', function () {
    return response()->json([
        'message' => __('messages.register_success')
    ]);
});

Route::get('/change/{lang}',[LanguageController::class,'changeLang'])->name('changeLang');

Route::get('/governorates', [RegionsController::class, 'getAllGovernorates']);

Route::middleware(['auth:api','role:teacher'])->group(function(){
    Route::post('/session/{id}/start',[AttendanceController::class,'startSession']);
    Route::post('/attendance/mark',[AttendanceController::class,'markBulk']);
    Route::post('/session/{id}/close',[AttendanceController::class,'closeSession']);
    Route::get('/attendance/lesson/{lessonId}/students', [AttendanceController::class, 'studentsForLesson']);
});

Route::group(['middlewar'=>'api','prefix'=>'lessons'],function($router){
    Route::post('/create', [LessonController::class, 'createLesson']);
    Route::put('/{lessonId}/update', [LessonController::class, 'updateLesson']);
        Route::post('/{lessonId}/close',[LessonController::class,'closeLesson']);

});


Route::group(['middlewar'=>'api','prefix'=>'groups'],function($router){
 Route::post('/create',[GroupController::class,'create']);
    Route::put('/{groupId}/update',[GroupController::class,'update']);
    Route::delete('/{groupId}/delete',[GroupController::class,'delete']);
    Route::post('{groupId}/join-request', [GroupMembershipController::class, 'studentRequestJoin']);
    Route::post('{groupId}/add-student',  [GroupMembershipController::class, 'teacherAddStudent']);
    Route::post('{groupId}/requests/{studentId}/approve', [GroupMembershipController::class, 'approve']);
    Route::post('{groupId}/requests/{studentId}/reject',  [GroupMembershipController::class, 'reject']);
    Route::get('{groupId}/requests', [GroupMembershipController::class, 'listPending']);
    Route::get('/{groupId}/attachments', [GroupAttachmentController::class, 'list']);
    Route::post('/{groupId}/attachments', [GroupAttachmentController::class, 'upload']);
    Route::delete('/{groupId}/attachments/{attachmentId}', [GroupAttachmentController::class, 'delete']);
    Route::get('/{groupId}/students', [GroupController::class, 'students']);
});


Route::group(['middlewar'=>'api','prefix'=>'teachers'],function($router) {
    Route::post('/{teacherId}/rate', [TeacherRatingController::class, 'rate']);
    Route::get('/{teacherId}/ratings', [TeacherRatingController::class, 'list']);
    Route::get('/{teacherId}/rating-summary', [TeacherRatingController::class, 'summary']);
});

