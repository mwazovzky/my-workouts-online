<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkoutTemplateResource;
use App\Models\WorkoutTemplate;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutTemplateController extends Controller
{
    public function show(WorkoutTemplate $workoutTemplate): JsonResource
    {
        $workoutTemplate->load('activities.sets');
        $workoutTemplate->loadCount('activities');

        return WorkoutTemplateResource::make($workoutTemplate);
    }
}
