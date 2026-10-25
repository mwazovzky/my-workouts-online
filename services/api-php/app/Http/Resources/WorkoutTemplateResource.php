<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\WorkoutTemplate
 */
class WorkoutTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
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
            $data['assignment'] = [
                'weekday' => $this->pivot->weekday,
            ];
        }

        return $data;
    }
}
