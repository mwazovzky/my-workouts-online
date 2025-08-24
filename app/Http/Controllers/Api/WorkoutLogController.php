<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkoutLogStoreRequest;
use App\Http\Resources\WorkoutLogResource;
use App\Models\WorkoutLog;
use App\QueryBuilders\WorkoutLogQueryBuilder;
use App\Services\WorkoutLog\WorkoutLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutLogController extends Controller
{
    /**
     * List WorkoutLogs for the authenticated user.
     */
    public function index(Request $request, WorkoutLogQueryBuilder $query)
    {
        $user = $request->user();
        $workoutLogs = $query->for($user)->get();

        return WorkoutLogResource::collection($workoutLogs);
    }

    /**
     * Show a WorkoutLog.
     */
    public function show(WorkoutLog $workoutLog): JsonResource
    {
        $workoutLog->load(['workoutTemplate', 'activities.sets']);
        $workoutLog->loadCount('activities');

        return WorkoutLogResource::make($workoutLog);
    }

    /**
     * Create a WorkoutLog. Copy activities from referenced workout template.
     */
    public function store(WorkoutLogStoreRequest $request, WorkoutLogService $service): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $workoutLog = $service->createFromTemplate($user, $data['workout_template_id']);
        $workoutLog->loadCount('activities');

        return WorkoutLogResource::make($workoutLog)->response()->setStatusCode(201);
    }

    /**
     * Complete a WorkoutLog.
     */
    public function complete(WorkoutLog $workoutLog): JsonResponse
    {
        $workoutLog->status = 'completed';
        $workoutLog->save();

        return response()->json(['message' => 'Workout log completed successfully.']);
    }

    /**
     * Delete a WorkoutLog.
     */
    public function destroy(Request $request, WorkoutLog $workoutLog): JsonResponse
    {
        if ($workoutLog->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $workoutLog->delete();

        return response()->json(null, 204);
    }
}
