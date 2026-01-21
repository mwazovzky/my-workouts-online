<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutLogResource extends JsonResource
{
    public function toArray($request): array
    {
        $workoutTemplate = $this->relationLoaded('workoutTemplate') ? $this->workoutTemplate : null;
        $activities = $this->relationLoaded('activities') ? $this->activities : collect();

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'status' => $this->status,
            'date' => $this->date ?? null,
            'created_at' => $this->created_at ?? null,
            'activities_count' => $this->activities_count ?? null,
            'workout_template' => $workoutTemplate ? (new WorkoutTemplateResource($workoutTemplate))->resolve() : null,
            'activities' => ActivityResource::collection($activities)->resolve(),
        ];
    }
}
