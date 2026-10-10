<?php

namespace App\Http\Resources;

use App\Http\WorkoutETag;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutResource extends JsonResource
{
    /**
     * Every single-workout response carries the workout's `ETag`.
     */
    public function withResponse($request, $response): void
    {
        $response->header('ETag', WorkoutETag::for($this->resource));
    }

    public function toArray($request): array
    {
        $workoutTemplate = $this->relationLoaded('workoutTemplate') ? $this->workoutTemplate : null;
        $activities = $this->relationLoaded('activities') ? $this->activities : collect();

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'status' => $this->status->value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'activities_count' => $this->activities_count ?? null,
            'workout_template' => $this->when($workoutTemplate !== null, fn () => (new WorkoutTemplateResource($workoutTemplate))->resolve()),
            'activities' => ActivityResource::collection($activities)->resolve(),
        ];
    }
}
