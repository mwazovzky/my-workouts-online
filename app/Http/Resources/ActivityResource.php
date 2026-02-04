<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    public function toArray($request): array
    {
        $exercise = $this->relationLoaded('exercise') ? $this->exercise : null;
        $equipment = $exercise && $exercise->relationLoaded('equipment') ? $exercise->equipment : null;
        $categories = $exercise && $exercise->relationLoaded('categories') ? $exercise->categories : collect();
        $sets = $this->relationLoaded('sets') ? $this->sets : collect();

        return [
            'id' => $this->id,
            'exercise_id' => $this->exercise_id ?? null,
            'exercise_name' => $exercise?->name,
            'rest_time_seconds' => $exercise?->rest_time_seconds,
            'exercise_equipment_name' => $equipment?->name,
            'exercise_category_names' => $categories->pluck('name')->values()->all(),
            'sets' => SetResource::collection($sets)->resolve(),
        ];
    }
}
