<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivityUpdateRequest;
use App\Models\Activity;
use App\Services\Activity\ActivityServiceInterface;
use Illuminate\Http\RedirectResponse;

class ActivityController extends Controller
{
    /**
     * Update an existing Activity.
     */
    public function update(ActivityUpdateRequest $request, Activity $activity, ActivityServiceInterface $service): RedirectResponse
    {
        $this->authorize('update', $activity);

        $data = $request->validated();
        $service->update($activity, $data);

        return back();
    }

    /**
     * Delete an Activity.
     */
    public function destroy(Activity $activity, ActivityServiceInterface $service): RedirectResponse
    {
        $this->authorize('delete', $activity);

        $service->delete($activity);

        return back();
    }
}
