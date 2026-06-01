<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Models\Group;
use App\Http\Traits\HttpResponses;

class FavoriteGroupController extends Controller
{
    use HttpResponses;

    public function toggle(int $groupId): JsonResponse
    {
        $user = auth()->user();

        if (!$user || !$user->student) {

            return $this->error(
                null,
                __('messages.unauthorized'),
                401
            );
        }

        $student = $user->student;

        $group = Group::find($groupId);

        if (!$group) {

            return $this->error(
                null,
                __('group.not_found'),
                404
            );
        }

        $exists = $student->favoriteGroups()
            ->where('group_id', $groupId)
            ->exists();

        if ($exists) {

            $student->favoriteGroups()
                ->detach($groupId);

            return $this->success([
                'is_favorite' => false
            ], __('favorite.removed'));
        }

        $student->favoriteGroups()
            ->attach($groupId);

        return $this->success([
            'is_favorite' => true
        ], __('favorite.added'));
    }

    public function index(): JsonResponse
    {
        $user = auth()->user();

        if (!$user || !$user->student) {

            return $this->error(
                null,
                __('messages.unauthorized'),
                401
            );
        }

        $student = $user->student;

        $groups = $user->student
            ->favoriteGroups()
            ->with([
                'subject',
                'teacher.user',
                'gradeLevel'
            ])
            ->latest()
            ->get()
            ->map(function ($group) use ($student) {

                return [

                    'id' => $group->id,

                    'name' => $group->name,

                    'description' => $group->description,

                    'price' => $group->price,

                    'is_favorite' => true,

                    'is_can_join' => $group->isCanJoin,

                    'is_already_joined' => $group->isJoinedByStudent($student->id),

                    'subject' => [
                        'id' => $group->subject?->id,

                        'name' => app()->getLocale() === 'ar'
                            ? $group->subject?->name_ar
                            : $group->subject?->name_en,
                    ],

                    'teacher' => [
                        'id' => $group->teacher?->id,

                        'name' => $group->teacher?->user?->user_name,

                        'image' => $group->teacher?->user?->image_profile_url,

                        'rating' => $group->teacher?->averageRating() ?? 0,

                        'ratings_count' => $group->teacher?->ratingsCount() ?? 0,
                    ],
                ];
            });

        return $this->success(
            $groups,
            __('favorite.list')
        );
    }
}
