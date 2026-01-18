<?php

namespace App\QueryBuilders;

use App\Models\User;
use App\Models\WorkoutLog;
use Illuminate\Database\Eloquent\Collection;

class WorkoutLogQueryBuilder
{
    protected $query;

    public function __construct()
    {
        $this->query = WorkoutLog::query();
    }

    /**
     * Scope workout logs visible to the given user.
     */
    public function for(User $user): self
    {
        $this->query
            ->where('user_id', $user->id)
            ->with('workoutTemplate')
            ->withCount('activities')
            ->orderByDesc('updated_at');

        return $this;
    }

    /**
     * Execute and return the collection.
     */
    public function get(): Collection
    {
        return $this->query->get();
    }
}
