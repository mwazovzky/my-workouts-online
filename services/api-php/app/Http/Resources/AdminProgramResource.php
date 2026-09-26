<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Program
 */
class AdminProgramResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name ?? null,
            'templates_count' => $this->whenCounted('workoutTemplates'),
            'translations' => [
                'name' => $this->translationMap('name'),
                'description' => $this->translationMap('description'),
            ],
            'assignments' => $this->whenLoaded('workoutTemplates', fn () => $this->workoutTemplates
                ->map(fn ($template) => [
                    'workout_template_id' => $template->id,
                    'workout_template_name' => $template->name,
                    'weekday' => $template->pivot->weekday,
                    'weekday_label' => __($template->pivot->weekday),
                ])
                ->values()),
        ];
    }
}
