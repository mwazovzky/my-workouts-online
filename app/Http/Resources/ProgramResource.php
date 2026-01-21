<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProgramResource extends JsonResource
{
    public function toArray($request): array
    {
        $users = $this->relationLoaded('users') ? $this->users : collect();

        return [
            'id' => $this->id,
            'name' => $this->name ?? null,
            'description' => $this->description ?? null,
            'users' => UserResource::collection($users)->resolve(),
        ];
    }
}
