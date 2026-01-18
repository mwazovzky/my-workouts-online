<?php

namespace App\QueryBuilders;

use App\Models\Program;
use App\Models\User;
use BadMethodCallException;
use Illuminate\Database\Eloquent\Collection;

class ProgramQueryBuilder
{
    protected $query;

    public function __construct()
    {
        $this->query = Program::query();
    }

    public function get(): Collection
    {
        return $this->query->get();
    }

    /**
     * Scope programs visible to the given user.
     *
     * Usage: $programs = $programBuilder->for($user)->get();
     */
    public function for(User $user): self
    {
        $this->query
            ->whereHas('users', fn ($q) => $q->where('users.id', $user->id))
            ->with('workoutTemplates');

        return $this;
    }

    public function __call($name, $arguments): self
    {
        if (! method_exists($this->query, $name)) {
            throw new BadMethodCallException("Method {$name} does not exist on the query builder.");
        }

        $this->query = $this->query->$name(...$arguments);

        return $this;
    }
}
