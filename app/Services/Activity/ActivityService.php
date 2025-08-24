<?php

namespace App\Services\Activity;

use App\Models\Activity;
use App\Models\Set;
use Illuminate\Support\Facades\DB;

class ActivityService
{
    /**
     * Update an Activity.
     *
     * Delete existing sets and create new ones according to provided payload data.
     *
     * @param  array  $data  Expected keys: 'sets' => [ ['order'=>int,'repetitions'=>int,'weight'=>float], ... ]
     */
    public function update(Activity $activity, array $data): Activity
    {
        $sets = $data['sets'] ?? [];

        DB::transaction(function () use ($activity, $sets) {
            Set::where('activity_id', $activity->id)->delete();

            foreach ($sets as $set) {
                $activity->sets()->create([
                    'order' => $set['order'],
                    'repetitions' => $set['repetitions'],
                    'weight' => $set['weight'],
                ]);
            }
        });

        return $activity->load('sets');
    }
}
