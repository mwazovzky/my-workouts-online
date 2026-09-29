<?php

namespace Tests\Feature\Catalog;

use App\Enums\DifficultyUnit;
use App\Enums\EffortType;
use App\Enums\Weekday;
use App\Exceptions\InvalidCatalogException;
use App\Models\Activity;
use App\Models\Category;
use App\Models\Equipment;
use App\Models\Exercise;
use App\Models\Program;
use App\Models\Set;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutTemplate;
use App\Services\Admin\ProgramServiceInterface;
use App\Services\Catalog\CatalogLoader;
use App\Services\Catalog\CatalogSyncReport;
use App\Services\Catalog\CatalogSyncServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class CatalogSyncTest extends TestCase
{
    use RefreshDatabase;

    private function catalog(): array
    {
        return (new CatalogLoader(base_path('tests/Fixtures/catalog')))->load();
    }

    /**
     * Sync the given catalog, or the fixture catalog when none is given.
     */
    private function sync(?array $catalog = null, bool $dryRun = false, bool $strict = false): CatalogSyncReport
    {
        $catalog ??= $this->catalog();

        $this->app->instance(CatalogLoader::class, new class($catalog) extends CatalogLoader
        {
            public function __construct(private readonly array $catalog) {}

            public function load(): array
            {
                return $this->catalog;
            }
        });

        return $this->app->make(CatalogSyncServiceInterface::class)->sync($dryRun, $strict);
    }

    private function removeRecord(array $catalog, string $type, string $slug): array
    {
        $catalog[$type] = array_values(array_filter(
            $catalog[$type],
            fn (array $record) => $record['slug'] !== $slug,
        ));

        return $catalog;
    }

    /**
     * Equipment that no catalog record can match by name.
     */
    private function unmanagedEquipment(): Equipment
    {
        return Equipment::createWithTranslations(
            ['en' => ['name' => 'Garage Rack '.(Equipment::count() + 1)]],
            ['difficulty_unit' => DifficultyUnit::Kilograms],
        );
    }

    private function unmanagedExercise(string $name, ?Equipment $equipment = null): Exercise
    {
        return Exercise::createWithTranslations(
            ['en' => ['name' => $name, 'description' => 'Entered by hand.']],
            [
                'equipment_id' => ($equipment ?? $this->unmanagedEquipment())->id,
                'effort_type' => EffortType::Repetitions,
                'rest_time_seconds' => 120,
            ],
        );
    }

    private function logWorkoutWith(Exercise $exercise): Activity
    {
        $workout = Workout::factory()->create();

        $activity = $workout->activities()->create(['exercise_id' => $exercise->id, 'order' => 1]);
        $activity->sets()->create(['order' => 1, 'effort_value' => 5, 'difficulty_value' => 50]);

        return $activity;
    }

    #[Test]
    public function it_creates_the_catalog_in_an_empty_database(): void
    {
        $report = $this->sync();

        $this->assertTrue($report->committed());
        $this->assertSame(3, $report->count('equipment', CatalogSyncReport::CREATED));
        $this->assertSame(3, $report->count('categories', CatalogSyncReport::CREATED));
        $this->assertSame(4, $report->count('exercises', CatalogSyncReport::CREATED));
        $this->assertSame(2, $report->count('workout_templates', CatalogSyncReport::CREATED));
        $this->assertSame(1, $report->count('programs', CatalogSyncReport::CREATED));
        $this->assertSame([], $report->warnings());

        $this->assertDatabaseCount('equipment', 3);
        $this->assertDatabaseCount('exercises', 4);
    }

    #[Test]
    public function it_stores_exercise_attributes_translations_and_categories(): void
    {
        $this->sync();

        $exercise = Exercise::where('slug', 'bench-press')->firstOrFail();

        $this->assertSame('barbell', $exercise->equipment->slug);
        $this->assertSame(EffortType::Repetitions, $exercise->effort_type);
        $this->assertSame(120, $exercise->rest_time_seconds);
        $this->assertSame(['chest'], $exercise->categories->pluck('slug')->all());
        $this->assertSame('Bench Press', $exercise->rawTranslation('name', 'en'));
        $this->assertSame('Жим лёжа', $exercise->rawTranslation('name', 'ru'));
        $this->assertSame('Жим штанги от груди.', $exercise->rawTranslation('description', 'ru'));
    }

    #[Test]
    public function it_stores_template_activities_and_sets_in_order(): void
    {
        $this->sync();

        $template = WorkoutTemplate::where('slug', 'full-body')->firstOrFail();
        $activities = $template->activities()->orderBy('order')->with('exercise', 'sets')->get();

        $this->assertSame(['bench-press', 'squat', 'push-up'], $activities->pluck('exercise.slug')->all());
        $this->assertSame([1, 2, 3], $activities->pluck('order')->all());

        $squatSets = $activities[1]->sets->sortBy('order')->values();

        $this->assertSame([10, 8], $squatSets->pluck('effort_value')->all());
        $this->assertSame([null, null], $squatSets->pluck('difficulty_value')->all());
        $this->assertCount(3, $activities[0]->sets);
    }

    #[Test]
    public function it_stores_heart_rate_zones_as_the_set_difficulty(): void
    {
        $this->sync();

        $template = WorkoutTemplate::where('slug', 'easy-spin')->firstOrFail();
        $activities = $template->activities()->orderBy('order')->with('sets')->get();

        $this->assertSame([1.0], $activities[0]->sets->pluck('difficulty_value')->all());
        $this->assertSame([2.0, 2.0], $activities[1]->sets->sortBy('order')->pluck('difficulty_value')->all());
        $this->assertSame([600, 600], $activities[1]->sets->sortBy('order')->pluck('effort_value')->all());
    }

    #[Test]
    public function it_stores_the_program_schedule_with_a_repeated_template(): void
    {
        $this->sync();

        $program = Program::where('slug', 'starter')->firstOrFail();
        $schedule = $program->workoutTemplates()->orderBy('program_workout_template.id')->get();

        $this->assertSame(['Monday', 'Thursday', 'Saturday'], $schedule->pluck('pivot.weekday')->all());
        $this->assertSame(['full-body', 'full-body', 'easy-spin'], $schedule->pluck('slug')->all());
    }

    #[Test]
    public function a_second_run_changes_nothing(): void
    {
        $this->sync();

        $activityIds = Activity::orderBy('id')->pluck('id')->all();
        $setIds = Set::orderBy('id')->pluck('id')->all();

        $report = $this->sync();

        $this->assertFalse($report->hasChanges());
        $this->assertSame(13, $report->total(CatalogSyncReport::UNCHANGED));
        $this->assertSame($activityIds, Activity::orderBy('id')->pluck('id')->all());
        $this->assertSame($setIds, Set::orderBy('id')->pluck('id')->all());
        $this->assertDatabaseCount('exercises', 4);
        $this->assertDatabaseCount('program_workout_template', 3);
    }

    #[Test]
    public function it_adopts_an_existing_row_by_its_english_name(): void
    {
        $existing = $this->unmanagedExercise('Bench Press');
        $activity = $this->logWorkoutWith($existing);

        $report = $this->sync();

        $this->assertSame(1, $report->count('exercises', CatalogSyncReport::ADOPTED));
        $this->assertSame(3, $report->count('exercises', CatalogSyncReport::CREATED));
        $this->assertDatabaseCount('exercises', 4);
        $this->assertSame('bench-press', $existing->fresh()->slug);
        $this->assertSame($existing->id, $activity->fresh()->exercise_id);
    }

    #[Test]
    public function it_matches_names_regardless_of_case_and_surrounding_spaces(): void
    {
        $existing = $this->unmanagedExercise(' bench PRESS ');

        $this->sync();

        $this->assertSame('bench-press', $existing->fresh()->slug);
        $this->assertSame('Bench Press', $existing->fresh()->rawTranslation('name', 'en'));
    }

    #[Test]
    public function it_adopts_an_existing_row_by_a_previous_name_and_renames_it(): void
    {
        $existing = $this->unmanagedExercise('Old Bench Press');
        $activity = $this->logWorkoutWith($existing);

        $this->sync();

        $adopted = $existing->fresh();

        $this->assertSame('bench-press', $adopted->slug);
        $this->assertSame('Bench Press', $adopted->rawTranslation('name', 'en'));
        $this->assertSame('Жим лёжа', $adopted->rawTranslation('name', 'ru'));
        $this->assertSame($existing->id, $activity->fresh()->exercise_id);
        $this->assertDatabaseCount('exercises', 4);
    }

    #[Test]
    public function it_adopts_the_oldest_row_and_warns_when_several_match(): void
    {
        $first = $this->unmanagedExercise('Bench Press');
        $second = $this->unmanagedExercise('Bench Press');

        $report = $this->sync();

        $this->assertSame('bench-press', $first->fresh()->slug);
        $this->assertNull($second->fresh()->slug);
        $this->assertCount(1, $report->warnings());
        $this->assertStringContainsString('exercises [bench-press]: 2 rows match by name', $report->warnings()[0]);
    }

    #[Test]
    public function it_prefers_an_active_row_over_a_retired_one(): void
    {
        $retired = $this->unmanagedExercise('Bench Press');
        $retired->delete();
        $active = $this->unmanagedExercise('Bench Press');

        $this->sync();

        $this->assertSame('bench-press', $active->fresh()->slug);
        $this->assertNull(Exercise::withTrashed()->findOrFail($retired->id)->slug);
    }

    #[Test]
    public function it_overwrites_admin_edits_to_managed_records(): void
    {
        $this->sync();

        $exercise = Exercise::where('slug', 'bench-press')->firstOrFail();
        $exercise->update(['rest_time_seconds' => 45]);
        $exercise->updateTranslations(['en' => ['name' => 'My Bench Press']]);
        $exercise->categories()->sync([Category::where('slug', 'legs')->firstOrFail()->id]);
        Equipment::where('slug', 'barbell')->firstOrFail()->update(['difficulty_unit' => DifficultyUnit::Pounds]);

        $report = $this->sync();

        $exercise = $exercise->fresh();

        $this->assertSame(120, $exercise->rest_time_seconds);
        $this->assertSame('Bench Press', $exercise->rawTranslation('name', 'en'));
        $this->assertSame(['chest'], $exercise->categories->pluck('slug')->all());
        $this->assertSame(DifficultyUnit::Kilograms, Equipment::where('slug', 'barbell')->firstOrFail()->difficulty_unit);

        $this->assertSame(1, $report->count('exercises', CatalogSyncReport::UPDATED));
        $this->assertSame(1, $report->count('equipment', CatalogSyncReport::UPDATED));
        $this->assertContains(
            'exercises [bench-press]: updated, changed rest_time_seconds, categories, name (en)',
            $report->changes(),
        );
    }

    #[Test]
    public function it_rewrites_a_template_whose_sets_were_edited(): void
    {
        $this->sync();

        $template = WorkoutTemplate::where('slug', 'easy-spin')->firstOrFail();
        $template->activities()->orderBy('order')->firstOrFail()->sets()->firstOrFail()->update(['effort_value' => 900]);

        $report = $this->sync();

        $this->assertSame(1, $report->count('workout_templates', CatalogSyncReport::UPDATED));
        $this->assertSame(1, $report->count('workout_templates', CatalogSyncReport::UNCHANGED));
        $this->assertSame(
            1800,
            $template->activities()->orderBy('order')->firstOrFail()->sets()->firstOrFail()->effort_value,
        );
    }

    #[Test]
    public function it_restores_the_program_schedule(): void
    {
        $this->sync();

        $program = Program::where('slug', 'starter')->firstOrFail();
        $program->workoutTemplates()->detach();

        $report = $this->sync();

        $this->assertSame(1, $report->count('programs', CatalogSyncReport::UPDATED));
        $this->assertSame(3, $program->workoutTemplates()->count());
    }

    #[Test]
    public function it_leaves_records_created_in_the_admin_ui_untouched(): void
    {
        $category = Category::createWithTranslations(['en' => ['name' => 'Grip']]);
        $exercise = $this->unmanagedExercise('Plate Pinch');
        $updatedAt = $exercise->updated_at;

        $this->travel(1)->hours();
        $this->sync();

        $this->assertNull($category->fresh()->slug);
        $this->assertNull($exercise->fresh()->slug);
        $this->assertSame('Plate Pinch', $exercise->fresh()->rawTranslation('name', 'en'));
        $this->assertTrue($updatedAt->equalTo($exercise->fresh()->updated_at));
        $this->assertDatabaseCount('exercises', 5);
    }

    #[Test]
    public function it_restores_a_managed_exercise_retired_in_the_admin_ui(): void
    {
        $this->sync();

        $exercise = Exercise::where('slug', 'squat')->firstOrFail();
        $exercise->delete();

        $report = $this->sync();

        $this->assertFalse($exercise->fresh()->trashed());
        $this->assertContains('exercises [squat]: updated, changed restored', $report->changes());
        $this->assertDatabaseCount('exercises', 4);
    }

    #[Test]
    public function it_soft_deletes_a_retired_exercise_and_keeps_its_history(): void
    {
        $this->sync();

        $exercise = Exercise::where('slug', 'bench-press')->firstOrFail();
        $activity = $this->logWorkoutWith($exercise);

        $catalog = $this->removeRecord($this->catalog(), 'exercises', 'bench-press');
        $catalog['workout_templates'][1]['activities'] = array_slice($catalog['workout_templates'][1]['activities'], 1);
        $catalog['retired']['exercises'][] = ['slug' => 'bench-press', 'names' => ['Bench Press']];

        $report = $this->sync($catalog);

        $this->assertSame(1, $report->count('exercises', CatalogSyncReport::RETIRED));
        $this->assertSoftDeleted('exercises', ['id' => $exercise->id]);
        $this->assertDatabaseHas('activities', ['id' => $activity->id, 'exercise_id' => $exercise->id]);
        $this->assertSame('Bench Press', $activity->fresh()->exercise->rawTranslation('name', 'en'));

        $this->assertFalse($this->sync($catalog)->hasChanges());
    }

    #[Test]
    public function it_retires_an_unmanaged_exercise_by_name(): void
    {
        $exercise = $this->unmanagedExercise('Behind the Neck Press');

        $catalog = $this->catalog();
        $catalog['retired']['exercises'][] = ['slug' => 'behind-the-neck-press', 'names' => ['Behind the Neck Press']];

        $this->sync($catalog);

        $this->assertSoftDeleted('exercises', ['id' => $exercise->id, 'slug' => 'behind-the-neck-press']);
    }

    #[Test]
    public function it_does_not_delete_a_record_that_is_only_removed_from_the_data_files(): void
    {
        $this->sync();

        $catalog = $this->removeRecord($this->catalog(), 'exercises', 'bench-press');
        $catalog['workout_templates'][1]['activities'] = array_slice($catalog['workout_templates'][1]['activities'], 1);

        $this->sync($catalog);

        $this->assertNotNull(Exercise::where('slug', 'bench-press')->first());
    }

    #[Test]
    public function it_deletes_retired_equipment_that_nothing_uses(): void
    {
        $rack = Equipment::createWithTranslations(
            ['en' => ['name' => 'Old Rack'], 'ru' => ['name' => 'Старая стойка']],
            ['difficulty_unit' => DifficultyUnit::None],
        );

        $report = $this->sync();

        $this->assertSame(1, $report->count('equipment', CatalogSyncReport::RETIRED));
        $this->assertDatabaseMissing('equipment', ['id' => $rack->id]);
        $this->assertDatabaseMissing('translations', ['translatable_type' => 'equipment', 'translatable_id' => $rack->id]);
    }

    #[Test]
    public function it_keeps_retired_equipment_that_a_retired_exercise_still_uses(): void
    {
        $rack = Equipment::createWithTranslations(['en' => ['name' => 'Old Rack']], ['difficulty_unit' => DifficultyUnit::None]);
        $this->unmanagedExercise('Rack Pull', $rack)->delete();

        $report = $this->sync();

        $this->assertSame(0, $report->count('equipment', CatalogSyncReport::RETIRED));
        $this->assertDatabaseHas('equipment', ['id' => $rack->id]);
        $this->assertSame(['equipment [old-rack]: not retired because exercises still use it.'], $report->warnings());
    }

    #[Test]
    public function it_keeps_a_retired_category_that_exercises_belong_to(): void
    {
        $category = Category::createWithTranslations(['en' => ['name' => 'Grip']]);
        $this->unmanagedExercise('Plate Pinch')->categories()->attach($category->id);

        $catalog = $this->catalog();
        $catalog['retired']['categories'][] = ['slug' => 'grip', 'names' => ['Grip']];

        $report = $this->sync($catalog);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertContains('categories [grip]: not retired because exercises still belong to it.', $report->warnings());
    }

    #[Test]
    public function it_retires_a_program_and_then_the_template_it_released(): void
    {
        $this->sync();

        $catalog = $this->removeRecord($this->catalog(), 'programs', 'starter');
        $catalog = $this->removeRecord($catalog, 'workout_templates', 'easy-spin');
        $catalog['programs'][] = [
            'slug' => 'strength-only',
            'translations' => [
                'en' => ['name' => 'Strength Only', 'description' => 'One strength day.'],
                'ru' => ['name' => 'Только сила', 'description' => 'Один силовой день.'],
            ],
            'schedule' => [['weekday' => Weekday::Monday, 'template' => 'full-body']],
            'previous_names' => [],
        ];
        $catalog['retired']['programs'][] = ['slug' => 'starter', 'names' => ['Starter']];
        $catalog['retired']['workout_templates'][] = ['slug' => 'easy-spin', 'names' => ['Easy Spin']];

        $report = $this->sync($catalog);

        $this->assertSame([], $report->warnings());
        $this->assertDatabaseMissing('programs', ['slug' => 'starter']);
        $this->assertDatabaseMissing('workout_templates', ['slug' => 'easy-spin']);
        $this->assertDatabaseHas('workout_templates', ['slug' => 'full-body']);
    }

    #[Test]
    public function it_keeps_a_retired_template_that_a_program_still_uses(): void
    {
        $this->sync();

        $template = WorkoutTemplate::where('slug', 'full-body')->firstOrFail();
        Program::factory()->create()->workoutTemplates()->attach($template->id, ['weekday' => 'Monday']);

        $catalog = $this->removeRecord($this->catalog(), 'workout_templates', 'full-body');
        $catalog['programs'][0]['schedule'] = [['weekday' => Weekday::Saturday, 'template' => 'easy-spin']];
        $catalog['retired']['workout_templates'][] = ['slug' => 'full-body', 'names' => ['Full Body']];

        $report = $this->sync($catalog);

        $this->assertDatabaseHas('workout_templates', ['id' => $template->id]);
        $this->assertContains('workout_templates [full-body]: not retired because a program still uses it.', $report->warnings());
    }

    #[Test]
    public function it_keeps_a_retired_program_that_users_are_enrolled_in(): void
    {
        $this->sync();

        $program = Program::where('slug', 'starter')->firstOrFail();
        $program->users()->attach(User::factory()->create()->id);

        $catalog = $this->removeRecord($this->catalog(), 'programs', 'starter');
        $catalog['programs'][] = [
            'slug' => 'strength-only',
            'translations' => [
                'en' => ['name' => 'Strength Only', 'description' => 'One strength day.'],
                'ru' => ['name' => 'Только сила', 'description' => 'Один силовой день.'],
            ],
            'schedule' => [['weekday' => Weekday::Monday, 'template' => 'full-body']],
            'previous_names' => [],
        ];
        $catalog['retired']['programs'][] = ['slug' => 'starter', 'names' => ['Starter']];

        $report = $this->sync($catalog);

        $this->assertDatabaseHas('programs', ['id' => $program->id]);
        $this->assertSame(3, $program->workoutTemplates()->count());
        $this->assertContains('programs [starter]: not retired because users are enrolled in it.', $report->warnings());
    }

    #[Test]
    public function a_dry_run_reports_changes_without_writing(): void
    {
        $existing = $this->unmanagedExercise('Old Bench Press');

        $report = $this->sync(dryRun: true);

        $this->assertFalse($report->committed());
        $this->assertTrue($report->dryRun);
        $this->assertSame(1, $report->count('exercises', CatalogSyncReport::ADOPTED));
        $this->assertSame(3, $report->count('exercises', CatalogSyncReport::CREATED));

        $this->assertDatabaseCount('exercises', 1);
        $this->assertDatabaseCount('programs', 0);
        $this->assertNull($existing->fresh()->slug);
        $this->assertSame('Old Bench Press', $existing->fresh()->rawTranslation('name', 'en'));
    }

    #[Test]
    public function strict_mode_writes_nothing_when_the_sync_raises_warnings(): void
    {
        $this->unmanagedExercise('Bench Press');
        $this->unmanagedExercise('Bench Press');

        $report = $this->sync(strict: true);

        $this->assertFalse($report->committed());
        $this->assertTrue($report->hasWarnings());
        $this->assertDatabaseCount('exercises', 2);
        $this->assertDatabaseCount('categories', 0);
    }

    #[Test]
    public function strict_mode_writes_when_there_are_no_warnings(): void
    {
        $report = $this->sync(strict: true);

        $this->assertTrue($report->committed());
        $this->assertDatabaseCount('exercises', 4);
    }

    #[Test]
    public function an_invalid_catalog_is_rejected_before_anything_is_written(): void
    {
        $catalog = $this->catalog();
        $catalog['programs'][0]['schedule'][0]['template'] = 'upper-body';

        try {
            $this->sync($catalog);
            $this->fail('The invalid catalog was accepted.');
        } catch (InvalidCatalogException $exception) {
            $this->assertCount(1, $exception->violations);
            $this->assertStringContainsString('workout template "upper-body" does not exist', $exception->violations[0]);
        }

        $this->assertDatabaseCount('equipment', 0);
        $this->assertDatabaseCount('exercises', 0);
    }

    #[Test]
    public function a_failure_part_way_through_rolls_everything_back(): void
    {
        $this->mock(ProgramServiceInterface::class)
            ->shouldReceive('create')
            ->andThrow(new RuntimeException('The database went away.'));

        try {
            $this->sync();
            $this->fail('The failure was swallowed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The database went away.', $exception->getMessage());
        }

        $this->assertDatabaseCount('equipment', 0);
        $this->assertDatabaseCount('exercises', 0);
        $this->assertDatabaseCount('workout_templates', 0);
        $this->assertDatabaseCount('translations', 0);
    }

    #[Test]
    public function it_warns_when_a_logged_exercise_changes_its_effort_type(): void
    {
        $this->sync();
        $this->logWorkoutWith(Exercise::where('slug', 'push-up')->firstOrFail());

        $catalog = $this->catalog();
        $catalog['exercises'][3]['effort_type'] = EffortType::Duration;

        $report = $this->sync($catalog);

        $this->assertSame(
            ['exercises [push-up]: effort type changes from repetitions to duration, which relabels logged workouts.'],
            $report->warnings(),
        );
        $this->assertSame(EffortType::Duration, Exercise::where('slug', 'push-up')->firstOrFail()->effort_type);
    }

    #[Test]
    public function it_warns_when_a_logged_exercise_moves_to_equipment_with_another_unit(): void
    {
        $this->sync();
        $this->logWorkoutWith(Exercise::where('slug', 'squat')->firstOrFail());

        $catalog = $this->catalog();
        $catalog['exercises'][2]['equipment'] = 'bodyweight';
        $catalog['workout_templates'][1]['activities'][1]['sets'] = [['effort' => 10, 'difficulty' => null]];

        $report = $this->sync($catalog);

        $this->assertSame(
            ['exercises [squat]: difficulty unit changes from kilograms to none, which relabels logged workouts.'],
            $report->warnings(),
        );
    }

    #[Test]
    public function it_warns_when_equipment_with_logged_exercises_changes_its_unit(): void
    {
        $this->sync();
        $this->logWorkoutWith(Exercise::where('slug', 'squat')->firstOrFail());

        $catalog = $this->catalog();
        $catalog['equipment'][0]['difficulty_unit'] = DifficultyUnit::Pounds;

        $report = $this->sync($catalog);

        $this->assertSame(
            ['equipment [barbell]: difficulty unit changes from kilograms to pounds, which relabels logged workouts.'],
            $report->warnings(),
        );
    }

    #[Test]
    public function it_does_not_warn_about_measurement_changes_without_logged_workouts(): void
    {
        $this->sync();

        $catalog = $this->catalog();
        $catalog['equipment'][0]['difficulty_unit'] = DifficultyUnit::Pounds;
        $catalog['exercises'][3]['effort_type'] = EffortType::Duration;

        $this->assertSame([], $this->sync($catalog)->warnings());
    }
}
