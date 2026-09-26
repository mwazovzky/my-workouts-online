<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExerciseResource;
use App\Models\Exercise;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExerciseController extends Controller
{
    /**
     * List the exercise catalog for selection during workout editing.
     *
     * Retired (soft-deleted) exercises are excluded so they cannot be added to
     * new activities, while existing activities still resolve them.
     */
    public function index(): AnonymousResourceCollection
    {
        $exercises = Exercise::query()
            ->with(['categories', 'equipment'])
            ->get()
            ->sortBy(fn (Exercise $exercise) => mb_strtolower((string) $exercise->name))
            ->values();

        return ExerciseResource::collection($exercises);
    }
}
