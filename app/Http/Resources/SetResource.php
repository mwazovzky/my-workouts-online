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
            'repetitions' => $this->repetitions ?? 0,
            'weight' => $this->weight ?? 0,
            'is_completed' => $this->is_completed,
        ];
    }
}
