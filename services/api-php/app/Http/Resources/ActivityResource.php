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
            'exercise_id' => $this->exercise_id,
            'exercise' => $this->when($exercise !== null && $equipment !== null, fn () => [
                'id' => $exercise->id,
                'name' => $exercise->name,
                'equipment_name' => $equipment->name,
                'effort_type' => $exercise->effort_type->value,
                'difficulty_unit' => $equipment->difficulty_unit->value,
                'category_names' => $categories->pluck('name')->values()->all(),
                'rest_time_seconds' => $exercise->rest_time_seconds,
            ]),
            'sets' => SetResource::collection($sets)->resolve(),
        ];
    }
}
