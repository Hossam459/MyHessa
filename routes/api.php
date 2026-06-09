<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Pages\PageController;
use App\Http\Controllers\Language\LanguageController;
use App\Http\Controllers\Regions\RegionsController;
use App\Http\Controllers\Lesson\LessonController;
use App\Http\Controllers\Attendance\AttendanceController;
use App\Http\Controllers\Group\GroupController;
use App\Http\Controllers\GroupMembership\GroupMembershipController;
use App\Http\Controllers\Group\GroupMaterialsController;
use App\Http\Controllers\Group\GroupFeedController;
use App\Http\Controllers\Teachers\TeacherRatingController;
use App\Http\Controllers\Teachers\TeacherController;
use App\Http\Controllers\GradeLevel\GradeLevelController;
use App\Http\Controllers\Subjects\SubjectsController;
use App\Http\Controllers\App\AppVersionController;
use App\Http\Controllers\Student\StudentHomeController;
use App\Http\Controllers\Teacher\TeacherDashboardController;
use App\Http\Controllers\Student\FavoriteGroupController;
use App\Http\Controllers\Reports\ReportsController;
use App\Http\Controllers\Search\SearchController;
use App\Http\Controllers\Notifications\NotificationController;
use App\Models\User;


Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['middleware'=>'api','prefix'=>'auth'],function($router){
    Route::post('/register',[AuthController::class,'register']);
    Route::post('/login',[AuthController::class,'login']);
    Route::get('/profile',[AuthController::class,'profile']);
    Route::put('/profile',[AuthController::class,'updateProfile']);
    Route::post('/logout',[AuthController::class,'logout']);
    Route::delete('/delete-account',[AuthController::class,'deleteAccount']);
    Route::delete('/fcm-token', [NotificationController::class, 'removeFcmToken']);
    Route::post('/fcm-token', [NotificationController::class, 'registerFcmToken']);
    Route::post('/profile/photo', [AuthController::class,'updateProfilePhoto'])->middleware('auth:api');

    // Password Reset Routes
    Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword']);
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);

    // Email Verification Routes
    Route::post('/send-verification-email', [EmailVerificationController::class, 'sendVerificationEmail'])->middleware('auth:api');
    Route::post('/verify-email', [EmailVerificationController::class, 'verifyEmail']);
    Route::post('/resend-verification', [EmailVerificationController::class, 'resendVerificationEmail']);
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

Route::middleware('auth:api')->group(function () {
    Route::get('/attendance/lesson/{lessonId}/details', [AttendanceController::class, 'lessonDetails']);
    Route::get('/lessons/{lessonId}/attendance', [AttendanceController::class, 'lessonDetails']);
});




Route::middleware(['auth:api', 'role:student'])->group(function () {

    Route::get('/student/home', [StudentHomeController::class, 'index']);
    Route::get('/student/recommended-groups', [StudentHomeController::class, 'recommendedGroups']);
    Route::get('/student/pending-requests', [GroupMembershipController::class, 'studentPendingRequests']);

});

Route::middleware([
    'auth:api',
    'role:student'
])->group(function () {

    Route::post(
        '/groups/{groupId}/favorite',
        [FavoriteGroupController::class, 'toggle']
    );

    Route::get(
        '/favorite-groups',
        [FavoriteGroupController::class, 'index']
    );

});

Route::middleware(['auth:api', 'role:teacher'])->group(function () {
    Route::get('/teacher/home', [TeacherDashboardController::class, 'index']);
    Route::get('/teacher/students', [TeacherDashboardController::class, 'students']);
});

Route::middleware('auth:api')->prefix('reports')->group(function () {
    Route::get('/teacher', [ReportsController::class, 'teacherOverview']);
    Route::get('/groups/{groupId}/attendance', [ReportsController::class, 'groupAttendance']);
    Route::get('/students/{studentId}/attendance', [ReportsController::class, 'studentAttendance']);
});

Route::middleware('auth:api')->get('/search', [SearchController::class, 'index']);

Route::middleware('auth:api')->prefix('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/send', [NotificationController::class, 'sendToUsers']);
    Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::post('/{notificationId}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('/{notificationId}', [NotificationController::class, 'destroy']);
});

Route::group(['middleware'=>'api','prefix'=>'lessons'],function($router){
    Route::post('/create', [LessonController::class, 'createLesson'])->middleware(['auth:api','role:teacher']);
    Route::put('/{lessonId}/update', [LessonController::class, 'updateLesson'])->middleware(['auth:api','role:teacher']);
    Route::post('/{lessonId}/close',[LessonController::class,'closeLesson'])->middleware(['auth:api','role:teacher']);
    Route::post('/{lessonId}/cancel',[LessonController::class,'cancelLesson'])->middleware(['auth:api','role:teacher']);

    Route::middleware(['auth:api','role:teacher'])->get('/teacher', [LessonController::class, 'teacherLessons']);
    Route::middleware(['auth:api','role:student'])->get('/student', [LessonController::class, 'studentLessons']);
});


