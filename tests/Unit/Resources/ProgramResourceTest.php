<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\ProgramResource;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProgramResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function resource_transforms_program_to_correct_json_structure(): void
    {
        $program = Program::factory()
            ->withTranslation('name', 'Strength Training')
            ->withTranslation('description', 'Build muscle and strength')
            ->create();

        $resource = new ProgramResource($program);
        $array = $resource->toArray(request());

        $this->assertIsArray($array);
        $this->assertEquals($program->id, $array['id']);
        $this->assertEquals('Strength Training', $array['name']);
        $this->assertEquals('Build muscle and strength', $array['description']);
    }

    #[Test]
    public function is_enrolled_field_is_false_when_users_not_counted(): void
    {
        $program = Program::factory()->create();
        $user = User::factory()->create();

        // Enroll user
        $program->users()->attach($user->id);

        // Load program without withCount
        $programWithoutCount = Program::find($program->id);
        $resource = new ProgramResource($programWithoutCount);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('is_enrolled', $array);
        $this->assertFalse($array['is_enrolled']);
    }

    #[Test]
    public function is_enrolled_field_is_true_when_user_is_enrolled(): void
    {
        $program = Program::factory()->create();
        $user = User::factory()->create();

        // Enroll user
        $program->users()->attach($user->id);

        // Load program with withCount filtered by current user
        $programWithCount = Program::withCount([
            'users' => fn($query) => $query->where('users.id', $user->id),
        ])->find($program->id);

        $resource = new ProgramResource($programWithCount);
        $array = $resource->toArray(request());

        $this->assertTrue($array['is_enrolled']);
    }

    #[Test]
    public function is_enrolled_field_is_false_when_user_is_not_enrolled(): void
    {
        $program = Program::factory()->create();
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        // Enroll only otherUser
        $program->users()->attach($otherUser->id);

        // Load program with withCount filtered by current user (not enrolled)
        $programWithCount = Program::withCount([
            'users' => fn($query) => $query->where('users.id', $user->id),
        ])->find($program->id);

        $resource = new ProgramResource($programWithCount);
        $array = $resource->toArray(request());

        $this->assertFalse($array['is_enrolled']);
    }

    #[Test]
    public function users_relationship_not_exposed_in_response(): void
    {
        $program = Program::factory()->create();
        $user = User::factory()->create();

        $program->users()->attach($user->id);

        // Load program with users relationship
        $programWithUsers = Program::with('users')->find($program->id);
        $resource = new ProgramResource($programWithUsers);
        $array = $resource->toArray(request());

        // Verify users relationship is NOT in the response (privacy)
        $this->assertArrayNotHasKey('users', $array);
    }

    #[Test]
    public function resource_handles_null_description_gracefully(): void
    {
        $program = Program::factory()
            ->withoutTranslation('description')
            ->create();

        $resource = new ProgramResource($program);
        $array = $resource->toArray(request());

        $this->assertNull($array['description']);
    }

    #[Test]
    public function resource_includes_all_expected_keys(): void
    {
        $program = Program::factory()->create();
        $resource = new ProgramResource($program);
        $array = $resource->toArray(request());

        $expectedKeys = ['id', 'name', 'description', 'is_enrolled'];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $array);
        }
    }

    #[Test]
    public function resource_does_not_expose_sensitive_fields(): void
    {
        $program = Program::factory()->create();
        $resource = new ProgramResource($program);
        $array = $resource->toArray(request());

        // Ensure sensitive data is not exposed
        $this->assertArrayNotHasKey('created_at', $array);
        $this->assertArrayNotHasKey('updated_at', $array);
        $this->assertArrayNotHasKey('users', $array);
        $this->assertArrayNotHasKey('workoutTemplates', $array);
    }
}
