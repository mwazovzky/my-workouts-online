<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Category
 */
class CategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name ?? null,
            'exercises_count' => $this->whenCounted('exercises'),
            'translations' => [
                'name' => $this->translationMap('name'),
            ],
        ];
    }
}
