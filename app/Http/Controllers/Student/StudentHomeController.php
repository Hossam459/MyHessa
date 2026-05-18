<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Models\Group;
use App\Models\Subject;
use App\Http\Traits\HttpResponses;

class StudentHomeController extends Controller
{
    use HttpResponses;

    public function index(): JsonResponse
    {
        $user = auth()->user();

        if (!$user || $user->role !== 'student') {
            return $this->error(
                null,
                __('messages.unauthorized'),
                403
            );
        }

        $student = $user->student;
        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';

        // Subjects
$subjects = Subject::where('grade_level_id', $student->grade_level_id)
    ->get(['id', 'name_ar', 'name_en'])
    ->map(function ($subject) use ($locale) {
        return [
            'id' => $subject->id,
            'name' => $locale === 'ar'
                ? $subject->name_ar
                : $subject->name_en,
        ];
    });

        // Recommended groups (same grade + city + government)
        $recommendedGroups = Group::with([
                'teacher.user',
                'teacher',
                'subject',
                'gradeLevel'
            ])
            ->where('grade_level_id', $student->grade_level_id)
            ->whereHas('teacher', function ($query) use ($student) {
                $query->where('goverment_id', $student->goverment_id)
                      ->where('city_id', $student->city_id);
            })
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($group) use ($locale) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'description' => $group->description,
                    'price' => $group->price,
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
                    'teacher' => [
                        'id' => $group->teacher?->id,
                        'name' => $group->teacher?->user?->user_name,
                        'image' => $group->teacher?->user?->image_profile_url,
                    ],
                    'is_favorite' => $this->isFavoriteGroup($group),
                ];
            });

        // fallback: same government only
        if ($recommendedGroups->isEmpty()) {
            $recommendedGroups = Group::with([
                    'teacher.user',
                    'teacher',
                    'subject',
                    'gradeLevel'
                ])
                ->where('grade_level_id', $student->grade_level_id)
                ->whereHas('teacher', function ($query) use ($student) {
                    $query->where('goverment_id', $student->goverment_id);
                })
                ->latest()
                ->take(10)
                ->get()
                ->map(function ($group) use ($locale) {
                    return [
                        'id' => $group->id,
                        'name' => $group->name,
                        'description' => $group->description,
                        'price' => $group->price,
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
                        'teacher' => [
                            'id' => $group->teacher?->id,
                            'name' => $group->teacher?->user?->user_name,
                            'image' => $group->teacher?->user?->image_profile_url,
                        ],
                        'is_favorite' => $this->isFavoriteGroup($group),
                    ];
                });
        }

        return $this->success([
            'user' => [
                'id' => $user->id,
                'name' => $user->user_name,
                'image' => $user->image_profile_url,
            ],

            'grade_level' => [
                'id' => $student->grade_level_id,
                'name' => $locale === 'ar'
                    ? $student->gradeLevel?->name_ar
                    : $student->gradeLevel?->name_en,
            ],

            'subjects' => $subjects,
            'recommended_groups' => $recommendedGroups,

        ], 'Student home loaded successfully');
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
}