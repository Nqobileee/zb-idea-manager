<?php

namespace Tests\Feature;

use App\Livewire\GlobalSearch;
use App\Models\Challenge;
use App\Models\Idea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Fixtures\SampleDataSeeder;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_bar_search_lists_matching_ideas_challenges_and_people(): void
    {
        $this->seed(SampleDataSeeder::class);
        $exec = User::where('is_admin', true)->firstOrFail();
        $author = User::where('is_admin', false)->firstOrFail();
        Idea::create(['num' => Idea::nextNumber(), 'user_id' => $author->id, 'title' => 'Zebra queue tickets', 'summary' => 's', 'body' => 'b', 'status' => 'Idea', 'visibility' => 'public']);
        Challenge::create(['user_id' => $exec->id, 'title' => 'Zebra crossing payments', 'brief' => 'b', 'keywords' => ['a']]);

        $this->actingAs($author);
        Livewire::test(GlobalSearch::class)
            ->set('q', 'z')->assertDontSee('Zebra queue tickets')
            ->set('q', 'zebra')->assertSee('Zebra queue tickets')->assertSee('Zebra crossing payments')
            ->set('q', $author->name)->assertSee($author->name)
            ->set('q', 'nothing-matches-this')->assertSee('Nothing matches');
    }

    public function test_top_bar_search_is_on_every_page(): void
    {
        $this->seed(SampleDataSeeder::class);
        $this->actingAs(User::where('is_admin', false)->firstOrFail());
        $this->get('/')->assertOk()->assertSeeLivewire(GlobalSearch::class);
    }
}
