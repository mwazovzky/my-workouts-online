<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivityUpdateRequest;
use App\Models\Activity;
use App\Services\Activity\ActivityService;
use Illuminate\Http\RedirectResponse;

class ActivityController extends Controller
{
    /**
     * Update an existing Activity.
     */
    public function update(ActivityUpdateRequest $request, Activity $activity, ActivityService $service): RedirectResponse
    {
        $data = $request->validated();
        $service->update($activity, $data);

        return back();
    }
}
