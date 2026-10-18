<?php

namespace App\Http\Requests\Concerns;

use App\Models\Exercise;
use Illuminate\Validation\Validator;

/**
 * Checks on an `activities` payload (workout save, admin templates) that
 * Laravel's rules cannot express: a retired exercise may stay where it
 * already is but not be added, and a set order must not repeat within one
 * activity (`distinct` would compare across activities).
 */
trait ChecksActivityPayload
{
    /**
     * @param  callable(array<string, mixed>, int): bool  $keepsRetired  whether the activity may keep this retired exercise
     */
    protected function checkActivities(Validator $validator, callable $keepsRetired): void
    {
        $activities = $this->activitiesPayload();
        $exerciseIds = array_values(array_unique(array_filter(
            array_map(static fn (array $activity): mixed => $activity['exercise_id'] ?? null, $activities),
            'is_int',
        )));

        $retired = $exerciseIds === []
            ? []
            : array_flip(Exercise::onlyTrashed()->whereIn('id', $exerciseIds)->pluck('id')->all());

        foreach ($activities as $i => $activity) {
            $exerciseId = $activity['exercise_id'] ?? null;

            if (is_int($exerciseId) && isset($retired[$exerciseId]) && ! $keepsRetired($activity, $exerciseId)) {
                $validator->addFailure("activities.{$i}.exercise_id", 'Exists', ['exercises', 'id']);
            }

            $sets = $this->setsPayload($activity);
            $orders = array_count_values(array_filter(
                array_map(static fn (array $set): mixed => $set['order'] ?? null, $sets),
                'is_int',
            ));

            foreach ($sets as $j => $set) {
                $order = $set['order'] ?? null;

                if (is_int($order) && $orders[$order] > 1) {
                    $validator->addFailure("activities.{$i}.sets.{$j}.order", 'Distinct');
                }
            }
        }
    }

    /**
     * The activities that are objects, keyed by their index in the payload.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function activitiesPayload(): array
    {
        $activities = $this->input('activities');

        return is_array($activities) ? array_filter($activities, 'is_array') : [];
    }

    /**
     * @param  array<string, mixed>  $activity
     * @return array<int, array<string, mixed>>
     */
    protected function setsPayload(array $activity): array
    {
        $sets = $activity['sets'] ?? null;

        return is_array($sets) ? array_filter($sets, 'is_array') : [];
    }
}
