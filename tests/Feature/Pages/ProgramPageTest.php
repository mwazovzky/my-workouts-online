<?php

namespace Tests\Feature\Pages;

use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProgramPageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function program_index_page_is_rendered(): void
    {
        $user = User::factory()->create();

        $program = Program::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('programs.index'));

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('ProgramIndex')
            ->has('programs')
            ->where('programs.0.id', $program->id)
            ->has('programs.0.is_enrolled')
            ->where('programs.0.is_enrolled', false)
        );
    }

    #[Test]
    public function program_show_page_is_rendered_with_id_prop(): void
    {
        $user = User::factory()->create();

        $program = Program::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('programs.show', ['id' => $program->id]));

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('ProgramShow')
            ->has('program')
            ->where('program.id', $program->id)
            ->has('program.is_enrolled')
            ->where('program.is_enrolled', false)
        );
    }

    #[Test]
    public function program_index_shows_enrolled_true_for_enrolled_user(): void
    {
        $user = User::factory()->create();
        $program = Program::factory()->create();

        $program->users()->attach($user);

        $response = $this->actingAs($user)->get(route('programs.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ProgramIndex')
            ->where('programs.0.id', $program->id)
            ->where('programs.0.is_enrolled', true)
        );
    }

    #[Test]
    public function program_show_shows_enrolled_true_for_enrolled_user(): void
    {
        $user = User::factory()->create();
        $program = Program::factory()->create();

        $program->users()->attach($user);

        $response = $this->actingAs($user)->get(route('programs.show', ['id' => $program->id]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ProgramShow')
            ->where('program.id', $program->id)
            ->where('program.is_enrolled', true)
        );
    }

    #[Test]
    public function program_index_does_not_expose_enrolled_users_list(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $program = Program::factory()->create();

        $program->users()->attach($otherUser);

        $response = $this->actingAs($user)->get(route('programs.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ProgramIndex')
            ->where('programs.0.id', $program->id)
            ->missing('programs.0.users')
        );
    }
}
