<?php

namespace Tests\Feature\Migrations;

use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UniqueEnrollmentMigrationTest extends TestCase
{
    use DatabaseMigrations;

    private const MIGRATION = '2026_10_12_000001_add_unique_enrollment_to_program_user_table';

    #[Test]
    public function it_keeps_the_earliest_of_duplicate_enrollments(): void
    {
        $this->migration()->down();

        $user = User::factory()->create();
        $other = User::factory()->create();
        $program = Program::factory()->create();

        $first = $this->enroll($user, $program);
        $this->enroll($user, $program);
        $this->enroll($user, $program);
        $kept = $this->enroll($other, $program);

        $this->migration()->up();

        $this->assertSame([$first, $kept], DB::table('program_user')->orderBy('id')->pluck('id')->all());
    }

    private function enroll(User $user, Program $program): int
    {
        return DB::table('program_user')->insertGetId(['user_id' => $user->id, 'program_id' => $program->id]);
    }

    private function migration(): Migration
    {
        return require database_path('migrations/'.self::MIGRATION.'.php');
    }
}
