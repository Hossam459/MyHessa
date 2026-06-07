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
        $user = auth()->user();
        if (!$user || !$user->teacher) {
            return $this->error(null, __('group.not_group_teacher'), 403);
        }

        if ($request->filled('teacher_id') && (int) $request->teacher_id !== (int) $user->teacher->id) {
            return $this->error(null, __('group.not_group_teacher'), 403);
        }

        $group = null;

        DB::transaction(function() use ($request, $user, &$group) {
            $groupData = $request->only(['name','description','subject_id','grade_level_id','max_students','price','start_date','end_date']);
            $groupData['teacher_id'] = $user->teacher->id;

            $group = Group::create($groupData);
            foreach ($request->schedules as $s) {
                $this->checkScheduleConflict($group->id,$s['day_of_week'],$s['start_time'],$s['end_time']);
                GroupSchedule::create(['group_id'=>$group->id]+$s);
            }
        });

        if (!$group) {
            return $this->error(null, __('messages.invalid_data'), 422);
        }

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
            'start_date' => $group->start_date?->toDateString(),
            'end_date' => $group->end_date?->toDateString(),
            'group_schedules' => $group->schedules,
        ], __('group.created'));
    }

    public function update(GroupRequest $request,$groupId) {
        $group = Group::findOrFail($groupId);

        $user = auth()->user();
        if (!$user || !$user->teacher || (int) $group->teacher_id !== (int) $user->teacher->id) {
            return $this->error(null, __('group.not_group_teacher'), 403);
        }

        DB::transaction(function() use ($request,$group) {
            $groupData = $request->only(['name','description','subject_id','grade_level_id','max_students','price','start_date','end_date']);

            if ($groupData) {
                $group->update($groupData);
            }

            if ($request->has('schedules')) {
                $group->schedules()->delete();
                foreach($request->schedules as $s){
                    $this->checkScheduleConflict($group->id,$s['day_of_week'],$s['start_time'],$s['end_time']);
                    GroupSchedule::create(['group_id'=>$group->id]+$s);
                }
            }
        });
        $group->load('schedules');

        if ($request->has('schedules') || $request->has('start_date') || $request->has('end_date')) {
            $this->generateLessonsFromSchedules($group);
        }

        return $this->success([
            'id' => $group->id,
            'name' => $group->name,
            'description' => $group->description,
            'subject_id' => $group->subject_id,
            'grade_level_id' => $group->grade_level_id,
            'max_students' => $group->max_students,
            'price' => $group->price,
            'teacher_id' => $group->teacher_id,
            'start_date' => $group->start_date?->toDateString(),
            'end_date' => $group->end_date?->toDateString(),
            'group_schedules' => $group->schedules,
        ], __('group.updated'));
    }

    private function generateLessonsFromSchedules(Group $group)
    {
        $now = Carbon::now();
        [$rangeStart, $rangeEnd] = $this->lessonGenerationRange($group, $now);

        foreach ($group->schedules as $schedule) {
            $currentLessonDate = $this->getFirstScheduleDateInSeason($schedule->day_of_week, $rangeStart);

            while ($currentLessonDate->lte($rangeEnd)) {
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

    private function lessonGenerationRange(Group $group, Carbon $now): array
    {
        if ($group->start_date && $group->end_date) {
            return [
                Carbon::parse($group->start_date)->startOfDay(),
                Carbon::parse($group->end_date)->endOfDay(),
            ];
        }

        $seasonStart = Carbon::create($now->year, self::LESSON_SEASON_START_MONTH, 1)->startOfDay();
        $seasonEnd = Carbon::create($now->year, self::LESSON_SEASON_END_MONTH, 30)->endOfDay();

        if ($now->month > self::LESSON_SEASON_END_MONTH) {
            $seasonStart = Carbon::create($now->year + 1, self::LESSON_SEASON_START_MONTH, 1)->startOfDay();
            $seasonEnd = Carbon::create($now->year + 1, self::LESSON_SEASON_END_MONTH, 30)->endOfDay();
        }

        return [$seasonStart, $seasonEnd];
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

        $group = Group::withCount('approvedStudents')
            ->with(['subject', 'gradeLevel', 'teacher.user', 'schedules'])
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
            'start_date' => $group->start_date?->toDateString(),
            'end_date' => $group->end_date?->toDateString(),
            'teacher_id' => $group->teacher_id,
            'teacher' => [
                'id' => $group->teacher->id,
                'name' => $group->teacher->user->user_name,
                'image' => $group->teacher->user->image_profile_url,
                'rating' => $group->teacher->averageRating(),
                'ratings_count' => $group->teacher->ratingsCount(),
            ],
            'group_schedules' => $group->schedules,
            'is_can_join' => $group->isCanJoin,
            'is_already_joined' => $group->isJoinedByStudent($user?->student?->id),
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
                'id' => $m->student_id,
                'name'       => $m->student?->user?->user_name,
                'image_profile_url' => $m->student?->user?->image_profile_url,

            ];
        });

    return $this->success($students, __('group.students_list'));
}

