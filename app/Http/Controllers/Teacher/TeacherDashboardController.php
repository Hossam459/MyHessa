<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Http\Traits\HttpResponses;
use Illuminate\Http\Request;

class TeacherDashboardController extends Controller
{
    use HttpResponses;

    public function index(): JsonResponse
    {
        $user = auth()->user();

        if (!$user || $user->role !== 'teacher') {
            return $this->error(
                null,
                __('messages.unauthorized'),
                403
            );
        }

        $teacher = $user->teacher;

        $groups = Group::withCount('students')
            ->where('teacher_id', $teacher->id)
            ->latest()
            ->take(10)
            ->get();

        $pendingRequests = GroupMembership::with([
                'student.user',
                'group'
            ])
            ->whereHas('group', function ($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id);
            })
            ->where('status', 'pending')
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($request) {
                return [
                    'id' => $request->id,
                    'group' => [
                        'id' => $request->group->id,
                        'name' => $request->group->name,
                    ],
                    'student' => [
                        'id' => $request->student->id,
                        'name' => $request->student->user->user_name,
                        'image' => $request->student->user->image_profile_url,
                    ],
                    'status' => $request->status,
                    'created_at' => $request->created_at,
                ];
            });

        return $this->success([
            'user' => [
                'id' => $user->id,
                'name' => $user->user_name,
                'image' => $user->image_profile_url,
            ],

            'my_groups' => $groups,

            'pending_join_requests' => $pendingRequests,

            'statistics' => [
                'groups_count' => Group::where('teacher_id', $teacher->id)->count(),

                'students_count' => Group::where(
        'teacher_id',
        $teacher->id
    )
    ->withCount('approvedStudents')
    ->get()
    ->sum('approved_students_count'),

                'pending_requests_count' => GroupMembership::whereHas('group', function ($q) use ($teacher) {
                        $q->where('teacher_id', $teacher->id);
                    })
                    ->where('status', 'pending')
                    ->count(),
            ]

        ], __('teacher.dashboard_loaded'));
    }

    public function students(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user || $user->role !== 'teacher' || !$user->teacher) {
            return $this->error(null, __('messages.unauthorized'), 403);
        }

        $teacher = $user->teacher;

        $memberships = GroupMembership::with([
                'student.user',
                'student.gradeLevel',
                'student.governorate',
                'student.city',
                'group.subject',
                'group.gradeLevel',
            ])
            ->where('status', GroupMembership::STATUS_APPROVED)
            ->whereHas('group', function ($query) use ($teacher) {
                $query->where('teacher_id', $teacher->id);
            })
            ->latest('joined_at')
            ->get();

        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';

        $students = $memberships
            ->groupBy('student_id')
            ->map(function ($studentMemberships) use ($locale) {
                $firstMembership = $studentMemberships->first();
                $student = $firstMembership->student;

                return [
                    'student_id' => $student?->id,
                    'user_id' => $student?->user_id,
                    'name' => $student?->user?->user_name,
                    'email' => $student?->user?->email,
                    'image_profile_url' => $student?->user?->image_profile_url,
                    'first_name' => $student?->first_name,
                    'last_name' => $student?->last_name,
                    'mobile_number' => $student?->mobile_number,
                    'parent_name' => $student?->parent_name,
                    'parent_contact' => $student?->parent_contact,
                    'grade_level' => [
                        'id' => $student?->gradeLevel?->id,
                        'name' => $locale === 'ar'
                            ? $student?->gradeLevel?->name_ar
                            : $student?->gradeLevel?->name_en,
                    ],
                    'governorate' => [
                        'id' => $student?->governorate?->id,
                        'name' => $locale === 'ar'
                            ? $student?->governorate?->name_ar
                            : $student?->governorate?->name_en,
                    ],
                    'city' => [
                        'id' => $student?->city?->id,
                        'name' => $locale === 'ar'
                            ? $student?->city?->name_ar
                            : $student?->city?->name_en,
                    ],
                    'groups' => $studentMemberships->map(function ($membership) use ($locale) {
                        $group = $membership->group;

                        return [
                            'id' => $group?->id,
                            'name' => $group?->name,
                            'subject' => [
                                'id' => $group?->subject?->id,
                                'name' => $locale === 'ar'
                                    ? $group?->subject?->name_ar
                                    : $group?->subject?->name_en,
                            ],
                            'grade_level' => [
                                'id' => $group?->gradeLevel?->id,
                                'name' => $locale === 'ar'
                                    ? $group?->gradeLevel?->name_ar
                                    : $group?->gradeLevel?->name_en,
                            ],
                            'joined_at' => $membership->joined_at,
                        ];
                    })->values(),
                ];
            })
            ->values();

        return $this->success($students, __('teacher.students_loaded'));
    }
}
