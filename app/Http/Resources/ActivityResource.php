<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    public function toArray($request): array
    {
        $exercise = $this->relationLoaded('exercise') ? $this->exercise : null;
        $sets = $this->relationLoaded('sets') ? $this->sets : collect();

        return [
            'id' => $this->id,
            'exercise_id' => $this->exercise_id ?? null,
            'exercise_name' => $exercise?->name,
            'rest_time_seconds' => $exercise?->rest_time_seconds,
            'sets' => SetResource::collection($sets)->resolve(),
        ];
    }
}
