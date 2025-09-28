<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgramResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'workouts' => WorkoutTemplateResource::collection($this->whenLoaded('workoutTemplates')),
            'is_enrolled' => $this->whenLoaded('users', function () use ($user) {
                return $this->users->contains($user);
            }, false),
        ];
    }
}
