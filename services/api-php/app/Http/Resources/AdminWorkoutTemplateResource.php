<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\WorkoutTemplate
 */
class AdminWorkoutTemplateResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name ?? null,
            'activities_count' => $this->whenCounted('activities'),
            'translations' => [
                'name' => $this->translationMap('name'),
                'description' => $this->translationMap('description'),
            ],
            'activities' => ActivityResource::collection($this->whenLoaded('activities')),
        ];
    }
}
