<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\StudentProfileResource;
use App\Http\Resources\TeacherProfileResource;
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->user_name,
            'email' => $this->email,
            'role' => $this->role,
            'image_profile' => $this->image_profile_url,
            'profile' => $this->role === 'student'
                ? new StudentProfileResource($this->student)
                : new TeacherProfileResource($this->teacher),
        ];
    }
}