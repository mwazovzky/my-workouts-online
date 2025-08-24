<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutLogResource extends JsonResource
{
    public function toArray($request): array
    {

        $workoutTemplate = $this->whenLoaded('workoutTemplate');

        return [
            'id' => $this->id,
            'workout_template_name' => $workoutTemplate?->name,
            'workout_template_description' => $workoutTemplate?->description,
            'user_id' => $this->user_id,
            'status' => $this->status,
            'activities' => ActivityResource::collection($this->whenLoaded('activities')),
            'activities_count' => $this->activities_count ?? 0,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
