<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name ?? '',
            'email' => $this->email ?? '',
            'user_type' => $this->user_type ?? '',
            'profile' => $this->when($this->user_type && $this->getProfile(), function () {
                return match($this->user_type) {
                    'admin' => new SchoolAdminResource($this->schoolAdmin),
                    'teacher' => new TeacherResource($this->teacher),
                    'parent' => new ParentResource($this->parent),
                    default => null,
                };
            }),
        ];
    }

    protected function getProfile()
    {
        return match($this->user_type) {
            'admin' => $this->schoolAdmin,
            'teacher' => $this->teacher,
            'parent' => $this->parent,
            default => null,
        };
    }
} 