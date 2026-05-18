<?php

namespace App\Http\Controllers\GradeLevel;

use App\Http\Controllers\Controller;
use App\Http\Traits\HttpResponses;
use App\Models\GradeLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradeLevelController extends Controller
{
    use HttpResponses;

    /**
     * Get all grade levels
     */
    public function index(Request $request): JsonResponse
    {
        $locale = app()->getLocale();

        $gradeLevels = GradeLevel::query()
            ->select([
                'id',
                'stage',
                'name_ar',
                'name_en',
                'sort_order',
            ])
            ->orderBy('sort_order')
            ->get()
            ->map(function ($grade) use ($locale) {
                return [
                    'id'         => $grade->id,
                    'stage'      => $grade->stage,
                    'name'       => $locale === 'ar' ? $grade->name_ar : $grade->name_en,
                    'sort_order' => $grade->sort_order,
                ];
            });

        return $this->success($gradeLevels, __('messages.success'));
    }

    /**
     * Get grade level by id
     */
    public function show(int $id): JsonResponse
    {
        $locale = app()->getLocale();

        $grade = GradeLevel::find($id);

        if (!$grade) {
            return $this->error(null, __('messages.not_found'), 404);
        }

        return $this->success([
            'id'         => $grade->id,
            'stage'      => $grade->stage,
            'name'       => $locale === 'ar' ? $grade->name_ar : $grade->name_en,
            'sort_order' => $grade->sort_order,
        ], __('messages.success'));
    }
}