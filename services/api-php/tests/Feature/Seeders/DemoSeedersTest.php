<?php

namespace Tests\Feature\Seeders;

use App\Models\Program;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutTemplate;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\WorkoutSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class DemoSeedersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Run a seeder directly, without the confirmation `db:seed` asks for in production.
     *
     * @param  class-string<Seeder>  $seeder
     */
    private function runSeeder(string $seeder): void
    {
        $this->app->make($seeder)->setContainer($this->app)->__invoke();
    }

    private function inProduction(): void
    {
        $this->app['env'] = 'production';
    }

    #[Test]
    public function the_database_seeder_seeds_roles_the_catalog_and_demo_data(): void
    {
        $this->seed();

        $this->assertDatabaseHas('roles', ['name' => 'Admin']);
        $this->assertGreaterThan(0, Program::count());

        $user = User::where('email', 'test@example.com')->firstOrFail();

        $this->assertTrue($user->isAdmin());
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertSame(30, Workout::where('user_id', $user->id)->count());
    }

    #[Test]
    public function the_database_seeder_can_run_repeatedly(): void
    {
        $this->seed();

        $programs = Program::count();
        $templates = WorkoutTemplate::count();

        $this->seed();

        $this->assertDatabaseCount('roles', 2);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('workouts', 30);
        $this->assertSame($programs, Program::count());
        $this->assertSame($templates, WorkoutTemplate::count());
    }

    #[Test]
    public function the_database_seeder_creates_no_demo_data_in_production(): void
    {
        $this->inProduction();

        $this->runSeeder(DatabaseSeeder::class);

        $this->assertDatabaseHas('roles', ['name' => 'Admin']);
        $this->assertGreaterThan(0, Program::count());
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('workouts', 0);
    }

    #[Test]
    public function the_user_seeder_refuses_to_run_in_production(): void
    {
        $this->inProduction();

        try {
            $this->runSeeder(UserSeeder::class);
            $this->fail('UserSeeder ran in production.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('must not run in production', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
    }

    #[Test]
    public function the_workout_seeder_refuses_to_run_in_production(): void
    {
        $this->seed();
        Workout::query()->delete();
        $this->inProduction();

        try {
            $this->runSeeder(WorkoutSeeder::class);
            $this->fail('WorkoutSeeder ran in production.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('must not run in production', $exception->getMessage());
        }

        $this->assertDatabaseCount('workouts', 0);
    }

    #[Test]
    public function demo_workouts_rotate_through_the_templates_in_slug_order(): void
    {
        $this->seed();

        $templateIds = WorkoutTemplate::orderBy('slug')->pluck('id')->all();

        $expected = [];
        for ($i = 0; $i < 30; $i++) {
            $expected[] = $templateIds[$i % count($templateIds)];
        }

        $this->assertSame($expected, Workout::orderBy('id')->pluck('workout_template_id')->all());
        $this->assertSame(25, Workout::where('status', 'completed')->count());
        $this->assertSame(5, Workout::where('status', 'in_progress')->count());
    }

    #[Test]
    public function demo_workouts_copy_the_activities_and_sets_of_their_template(): void
    {
        $this->seed();

        $workout = Workout::orderBy('id')->firstOrFail();
        $template = WorkoutTemplate::findOrFail($workout->workout_template_id);

        $this->assertSame(
            $template->activities()->orderBy('order')->pluck('exercise_id')->all(),
            $workout->activities()->orderBy('order')->pluck('exercise_id')->all(),
        );
        $this->assertSame(
            $template->activities()->withCount('sets')->orderBy('order')->pluck('sets_count')->all(),
            $workout->activities()->withCount('sets')->orderBy('order')->pluck('sets_count')->all(),
        );
    }
}
