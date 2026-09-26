<?php

namespace App\Services\Admin;

use App\Models\Exercise;

interface ExerciseServiceInterface
{
    /**
     * @param  array{equipment_id: int, effort_type: string, rest_time_seconds?: ?int, category_ids?: array<int>, translations: array<string, array<string, ?string>>}  $data
     */
    public function create(array $data): Exercise;

    /**
     * @param  array{equipment_id: int, effort_type: string, rest_time_seconds?: ?int, category_ids?: array<int>, translations: array<string, array<string, ?string>>}  $data
     */
    public function update(Exercise $exercise, array $data): Exercise;

    public function delete(Exercise $exercise): void;
}
