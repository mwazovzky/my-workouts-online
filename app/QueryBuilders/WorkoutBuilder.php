<?php

namespace App\QueryBuilders;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class WorkoutBuilder extends Builder
{
    public function ownedBy(User $user): self
    {
        return $this->whereBelongsTo($user);
    }

    public function withTemplate(): self
    {
        return $this->with('workoutTemplate');
    }

    public function withActivitiesCount(): self
    {
        return $this->withCount('activities');
    }

    public function latestUpdated(): self
    {
        return $this->orderByDesc('updated_at')->orderByDesc('id');
    }
}
