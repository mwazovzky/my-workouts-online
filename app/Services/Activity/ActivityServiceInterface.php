<?php

namespace App\Services\Activity;

use App\Models\Activity;

interface ActivityServiceInterface
{
    /**
     * Update an Activity and its sets.
     *
     * @param  array{sets?: array<int, array{id?: int|null, order: int, repetitions: int, weight: numeric, is_completed?: bool}>}  $data
     */
    public function update(Activity $activity, array $data): Activity;

    /**
     * Delete an Activity and its sets.
     */
    public function delete(Activity $activity): void;
}
