<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActivityUpdateRequest;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Services\Activity\ActivityService;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityController extends Controller
{
    /**
     * Update an existing Activity.
     */
    public function update(ActivityUpdateRequest $request, Activity $activity, ActivityService $service): JsonResource
    {
        $data = $request->validated();
        $activity = $service->update($activity, $data);

        return ActivityResource::make($activity);
    }
}
