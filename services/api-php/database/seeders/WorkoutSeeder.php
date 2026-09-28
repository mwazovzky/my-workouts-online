<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutTemplate;
use Illuminate\Database\Seeder;
use RuntimeException;

class WorkoutSeeder extends Seeder
{
    private const WORKOUT_COUNT = 30;

    private const COMPLETED_COUNT = 25;

    /**
     * Seed demo workouts for the demo user, rotating through the catalog
     * templates in a fixed order. Skipped when the user already has workouts.
     *
     * @throws RuntimeException
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('WorkoutSeeder creates demo workouts and must not run in production.');
        }

        $user = User::where('email', 'test@example.com')->first();

        if (! $user) {
            $this->command?->warn('User test@example.com not found. Please run UserSeeder first.');

            return;
        }

        if (Workout::where('user_id', $user->id)->exists()) {
            return;
        }

        $templates = WorkoutTemplate::query()
            ->whereNotNull('slug')
            ->orderBy('slug')
            ->get();

        if ($templates->isEmpty()) {
            $this->command?->warn('No workout templates found. Please run CatalogSeeder first.');

            return;
        }

        for ($i = 0; $i < self::WORKOUT_COUNT; $i++) {
            $template = $templates[$i % $templates->count()];
            $status = $i < self::COMPLETED_COUNT ? 'completed' : 'in_progress';
            $timestamp = now()->subDays($i);

            $workout = Workout::create([
                'user_id' => $user->id,
                'workout_template_id' => $template->id,
                'name' => $template->name,
                'status' => $status,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            foreach ($template->activities()->orderBy('order')->get() as $templateActivity) {
                $activity = $workout->activities()->create([
                    'exercise_id' => $templateActivity->exercise_id,
                    'order' => $templateActivity->order,
                ]);

                foreach ($templateActivity->sets()->orderBy('order')->get() as $templateSet) {
                    $activity->sets()->create([
                        'order' => $templateSet->order,
                        'effort_value' => $templateSet->effort_value,
                        'difficulty_value' => $templateSet->difficulty_value,
                    ]);
                }
            }
        }
    }
}
