<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Http\Traits\HttpResponses;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\GroupMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    use HttpResponses;

    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return $this->error(null, __('messages.unauthorized'), 401);
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'in:all,groups,teachers,students,lessons'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'grade_level_id' => ['nullable', 'integer', 'exists:grade_levels,id'],
            'governorate_id' => ['nullable', 'integer', 'exists:governorates,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $type = $filters['type'] ?? 'all';
        $limit = $filters['limit'] ?? 10;

        $data = [
            'filters' => [
                'q' => $filters['q'] ?? null,
                'type' => $type,
                'subject_id' => $filters['subject_id'] ?? null,
                'grade_level_id' => $filters['grade_level_id'] ?? null,
                'governorate_id' => $filters['governorate_id'] ?? null,
                'city_id' => $filters['city_id'] ?? null,
                'limit' => $limit,
            ],
            'groups' => collect(),
            'teachers' => collect(),
            'students' => collect(),
            'lessons' => collect(),
        ];

        if (in_array($type, ['all', 'teachers'], true)) {
            $data['teachers'] = $this->searchTeachers($filters, $limit);
        }

        if (in_array($type, ['all', 'students'], true)) {
            $data['students'] = $this->searchStudents($filters, $limit);
        }

        if ($user->role === 'student' && in_array($type, ['all', 'groups'], true)) {
            $data['groups'] = $this->searchGroups($filters, $limit);
        }

        if ($user->role === 'teacher' && $user->teacher) {
            if (in_array($type, ['all', 'groups'], true)) {
                $data['groups'] = $this->searchTeacherGroups($user->teacher->id, $filters, $limit);
            }

            if (in_array($type, ['all', 'lessons'], true)) {
                $data['lessons'] = $this->searchTeacherLessons($user->teacher->id, $filters, $limit);
            }
        }

        return $this->success($data, __('messages.success'));
    }

    private function searchGroups(array $filters, int $limit)
    {
        $studentId = auth()->user()?->student?->id;

        return $this->groupQuery($filters)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Group $group) => $this->formatGroup($group, $studentId))
            ->values();
    }

    private function searchTeacherGroups(int $teacherId, array $filters, int $limit)
    {
        return $this->groupQuery($filters)
            ->where('teacher_id', $teacherId)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Group $group) => $this->formatGroup($group))
            ->values();
    }

    private function groupQuery(array $filters)
    {
        $query = Group::withCount('approvedStudents')
            ->with(['subject', 'gradeLevel', 'teacher.user', 'teacher.governorate', 'teacher.city', 'latestLesson.attendances', 'memberships']);

        if (!empty($filters['q'])) {
            $keyword = $this->keyword($filters['q']);

            $query->where(function ($query) use ($keyword) {
                $query->where('name', 'like', $keyword)
                    ->orWhere('description', 'like', $keyword)
                    ->orWhereHas('subject', function ($subjectQuery) use ($keyword) {
                        $subjectQuery->where('name_ar', 'like', $keyword)
                            ->orWhere('name_en', 'like', $keyword);
                    })
                    ->orWhereHas('teacher.user', function ($userQuery) use ($keyword) {
                        $userQuery->where('user_name', 'like', $keyword);
                    });
            });
        }

        if (!empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (!empty($filters['grade_level_id'])) {
            $query->where('grade_level_id', $filters['grade_level_id']);
        }

        if (!empty($filters['governorate_id'])) {
            $query->whereHas('teacher', fn ($teacherQuery) => $teacherQuery->where('goverment_id', $filters['governorate_id']));
        }

        if (!empty($filters['city_id'])) {
            $query->whereHas('teacher', fn ($teacherQuery) => $teacherQuery->where('city_id', $filters['city_id']));
        }

        return $query;
    }

    private function searchTeachers(array $filters, int $limit)
    {
        $query = Teacher::with(['user', 'subjects', 'governorate', 'city'])
            ->withCount('ratings')
            ->withAvg('ratings', 'rating');

        if (!empty($filters['q'])) {
            $keyword = $this->keyword($filters['q']);

            $query->where(function ($query) use ($keyword) {
                $query->where('first_name', 'like', $keyword)
                    ->orWhere('last_name', 'like', $keyword)
                    ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", [$keyword])
                    ->orWhereRaw("CONCAT(COALESCE(last_name, ''), ' ', COALESCE(first_name, '')) like ?", [$keyword])
                    ->orWhere('mobile_number', 'like', $keyword)
                    ->orWhere('bio', 'like', $keyword)
                    ->orWhereHas('user', function ($userQuery) use ($keyword) {
                        $userQuery->where('user_name', 'like', $keyword)
                            ->orWhere('email', 'like', $keyword);
                    })
                    ->orWhereHas('subjects', function ($subjectQuery) use ($keyword) {
                        $subjectQuery->where('name_ar', 'like', $keyword)
                            ->orWhere('name_en', 'like', $keyword);
                    });
            });
        }

        if (!empty($filters['subject_id'])) {
            $query->whereHas('subjects', fn ($subjectQuery) => $subjectQuery->where('subjects.id', $filters['subject_id']));
        }

        if (!empty($filters['grade_level_id'])) {
            $query->whereHas('groups', fn ($groupQuery) => $groupQuery->where('grade_level_id', $filters['grade_level_id']));
        }

        if (!empty($filters['governorate_id'])) {
            $query->where('goverment_id', $filters['governorate_id']);
        }

        if (!empty($filters['city_id'])) {
            $query->where('city_id', $filters['city_id']);
        }

        return $query->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Teacher $teacher) => $this->formatTeacher($teacher))
            ->values();
    }

    private function searchStudents(array $filters, int $limit)
    {
        $query = Student::with(['user', 'gradeLevel']);

        if (!empty($filters['q'])) {
            $keyword = $this->keyword($filters['q']);

            $query->where(function ($query) use ($keyword) {
                $query->whereHas('user', function ($userQuery) use ($keyword) {
                    $userQuery->where('user_name', 'like', $keyword)
                        ->orWhere('email', 'like', $keyword);
                })
                ->orWhere('first_name', 'like', $keyword)
                ->orWhere('last_name', 'like', $keyword)
                ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", [$keyword])
                ->orWhereRaw("CONCAT(COALESCE(last_name, ''), ' ', COALESCE(first_name, '')) like ?", [$keyword])
                ->orWhere('mobile_number', 'like', $keyword)
                ->orWhere('parent_name', 'like', $keyword)
                ->orWhere('parent_contact', 'like', $keyword);
            });
        }

        if (!empty($filters['grade_level_id'])) {
            $query->where('grade_level_id', $filters['grade_level_id']);
        }

        return $query->latest()
            ->limit($limit)
            ->get()
            ->map(function (Student $student) {
                return [
                    'id' => $student?->id,
                    'user_id' => $student?->user_id,
                    'name' => $student?->user?->user_name,
                    'email' => $student?->user?->email,
                    'image_profile_url' => $student?->user?->image_profile_url,
                    'first_name' => $student?->first_name,
                    'last_name' => $student?->last_name,
                    'mobile_number' => $student?->mobile_number,
                    'grade_level' => $this->localizedName($student?->gradeLevel),
                ];
            })
            ->values();
    }

    private function searchTeacherLessons(int $teacherId, array $filters, int $limit)
    {
        $query = Lesson::with(['group.subject', 'group.gradeLevel'])
            ->where('teacher_id', $teacherId);

        if (!empty($filters['q'])) {
            $keyword = $this->keyword($filters['q']);

            $query->where(function ($query) use ($keyword) {
                $query->where('title', 'like', $keyword)
                    ->orWhereHas('group', fn ($groupQuery) => $groupQuery->where('name', 'like', $keyword))
                    ->orWhereHas('group.subject', function ($subjectQuery) use ($keyword) {
                        $subjectQuery->where('name_ar', 'like', $keyword)
                            ->orWhere('name_en', 'like', $keyword);
                    });
            });
        }

        if (!empty($filters['subject_id'])) {
            $query->whereHas('group', fn ($groupQuery) => $groupQuery->where('subject_id', $filters['subject_id']));
        }

        if (!empty($filters['grade_level_id'])) {
            $query->whereHas('group', fn ($groupQuery) => $groupQuery->where('grade_level_id', $filters['grade_level_id']));
        }

        return $query->orderByDesc('lesson_date')
            ->orderByDesc('start_time')
            ->limit($limit)
            ->get()
            ->map(fn (Lesson $lesson) => [
                'id' => $lesson->id,
                'title' => $lesson->title ?: $lesson->group?->name,
                'date' => $lesson->lesson_date,
                'start_time' => $lesson->start_time,
                'end_time' => $lesson->end_time,
                'status' => $lesson->status,
                'attendance_status' => $lesson->attendance_status,
                'group' => [
                    'id' => $lesson->group?->id,
                    'name' => $lesson->group?->name,
                    'subject' => $this->localizedName($lesson->group?->subject),
                    'grade_level' => $this->localizedName($lesson->group?->gradeLevel),
                ],
            ])
            ->values();
    }

    private function formatGroup(Group $group, ?int $studentId = null): array
    {
        return [
            'id' => $group->id,
            'name' => $group->name,
            'description' => $group->description,
            'price' => $group->price,
            'max_students' => $group->max_students,
            'students_count' => $group->approved_students_count,
            'subject' => $this->localizedName($group->subject),
            'grade_level' => $this->localizedName($group->gradeLevel),
            'teacher' => [
                'id' => $group->teacher?->id,
                'name' => $group->teacher?->user?->user_name,
                'image' => $group->teacher?->user?->image_profile_url,
                'rating' => $group->teacher?->averageRating() ?? 0,
                'ratings_count' => $group->teacher?->ratingsCount() ?? 0,
            ],
            'membership_status' => ($group->relationLoaded('memberships')
                ? $group->memberships->firstWhere('student_id', $studentId)?->status
                : $group->memberships()->where('student_id', $studentId)->value('status')) ?? null,
            'is_pending' => ($group->relationLoaded('memberships')
                ? ($group->memberships->firstWhere('student_id', $studentId)?->status === GroupMembership::STATUS_PENDING)
                : $group->memberships()->where('student_id', $studentId)->where('status', GroupMembership::STATUS_PENDING)->exists()),
            'is_can_join' => $group->isCanJoin,
            'is_already_joined' => $group->isJoinedByStudent($studentId),
            'is_favorite' => $this->isFavoriteGroup($group),
        ];
    }

    private function groupAttendanceStatus(Group $group, ?int $studentId = null): string
    {
        $lesson = $group->relationLoaded('latestLesson')
            ? $group->latestLesson
            : $group->latestLesson()->with('attendances')->first();

        if (!$lesson) {
            return 'pending';
        }

        if (!$studentId) {
            return $lesson->attendance_status ?? 'pending';
        }

        if ($lesson->attendance_status === 'cancelled') {
            return 'cancelled';
        }

        $attendance = $lesson->relationLoaded('attendances')
            ? $lesson->attendances->firstWhere('student_id', $studentId)
            : $lesson->attendances()->where('student_id', $studentId)->first();

        return $attendance?->status ?? 'unmarked';
    }

    private function formatTeacher(Teacher $teacher): array
    {
        return [
            'id' => $teacher->id,
            'user_id' => $teacher->user_id,
            'name' => $teacher->user?->user_name,
            'image_profile_url' => $teacher->user?->image_profile_url,
            'first_name' => $teacher->first_name,
            'last_name' => $teacher->last_name,
            'bio' => $teacher->bio,
            'rating' => $teacher->averageRating(),
            'ratings_count' => $teacher->ratingsCount(),
            'subjects' => $teacher->subjects
                ->map(fn ($subject) => $this->localizedName($subject))
                ->values(),
            'governorate' => $this->localizedName($teacher->governorate),
            'city' => $this->localizedName($teacher->city),
        ];
    }

    private function localizedName($model): ?array
    {
        if (!$model) {
            return null;
        }

        return [
            'id' => $model->id,
            'name' => app()->getLocale() === 'ar' ? $model->name_ar : $model->name_en,
        ];
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

    private function keyword(string $value): string
    {
        return '%' . addcslashes($value, '%_\\') . '%';
    }
}
