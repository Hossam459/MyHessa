<?php

namespace App\Http\Controllers\Subjects;

use App\Http\Controllers\Controller;
use App\Http\Traits\HttpResponses;
use App\Models\GradeLevel;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubjectsController extends Controller
{
    use HttpResponses;


    public function index(Request $request): JsonResponse
    {
        $locale = app()->getLocale();

      $subjects = Subject::with('gradeLevel')
    ->select('id', 'name_ar', 'name_en', 'grade_level_id')
    ->get()
    ->groupBy('grade_level_id')
    ->map(function ($items, $gradeLevelId) use ($locale) {

        $gradeLevel = optional($items->first()->gradeLevel);

        return [
            'grade_level_id' => (int) $gradeLevelId,
            'grade_level_name' => $gradeLevel
                ? ($locale === 'ar' ? $gradeLevel->name_ar : $gradeLevel->name_en)
                : null,
            'subjects' => $items->map(function ($subject) use ($locale) {
                return [
                    'id'      => $subject->id,
                    'name'    => $locale === 'ar' ? $subject->name_ar : $subject->name_en,
                ];
            })->values(),
        ];
    })
    ->values();

        return $this->success($subjects, __('messages.success'));
    }

   


    public function byGradeLevel(): JsonResponse
    {
        $locale = app()->getLocale();

        $gradeLevels = GradeLevel::select('id', 'stage', 'name_ar', 'name_en')
            ->with(['subjects' => function ($query) {
                $query->select('id', 'grade_level_id', 'name_ar', 'name_en');
            }])
            ->orderBy('sort_order')
            ->get();

        $data = $gradeLevels->map(function ($gradeLevel) use ($locale) {
            return [
                'id' => $gradeLevel->id,
                'stage' => $gradeLevel->stage,
                'name' => $locale === 'ar' ? $gradeLevel->name_ar : $gradeLevel->name_en,
                'subjects' => $gradeLevel->subjects->map(function ($subject) use ($locale) {
                    return [
                        'id' => $subject->id,
                        'grade_level_id' => $subject->grade_level_id,
                        'name' => $locale === 'ar' ? $subject->name_ar : $subject->name_en,
                    ];
                })->values(),
            ];
        });

        return $this->success($data, __('messages.success'));
    }

    public function groupedByStage(): JsonResponse
    {
        $subjects = Subject::select(
                'id',
                'grade_level_id',
                'name_ar',
                'name_en'
            )
            ->with('gradeLevel:id,stage')
            ->get();

        $grouped = [
            [
                'stage_key' => 'primary',
                'stage_name' => Subject::STAGE_PRIMARY,
                'subjects' => [],
            ],
            [
                'stage_key' => 'prep',
                'stage_name' => Subject::STAGE_PREP,
                'subjects' => [],
            ],
            [
                'stage_key' => 'secondary',
                'stage_name' => Subject::STAGE_SECONDARY,
                'subjects' => [],
            ],
        ];

        $locale = app()->getLocale();

        foreach ($grouped as &$stageGroup) {
            $stageSubjects = $subjects
                ->filter(function ($subject) use ($stageGroup) {
                    return $subject->gradeLevel?->stage === $stageGroup['stage_name'];
                })
                ->unique('name_ar')
                ->values()
                ->map(function ($subject) use ($locale) {
                    return [
                        'id' => $subject->id,
                        'name' => $locale === 'ar' ? $subject->name_ar : $subject->name_en,
                    ];
                });

            $stageGroup['subjects'] = $stageSubjects;
        }

        return $this->success($grouped, __('messages.success'));
    }
}