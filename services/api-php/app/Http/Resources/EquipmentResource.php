<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Equipment
 */
class EquipmentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name ?? null,
            'difficulty_unit' => $this->difficulty_unit->value,
            'exercises_count' => $this->whenCounted('exercises'),
            'translations' => [
                'name' => $this->translationMap('name'),
            ],
        ];
    }
}
