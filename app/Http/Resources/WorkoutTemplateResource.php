<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutTemplateResource extends JsonResource
{
    public function toArray($request): array
    {
        $activities = $this->relationLoaded('activities') ? $this->activities : collect();

        $data = [
            'id' => $this->id,
            'name' => $this->name ?? null,
            'description' => $this->description ?? null,
            'activities' => ActivityResource::collection($activities)->resolve(),
        ];

        if ($this->pivot && $this->pivot->weekday) {
            $data['pivot'] = [
                'weekday' => $this->pivot->weekday,
                'weekday_label' => __($this->pivot->weekday),
            ];
        }

        return $data;
    }
}
