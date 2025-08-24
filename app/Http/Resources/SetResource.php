<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SetResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'order' => $this->order,
            'repetitions' => $this->repetitions,
            'weight' => $this->weight,
        ];
    }
}
