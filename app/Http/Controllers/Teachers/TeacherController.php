<?php

namespace App\Http\Controllers\Teachers;

use App\Http\Controllers\Controller;
use App\Http\Traits\HttpResponses;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;

class TeacherController extends Controller
{
    use HttpResponses;

    public function show($teacherId): JsonResponse
    {
        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';

        $teacher = Teacher::with([
                'user',
                'subjects',
                'governorate',
                'city',
                'groups.subject',
                'groups.gradeLevel',
                'ratings.student.user',
            ])
            ->withCount('ratings')
            ->withAvg('ratings', 'rating')
            ->findOrFail($teacherId);

        return $this->success([
            'id' => $teacher->id,
            'user_id' => $teacher->user_id,
            'name' => $teacher->user?->user_name,
            'email' => $teacher->user?->email,
            'image_profile_url' => $teacher->user?->image_profile_url,
            'first_name' => $teacher->first_name,
            'last_name' => $teacher->last_name,
            'mobile_number' => $teacher->mobile_number,
            'birth_day' => $teacher->birth_day,
            'bio' => $teacher->bio,
            'rating' => $teacher->averageRating(),
            'ratings_count' => $teacher->ratingsCount(),
            'reviews' => $teacher->ratings
                ->sortByDesc('id')
                ->map(fn ($review) => [
                    'id' => $review->id,
                    'student_id' => $review->student_id,
                    'student_name' => $review->student?->user?->user_name,
                    'student_image_profile_url' => $review->student?->user?->image_profile_url,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'created_at' => $review->created_at,
                ])
                ->values(),
            'subjects' => $teacher->subjects->map(fn ($subject) => [
                'id' => $subject->id,
                'name' => $locale === 'ar' ? $subject->name_ar : $subject->name_en,
            ])->values(),
            'governorate' => [
                'id' => $teacher->governorate?->id,
                'name' => $locale === 'ar'
                    ? $teacher->governorate?->name_ar
                    : $teacher->governorate?->name_en,
            ],
            'city' => [
                'id' => $teacher->city?->id,
                'name' => $locale === 'ar'
                    ? $teacher->city?->name_ar
                    : $teacher->city?->name_en,
            ],
            'groups' => $teacher->groups->map(fn ($group) => [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'price' => $group->price,
                'max_students' => $group->max_students,
                'students_count' => $group->approvedStudents()->count(),
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
                'is_can_join' => $group->isCanJoin,
                'is_already_joined' => $group->isJoinedByStudent(auth()->user()?->student?->id),
            ])->values(),
        ], __('teacher.details_loaded'));
    }
}