Route::group(['middleware'=>'api','prefix'=>'groups'],function($router){
    Route::get('/my-groups',[GroupController::class, 'index'])->middleware('auth:api');
    Route::post('/create',[GroupController::class,'create'])->middleware('auth:api');
    Route::put('/{groupId}/update',[GroupController::class,'update'])->middleware('auth:api');
    Route::delete('/{groupId}/delete',[GroupController::class,'delete'])->middleware('auth:api');
    Route::get('/{groupId}', [GroupController::class, 'show'])->middleware(['auth:api']);
    Route::post('/{groupId}/join-request', [GroupMembershipController::class, 'studentRequestJoin'])->middleware('auth:api');
    Route::post('/{groupId}/cancel-request', [GroupMembershipController::class, 'cancelRequest'])->middleware(['auth:api','role:student']);
    Route::post('/{groupId}/add-student',  [GroupMembershipController::class, 'teacherAddStudent'])->middleware('auth:api');
    Route::post('/{groupId}/leave', [GroupMembershipController::class, 'leave'])->middleware('auth:api');
    Route::post('/{groupId}/requests/{studentId}/approve', [GroupMembershipController::class, 'approve'])->middleware('auth:api');
    Route::post('/{groupId}/remove-student/{studentId}', [GroupMembershipController::class, 'removeStudent'])->middleware(['auth:api','role:teacher']);
    Route::post('/{groupId}/requests/{studentId}/reject',  [GroupMembershipController::class, 'reject'])->middleware('auth:api');
    Route::get('/{groupId}/pending', [GroupMembershipController::class,'listPending'])->middleware('auth:api');
    Route::get('/{groupId}/requests', [GroupMembershipController::class, 'listPending'])->middleware('auth:api');
  
    Route::get('/{groupId}/materials', [GroupMaterialsController::class, 'list'])->middleware('auth:api');
    Route::post('/{groupId}/materials', [GroupMaterialsController::class, 'upload'])->middleware(['auth:api','role:teacher']);
    Route::get('/{groupId}/materials/{attachmentId}/download', [GroupMaterialsController::class, 'download'])->middleware('auth:api');
    Route::delete('/{groupId}/materials/{attachmentId}', [GroupMaterialsController::class, 'delete'])->middleware(['auth:api','role:teacher']);
   
    Route::get('/{groupId}/students', [GroupController::class, 'students'])->middleware('auth:api');
    Route::get('/{groupId}/students/{studentId}', [GroupController::class, 'studentDetails'])->middleware('auth:api');
    Route::get('/{groupId}/members', [GroupController::class, 'students'])->middleware('auth:api');
    Route::get('/{groupId}/members/{studentId}', [GroupController::class, 'studentDetails'])->middleware('auth:api');
    Route::get('/{groupId}/lessons', [LessonController::class, 'groupLessons'])->middleware('auth:api');

    Route::get('/{groupId}/feed', [GroupFeedController::class, 'list'])->middleware('auth:api');

    Route::post('/{groupId}/feed', [GroupFeedController::class, 'create'])->middleware(['auth:api','role:teacher']);
    Route::get('/{groupId}/feed/attachments/{attachmentId}/download', [GroupFeedController::class, 'downloadAttachment'])->middleware('auth:api');
    Route::put('/{groupId}/feed/{postId}', [GroupFeedController::class, 'update'])->middleware(['auth:api','role:teacher']);
    Route::post('/{groupId}/feed/{postId}/update', [GroupFeedController::class, 'update'])->middleware(['auth:api','role:teacher']);
    Route::delete('/{groupId}/feed/{postId}', [GroupFeedController::class, 'delete'])->middleware(['auth:api','role:teacher']);
    Route::post('/{groupId}/feed/{postId}/pin', [GroupFeedController::class, 'togglePin'])->middleware(['auth:api','role:teacher']);

    Route::get('/{groupId}/overview', [GroupController::class, 'overview'])->middleware(['auth:api','role:student']);
    Route::get('/{groupId}/attendance', [GroupController::class, 'groupAttendance'])->middleware(['auth:api','role:student','role:teacher'] );
});


Route::group(['middlewar'=>'api','prefix'=>'teachers'],function($router) {
    Route::get('/{teacherId}', [TeacherController::class, 'show'])->middleware('auth:api');
    Route::post('/{teacherId}/rate', [TeacherRatingController::class, 'rate'])->middleware('auth:api');
    Route::get('/{teacherId}/ratings', [TeacherRatingController::class, 'list'])->middleware('auth:api');
    Route::get('/{teacherId}/rating-summary', [TeacherRatingController::class, 'summary'])->middleware('auth:api');
});


Route::prefix('grade-levels')->group(function () {
    Route::get('/', [GradeLevelController::class, 'index']);
    Route::get('/{id}', [GradeLevelController::class, 'show']);
});

Route::prefix('subjects')->group(function () {
    Route::get('/', [SubjectsController::class, 'index']);
    Route::get('/grouped-by-stage', [SubjectsController::class, 'groupedByStage']);
    Route::get('/by-grade-level', [SubjectsController::class, 'byGradeLevel']);
});


Route::get('/version/{platform}', [AppVersionController::class, 'check']);

