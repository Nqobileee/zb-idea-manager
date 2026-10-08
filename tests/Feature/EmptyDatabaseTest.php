<?php

namespace Tests\Feature;

use App\Models\Idea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The real database starts empty. Every page must still work with no data at all. */
class EmptyDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_seeder_adds_nothing(): void
    {
        $this->seed();
        $this->assertSame(0, User::count() + Idea::count());
    }

    public function test_every_page_renders_with_no_data(): void
    {
        $exec = User::create(['name' => 'First User', 'email' => 'first@example.com', 'is_admin' => true]);
        $this->actingAs($exec);
        foreach (['/', '/challenges', '/challenges/create', '/chat', '/activity', '/members', '/profile/edit', '/members/'.$exec->id, '/executive/ranking', '/executive/insights', '/ideas/create'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/')->assertSee('No ideas yet');
    }
}
