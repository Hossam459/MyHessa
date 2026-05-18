<?php

namespace App\Http\Controllers\Group;

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
use App\Models\GroupMembership;
use App\Models\GroupSchedule;
use App\Models\Group;
use App\Models\Lesson;
use App\Http\Resources\UserResource;
use App\Http\Requests\GroupRequest;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GroupController extends Controller {
    use HttpResponses;

    private const LESSON_SEASON_START_MONTH = 5;
    private const LESSON_SEASON_END_MONTH = 9;

    public function create(GroupRequest $request) {
        DB::transaction(function() use ($request, &$group) {
            $group = Group::create($request->only(['name','description','subject_id','grade_level_id','max_students','price','teacher_id'
]));
            foreach ($request->schedules as $s) {
                $this->checkScheduleConflict($group->id,$s['day_of_week'],$s['start_time'],$s['end_time']);
                GroupSchedule::create(['group_id'=>$group->id]+$s);
            }
        });
        $group->load('schedules');
        $this->generateLessonsFromSchedules($group);

        return $this->success([
            'id' => $group->id,
            'name' => $group->name,
            'description' => $group->description,
            'subject_id' => $group->subject_id,
            'grade_level_id' => $group->grade_level_id,
            'max_students' => $group->max_students,
            'price' => $group->price,
            'teacher_id' => $group->teacher_id,
            'group_schedules' => $group->schedules,
        ], __('group.created'));
    }

    public function update(GroupRequest $request,$groupId) {
        $group = Group::findOrFail($groupId);
        DB::transaction(function() use ($request,$group) {
            $group->update($request->only(['name','description','subject_id','grade_level_id','max_students','price','teacher_id']));
            $group->schedules()->delete();
            foreach($request->schedules as $s){
                $this->checkScheduleConflict($group->id,$s['day_of_week'],$s['start_time'],$s['end_time']);
                GroupSchedule::create(['group_id'=>$group->id]+$s);
            }
        });
        $group->load('schedules');
        $this->generateLessonsFromSchedules($group);

        return $this->success([
            'id' => $group->id,
            'name' => $group->name,
            'description' => $group->description,
            'subject_id' => $group->subject_id,
            'grade_level_id' => $group->grade_level_id,
            'max_students' => $group->max_students,
            'price' => $group->price,
            'teacher_id' => $group->teacher_id,
            'group_schedules' => $group->schedules,
        ], __('group.updated'));
    }

    private function generateLessonsFromSchedules(Group $group)
    {
        $now = Carbon::now();

        // تحديد بداية ونهاية الموسم
        $seasonStart = Carbon::create($now->year, self::LESSON_SEASON_START_MONTH, 1)->startOfDay();
        $seasonEnd = Carbon::create($now->year, self::LESSON_SEASON_END_MONTH, 30)->endOfDay();

        if ($now->month > self::LESSON_SEASON_END_MONTH) {
            $seasonStart = Carbon::create($now->year + 1, self::LESSON_SEASON_START_MONTH, 1)->startOfDay();
            $seasonEnd = Carbon::create($now->year + 1, self::LESSON_SEASON_END_MONTH, 30)->endOfDay();
        }

        foreach ($group->schedules as $schedule) {
            $currentLessonDate = $this->getNextLessonDateForSchedule($schedule->day_of_week, $schedule->start_time, $now);

            while ($currentLessonDate->lte($seasonEnd)) {
                $lessonDateString = $currentLessonDate->toDateString();

                $exists = Lesson::where('schedule_id', $schedule->id)
                    ->where('lesson_date', $lessonDateString)
                    ->exists();

                if (!$exists) {
                    Lesson::create([
                        'group_id'         => $group->id,
                        'teacher_id'       => $group->teacher_id,
                        'schedule_id'      => $schedule->id,
                        'title'            => $group->name . ' Lesson',
                        'lesson_date'      => $lessonDateString,
                        'start_time'       => Carbon::parse($lessonDateString . ' ' . $schedule->start_time),
                        'end_time'         => Carbon::parse($lessonDateString . ' ' . $schedule->end_time),
                        'attendance_status'=> 'pending',
                    ]);
                }

                $currentLessonDate->addWeek();
            }
        }
    }

    private function getNextLessonDateForSchedule(int $dayOfWeek, string $startTime, Carbon $now): Carbon
    {
        if ($now->month < self::LESSON_SEASON_START_MONTH) {
            $seasonStart = Carbon::create($now->year, self::LESSON_SEASON_START_MONTH, 1)->startOfDay();
            return $this->getFirstScheduleDateInSeason($dayOfWeek, $seasonStart);
        }

        if ($now->month > self::LESSON_SEASON_END_MONTH) {
            $seasonStart = Carbon::create($now->year + 1, self::LESSON_SEASON_START_MONTH, 1)->startOfDay();
            return $this->getFirstScheduleDateInSeason($dayOfWeek, $seasonStart);
        }

        $candidate = $this->getFirstScheduleDateInSeason($dayOfWeek, $now->copy()->startOfDay());

        if ($candidate->isSameDay($now) && $now->gt(Carbon::parse($candidate->toDateString() . ' ' . $startTime))) {
            $candidate->addWeek();
        }

        $seasonEnd = Carbon::create($now->year, self::LESSON_SEASON_END_MONTH, 30)->endOfDay();
        if ($candidate->greaterThan($seasonEnd)) {
            $seasonStart = Carbon::create($now->year + 1, self::LESSON_SEASON_START_MONTH, 1)->startOfDay();
            return $this->getFirstScheduleDateInSeason($dayOfWeek, $seasonStart);
        }

        return $candidate;
    }

    private function getFirstScheduleDateInSeason(int $dayOfWeek, Carbon $startDate): Carbon
    {
        $currentDay = $startDate->dayOfWeekIso;
        $daysUntilSchedule = ($dayOfWeek - $currentDay + 7) % 7;
        return $startDate->copy()->addDays($daysUntilSchedule);
    }

    public function delete($groupId) {
        $group = Group::findOrFail($groupId);
        $group->delete();
        return $this->success(null, __('group.deleted'));
    }

    public function show($groupId) {
        $user = auth()->user();
        if (!$user) return $this->error(null, __('messages.unauthorized'), 401);

        $group = Group::with(['subject', 'gradeLevel', 'teacher.user', 'schedules'])
            ->findOrFail($groupId);

        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';

        return $this->success([
            'id' => $group->id,
            'name' => $group->name,
            'description' => $group->description,
            'subject_id' => $group->subject_id,
            'subject' => [
                'id' => $group->subject?->id,
                'name' => $locale === 'ar'
                    ? $group->subject?->name_ar
                    : $group->subject?->name_en,
            ],
            'grade_level_id' => $group->grade_level_id,
            'grade_level' => [
                'id' => $group->gradeLevel?->id,
                'name' => $locale === 'ar'
                    ? $group->gradeLevel?->name_ar
                    : $group->gradeLevel?->name_en,
            ],
            'max_students' => $group->max_students,
            'price' => $group->price,
            'teacher_id' => $group->teacher_id,
            'teacher' => [
                'id' => $group->teacher->id,
                'name' => $group->teacher->user->user_name,
                'image' => $group->teacher->user->image_profile
                    ? asset('storage/users/' . $group->teacher->user->image_profile)
                    : null,
            ],
            'group_schedules' => $group->schedules,
            'is_favorite' => $this->isFavoriteGroup($group),
        ], __('messages.success'));
    }

    private function isFavoriteGroup(Group $group): bool
    {
        return auth()->user()?->student
            ? auth()->user()
                ->student
                ->favoriteGroups()
                ->where('group_id', $group->id)
                ->exists()
            : false;
    }

    private function checkScheduleConflict($groupId,$day,$start,$end){
        $conflict = GroupSchedule::where('group_id',$groupId)->where('day_of_week',$day)
            ->where(function($q) use ($start,$end){
                $q->whereBetween('start_time',[$start,$end])
                  ->orWhereBetween('end_time',[$start,$end])
                  ->orWhereRaw('? BETWEEN start_time AND end_time',[$start])
                  ->orWhereRaw('? BETWEEN start_time AND end_time',[$end]);
            })->exists();
        if($conflict) abort(422, __('group.schedule_conflict'));
    }

    public function students($groupId)
{
    $group = Group::findOrFail($groupId);

    $students = GroupMembership::with(['student.user'])
        ->where('group_id', $group->id)
        ->where('status', 'approved')
        ->orderBy('id')
        ->get()
        ->map(function ($m) {
            return [
                'student_id' => $m->student_id,
                'name'       => $m->student?->user?->user_name,
                'image_profile_url' => $m->student?->user?->image_profile_url,

            ];
        });

    return $this->success($students, __('group.students_list'));
}


public function overview(Request $request, $groupId)
{
    $user = auth()->user();
    if (!$user || !$user->student) return $this->error(null, __('messages.unauthorized'), 401);

    $group = \App\Models\Group::with(['subject', 'gradeLevel', 'teacher.user', 'schedules'])
        ->findOrFail($groupId);

    $isApproved = \App\Models\GroupMembership::where('group_id', $group->id)
        ->where('student_id', $user->student->id)
        ->where('status', 'approved')
        ->exists();

    if (!$isApproved) return $this->error(null, __('group.forbidden_group_overview'), 403);

    $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';

    $feed = \App\Models\GroupPost::with(['attachments', 'teacher.user'])
        ->where('group_id', $group->id)
        ->orderByDesc('is_pinned')
        ->orderByDesc('id')
        ->limit(10)
        ->get()
        ->map(function ($p) {
            return [
                'id' => $p->id,
                'content' => $p->content,
                'is_pinned' => (bool)$p->is_pinned,
                'teacher_name' => $p->teacher?->user?->user_name,
                'teacher_image_profile_url' => $p->teacher?->user?->image_profile
                    ? asset('storage/users/' . $p->teacher->user->image_profile)
                    : null,
                'created_at' => $p->created_at,
                'attachments' => $p->attachments->map(fn($a) => [
                    'id' => $a->id,
                    'file_name' => $a->file_name,
                    'mime_type' => $a->mime_type,
                    'file_size' => $a->file_size,
                    'url' => asset('storage/' . ltrim($a->file_path, '/')),
                ])
            ];
        });

    $materials = \App\Models\GroupAttachment::where('group_id', $group->id)
        ->orderByDesc('id')
        ->get()
        ->map(fn($a) => [
            'id' => $a->id,
            'title' => $a->title,
            'file_name' => $a->file_name,
            'mime_type' => $a->mime_type,
            'file_size' => $a->file_size,
            'url' => asset('storage/' . ltrim($a->file_path, '/')),
            'created_at' => $a->created_at,
        ]);

    return $this->success([
        'group' => [
            'id' => $group->id,
            'name' => $group->name,
            'description' => $group->description,
            'price' => $group->price,
            'max_students' => $group->max_students,
            'subject' => [
                'id' => $group->subject?->id,
                'name' => $locale === 'ar'
                    ? $group->subject?->name_ar
                    : $group->subject?->name_en,
            ],
            'grade_level' => [
                'id' => $group->gradeLevel?->id,
                'name' => $locale === 'ar'
                    ? $group->gradeLevel?->name_ar
                    : $group->gradeLevel?->name_en,
            ],
            'group_schedules' => $group->schedules,
            'teacher' => [
                'id' => $group->teacher->id,
                'name' => $group->teacher->user->user_name,
                'image_profile_url' => $group->teacher->user->image_profile
                    ? asset('storage/users/' . $group->teacher->user->image_profile)
                    : null,
            ],
            'is_favorite' => $this->isFavoriteGroup($group),
        ],
        'feed' => $feed,
        'materials' => $materials,
    ], __('group.overview'));
}


 public function index(): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {

            return $this->error(
                null,
                __('messages.unauthorized'),
                401
            );
        }

        // =========================
        // STUDENT
        // =========================

        if ($user->role === 'student') {

            $student = $user->student;

            $groups = GroupMembership::with([
                    'group.subject',
                    'group.gradeLevel',
                    'group.teacher.user',
                    'group.schedules',
                ])
                ->where('student_id', $student->id)
                ->where(
                    'status',
                    GroupMembership::STATUS_APPROVED
                )
                ->latest()
                ->get()
                ->map(function ($membership) {

                    $group = $membership->group;

                    return [
                        'id' => $group->id,
                        'name' => $group->name,
                        'description' => $group->description,
                        'price' => $group->price,

                        'subject' => [
                            'id' => $group->subject?->id,
                            'name' => app()->getLocale() === 'ar'
    ? $group->subject?->name_ar
    : $group->subject?->name_en,
                        ],

                        'grade_level' => [
                            'id' => $group->gradeLevel?->id,
                            'name' => app()->getLocale() === 'ar'
    ? $group->gradeLevel?->name_ar
    : $group->gradeLevel?->name_en,
                        ],

                        'group_schedules' => $group->schedules,

                        'teacher' => [
                            'id' => $group->teacher?->id,
                            'name' => $group->teacher?->user?->user_name,
                            'image' => $group->teacher?->user?->image_profile_url,
                        ],
                        'is_favorite' => $this->isFavoriteGroup($group),
                        'joined_at' => $membership->joined_at,
                    ];
                });

            return $this->success(
                $groups,
                'Student groups loaded successfully'
            );
        }

        // =========================
        // TEACHER
        // =========================

        if ($user->role === 'teacher') {

            $teacher = $user->teacher;

            $groups = Group::with([
                    'subject',
                    'gradeLevel',
                    'schedules',
                ])
                ->withCount('approvedStudents')
                ->where('teacher_id', $teacher->id)
                ->latest()
                ->get()
                ->map(function ($group) {

                    return [
                        'id' => $group->id,
                        'name' => $group->name,
                        'description' => $group->description,
                        'price' => $group->price,

                        'subject' => [
                            'id' => $group->subject?->id,
                            'name' => app()->getLocale() === 'ar'
    ? $group->subject?->name_ar
    : $group->subject?->name_en,
                        ],

                        'grade_level' => [
                            'id' => $group->gradeLevel?->id,
                            'name' => app()->getLocale() === 'ar'
    ? $group->gradeLevel?->name_ar
    : $group->gradeLevel?->name_en,
                        ],

                        'group_schedules' => $group->schedules,

                        'students_count' => $group->approved_students_count,
                    ];
                });

            return $this->success(
                $groups,
                'Teacher groups loaded successfully'
            );
        }

        return $this->error(
            null,
            __('messages.unauthorized'),
            403
        );
    }
}
