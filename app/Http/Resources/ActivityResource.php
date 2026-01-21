<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    public function toArray($request): array
    {
        $sets = $this->relationLoaded('sets') ? $this->sets : collect();

        return [
            'id' => $this->id,
            'exercise_id' => $this->exercise_id ?? null,
            'exercise_name' => $this->exercise_name,
            'sets' => SetResource::collection($sets)->resolve(),
        ];
    }
}
