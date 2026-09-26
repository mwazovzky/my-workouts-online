<?php

namespace App\Services\Admin;

use App\Models\WorkoutTemplate;
use Illuminate\Support\Facades\DB;

class WorkoutTemplateService implements WorkoutTemplateServiceInterface
{
    public function create(array $data): WorkoutTemplate
    {
        return DB::transaction(function () use ($data) {
            $template = WorkoutTemplate::createWithTranslations($data['translations']);
            $this->syncActivities($template, $data['activities']);

            return $this->loadForResource($template);
        });
    }

    public function update(WorkoutTemplate $template, array $data): WorkoutTemplate
    {
        return DB::transaction(function () use ($template, $data) {
            $template->updateTranslations($data['translations']);
            $template->activities()->delete();
            $this->syncActivities($template, $data['activities']);

            return $this->loadForResource($template);
        });
    }

    public function delete(WorkoutTemplate $template): void
    {
        DB::transaction(function () use ($template) {
            $template->activities()->delete();
            $template->delete();
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $activities
     */
    private function syncActivities(WorkoutTemplate $template, array $activities): void
    {
        foreach ($activities as $activityData) {
            $activity = $template->activities()->create([
                'exercise_id' => $activityData['exercise_id'],
                'order' => $activityData['order'],
            ]);

            foreach ($activityData['sets'] as $setData) {
                $activity->sets()->create([
                    'order' => $setData['order'],
                    'effort_value' => $setData['effort_value'],
                    'difficulty_value' => $setData['difficulty_value'] ?? null,
                ]);
            }
        }
    }

    private function loadForResource(WorkoutTemplate $template): WorkoutTemplate
    {
        return $template->load([
            'activities' => fn ($query) => $query->orderBy('order'),
            'activities.sets' => fn ($query) => $query->orderBy('order'),
            'activities.exercise.equipment',
            'activities.exercise.categories',
        ]);
    }
}
