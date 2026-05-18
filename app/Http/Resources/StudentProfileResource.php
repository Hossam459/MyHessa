<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'mobile_number' => $this->mobile_number,
            'birth_day' => $this->birth_day,
            'parent_name' => $this->parent_name,
            'parent_contact' => $this->parent_contact,
            'grade_level' => [
                'id' => $this->gradeLevel?->id,
                'name' => app()->getLocale() === 'ar'
                    ? $this->gradeLevel?->name_ar
                    : $this->gradeLevel?->name_en,
                'stage' => $this->gradeLevel?->stage,
            ],
        ];
    }
}