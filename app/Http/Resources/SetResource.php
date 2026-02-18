<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SetResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'order' => $this->order,
            'effort_value' => $this->effort_value ?? 0,
            'difficulty_value' => $this->difficulty_value,
            'is_completed' => $this->is_completed,
        ];
    }
}
