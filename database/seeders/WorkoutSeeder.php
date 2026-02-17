<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutTemplate;
use Illuminate\Database\Seeder;

class WorkoutSeeder extends Seeder
{
    public function run(): void
    {
        // Get the existing test user
        $user = User::where('email', 'test@example.com')->first();

        if (! $user) {
            $this->command->warn('User test@example.com not found. Please run UserSeeder first.');

            return;
        }

        // Get existing workout templates with translations
        $templates = WorkoutTemplate::all();

        if ($templates->isEmpty()) {
            $this->command->warn('No workout templates found. Please run WorkoutTemplateSeeder first.');

            return;
        }

        for ($i = 0; $i < 30; $i++) {
            $template = $templates->random();
            $status = $i < 25 ? 'completed' : 'in_progress';
            $timestamp = now()->subDays($i);

            $workout = Workout::create([
                'user_id' => $user->id,
                'workout_template_id' => $template->id,
                'name' => $template->name,
                'status' => $status,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            // Copy activities and sets from the template
            foreach ($template->activities()->orderBy('order')->get() as $templateActivity) {
                $activity = $workout->activities()->create([
                    'exercise_id' => $templateActivity->exercise_id,
                    'order' => $templateActivity->order,
                ]);

                foreach ($templateActivity->sets()->orderBy('order')->get() as $templateSet) {
                    $activity->sets()->create([
                        'order' => $templateSet->order,
                        'weight' => $templateSet->weight,
                        'repetitions' => $templateSet->repetitions,
                    ]);
                }
            }
        }
    }
}
