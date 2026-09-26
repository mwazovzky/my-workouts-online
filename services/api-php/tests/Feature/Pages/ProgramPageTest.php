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

        $response = $this
            ->actingAs($user)
            ->get(route('programs.index'));

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('ProgramIndex')
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
            ->where('id', $program->id)
        );
    }
}
