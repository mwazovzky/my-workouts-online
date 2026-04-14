<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name ?? null,
            'email' => $this->email,
            'locale' => $this->locale,
            'theme_preference' => $this->theme_preference,
        ];
    }
}