public function studentDetails($groupId, $studentId)
{
    $user = auth()->user();
    if (!$user) return $this->error(null, __('messages.unauthorized'), 401);

    $group = Group::findOrFail($groupId);

    $membership = GroupMembership::with([
            'student.user',
            'student.gradeLevel',
            'student.governorate',
            'student.city',
        ])
        ->where('group_id', $group->id)
        ->where('student_id', $studentId)
        ->first();

    if (!$membership) {
        return $this->error(null, __('messages.not_found'), 404);
    }

    $isGroupTeacher = $user->teacher && (int) $group->teacher_id === (int) $user->teacher->id;
    $isSameStudent = $user->student && (int) $user->student->id === (int) $studentId;

    if (!$isGroupTeacher && !$isSameStudent) {
        return $this->error(null, __('group.not_group_teacher'), 403);
    }

    $student = $membership->student;
    $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';

    return $this->success([
        'student_id' => $student->id,
        'user_id' => $student->user_id,
        'name' => $student->user?->user_name,
        'email' => $student->user?->email,
        'image_profile_url' => $student->user?->image_profile_url,
        'first_name' => $student->first_name,
        'last_name' => $student->last_name,
        'mobile_number' => $student->mobile_number,
        'birth_day' => $student->birth_day,
        'parent_name' => $student->parent_name,
        'parent_contact' => $student->parent_contact,
        'grade_level' => [
            'id' => $student->gradeLevel?->id,
            'name' => $locale === 'ar'
                ? $student->gradeLevel?->name_ar
                : $student->gradeLevel?->name_en,
            'stage' => $student->gradeLevel?->stage,
        ],
        'governorate' => [
            'id' => $student->governorate?->id,
            'name' => $locale === 'ar'
                ? $student->governorate?->name_ar
                : $student->governorate?->name_en,
        ],
        'city' => [
            'id' => $student->city?->id,
            'name' => $locale === 'ar'
                ? $student->city?->name_ar
                : $student->city?->name_en,
        ],
        'membership' => [
            'status' => $membership->status,
            'requested_by' => $membership->requested_by,
            'joined_at' => $membership->joined_at,
            'decided_at' => $membership->decided_at,
        ],
    ], __('group.student_details'));
}


public function overview(Request $request, $groupId)
{
    $user = auth()->user();
    if (!$user || !$user->student) return $this->error(null, __('messages.unauthorized'), 401);

    $group = \App\Models\Group::withCount('approvedStudents')->with(['subject', 'gradeLevel', 'teacher.user', 'schedules'])
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
                'teacher_image_profile_url' => $p->teacher?->user?->image_profile_url,
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
            'start_date' => $group->start_date?->toDateString(),
            'end_date' => $group->end_date?->toDateString(),
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
                'image_profile_url' => $group->teacher->user->image_profile_url,
                'rating' => $group->teacher->averageRating(),
                'ratings_count' => $group->teacher->ratingsCount(),
            ],
            'is_can_join' => $group->isCanJoin,
            'is_already_joined' => true,
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
                        'start_date' => $group->start_date?->toDateString(),
                        'end_date' => $group->end_date?->toDateString(),

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
                            'rating' => $group->teacher?->averageRating() ?? 0,
                            'ratings_count' => $group->teacher?->ratingsCount() ?? 0,
                        ],
                        'is_can_join' => $group->isCanJoin,
                        'is_already_joined' => true,
                        'is_favorite' => $this->isFavoriteGroup($group),
                        'joined_at' => $membership->joined_at,
                    ];
                });

            return $this->success(
                $groups,
                __('group.student_groups_loaded')
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
                        'start_date' => $group->start_date?->toDateString(),
                        'end_date' => $group->end_date?->toDateString(),

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
                        'is_can_join' => $group->isCanJoin,

                        'students_count' => $group->approved_students_count,
                    ];
                });

            return $this->success(
                $groups,
                __('group.teacher_groups_loaded')
            );
        }

        return $this->error(
            null,
            __('messages.unauthorized'),
            403
        );
    }


    public function groupAttendance(Request $request, $groupId)
{
    $teacher = auth()->user();

    $group = Group::with([
        'lessons.attendances.student'
    ])
    ->where('teacher_id', $teacher->id)
    ->findOrFail($groupId);

    $lessons = $group->lessons->map(function ($lesson) {

        $presentCount = $lesson->attendances
            ->where('status', 'present')
            ->count();

        $absentCount = $lesson->attendances
            ->where('status', 'absent')
            ->count();

        return [
            'lesson_id' => $lesson->id,
            'lesson_title' => $lesson->title,
            'lesson_date' => $lesson->date,

            'summary' => [
                'present_count' => $presentCount,
                'absent_count' => $absentCount,
            ],

            'attendance' => $lesson->attendances->map(function ($attendance) {

                return [
                    'student_id' => $attendance->student->id,
                    'student_name' => $attendance->student->name,
                    'status' => $attendance->status,
                    'attendance_time' => $attendance->created_at,
                ];
            }),
        ];
    });

    return response()->json([
        'group' => [
            'id' => $group->id,
            'name' => $group->name,
        ],

        'lessons' => $lessons,
    ]);
}
}
