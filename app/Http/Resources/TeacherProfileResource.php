<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'mobile_number' => $this->mobile_number,
            'birth_day' => $this->birth_day,
            'bio' => $this->bio,
            'subjects' => $this->subjects->map(fn ($subject) => [
                'id' => $subject->id,
                'name' => app()->getLocale() === 'ar'
                    ? $subject->name_ar
                    : $subject->name_en,
            ]),
        ];
    }
}