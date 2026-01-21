<?php

namespace Tests\Feature\Pages;

use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProgramPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_index_page_is_rendered(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $program = Program::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('programs.index'));

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('ProgramIndex')
            ->has('programs')
            ->where('programs.0.id', $program->id)
            ->has('programs.0.users')
        );
    }

    public function test_program_show_page_is_rendered_with_id_prop(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $program = Program::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('programs.show', ['id' => $program->id]));

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('ProgramShow')
            ->has('program')
            ->where('program.id', $program->id)
            ->has('program.users')
        );
    }
}
