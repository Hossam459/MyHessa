<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Http\Traits\HttpResponses;

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

        ], 'Teacher dashboard loaded successfully');
    }
}