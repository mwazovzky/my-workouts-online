<?php

namespace App\Services\Admin;

use App\Models\Exercise;
use Illuminate\Support\Facades\DB;

class ExerciseService implements ExerciseServiceInterface
{
    public function create(array $data): Exercise
    {
        return DB::transaction(function () use ($data) {
            $exercise = Exercise::createWithTranslations(
                $data['translations'],
                $this->attributes($data),
            );

            $exercise->categories()->sync($data['category_ids'] ?? []);

            return $exercise->load('categories');
        });
    }

    public function update(Exercise $exercise, array $data): Exercise
    {
        return DB::transaction(function () use ($exercise, $data) {
            $exercise->update($this->attributes($data));
            $exercise->updateTranslations($data['translations']);
            $exercise->categories()->sync($data['category_ids'] ?? []);

            return $exercise->load('categories');
        });
    }

    public function delete(Exercise $exercise): void
    {
        $exercise->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'equipment_id' => $data['equipment_id'],
            'effort_type' => $data['effort_type'],
            'rest_time_seconds' => $data['rest_time_seconds'] ?? null,
        ];
    }
}
