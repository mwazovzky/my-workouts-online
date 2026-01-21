<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutTemplateResource extends JsonResource
{
    public function toArray($request): array
    {
        $activities = $this->relationLoaded('activities') ? $this->activities : collect();

        return [
            'id' => $this->id,
            'name' => $this->name ?? null,
            'description' => $this->description ?? null,
            'activities' => ActivityResource::collection($activities)->resolve(),
        ];
    }
}
