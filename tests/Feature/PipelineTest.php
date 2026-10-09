<?php

namespace Tests\Feature;

use App\Livewire\Pipeline;
use App\Livewire\ProjectShow;
use App\Models\Idea;
use App\Models\IdeaTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Fixtures\SampleDataSeeder;
use Tests\TestCase;

class PipelineTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private User $stranger;

    private User $exec;

    private Idea $idea;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SampleDataSeeder::class);
        $this->exec = User::where('is_admin', true)->firstOrFail();
        $this->author = User::where('is_admin', false)->firstOrFail();
        $this->stranger = User::where('is_admin', false)->where('id', '!=', $this->author->id)->firstOrFail();
        $this->idea = Idea::create(['num' => Idea::nextNumber(), 'user_id' => $this->author->id, 'title' => 'Queue tickets', 'summary' => 's', 'body' => 'b', 'status' => 'Idea', 'visibility' => 'public']);
    }

    public function test_a_newly_posted_challenge_shows_at_the_top_of_the_home_feed(): void
    {
        $this->actingAs($this->exec);
        app(\App\Services\IdeaActions::class)->postChallenge($this->exec, ['title' => 'Cut branch queue times', 'brief' => 'Payday queues are too long', 'keywords' => 'queue', 'deadline' => now()->addDays(20)->toDateString()]);

        $this->actingAs($this->author);
        $html = Livewire::test(\App\Livewire\Feed::class)->assertSee('Open challenges')->assertSee('Cut branch queue times')->html();
        // the challenge comes before the first idea card
        $this->assertLessThan(strpos($html, 'Queue tickets'), strpos($html, 'Cut branch queue times'));
    }

    public function test_expired_challenges_leave_the_top_and_filters_hide_the_strip(): void
    {
        \App\Models\Challenge::create(['user_id' => $this->exec->id, 'title' => 'Old finished challenge', 'brief' => 'b', 'keywords' => ['a'], 'deadline' => now()->subDays(2)]);
        $this->actingAs($this->author);
        Livewire::test(\App\Livewire\Feed::class)->assertDontSee('Old finished challenge');

        \App\Models\Challenge::create(['user_id' => $this->exec->id, 'title' => 'Fresh one', 'brief' => 'b', 'keywords' => ['a']]);
        Livewire::test(\App\Livewire\Feed::class)->assertSee('Fresh one')->set('search', 'queue')->assertDontSee('Open challenges');
    }

    public function test_pipeline_is_in_the_menu_below_members_for_everyone(): void
    {
        foreach ([$this->author, $this->exec] as $u) {
            $html = $this->actingAs($u)->get('/')->assertOk()->getContent();
            $this->assertNotFalse(strpos($html, route('pipeline')));
            $this->assertGreaterThan(strpos($html, route('members')), strpos($html, route('pipeline')));
        }
    }

    public function test_board_has_a_column_for_every_stage_with_the_projects(): void
    {
        $this->actingAs($this->stranger);
        $page = Livewire::test(Pipeline::class);
        foreach (Idea::STATUSES as $s) {
            $page->assertSee($s);
        }
        $page->assertSee('Queue tickets')->assertSee('Drag a card');
    }

    public function test_author_moves_a_project_and_the_move_is_recorded(): void
    {
        $this->actingAs($this->author);
        Livewire::test(Pipeline::class)->call('move', $this->idea->id, 'Prototype');
        $this->assertSame('Prototype', $this->idea->fresh()->status);
        $this->assertDatabaseHas('idea_updates', ['idea_id' => $this->idea->id, 'kind' => 'stage', 'from_stage' => 'Idea', 'to_stage' => 'Prototype', 'user_id' => $this->author->id]);
    }

    public function test_a_stranger_cannot_move_a_project_but_an_executive_can_and_the_author_is_told(): void
    {
        $this->actingAs($this->stranger);
        Livewire::test(Pipeline::class)->call('move', $this->idea->id, 'Demo')->assertForbidden();
        $this->assertSame('Idea', $this->idea->fresh()->status);

        $this->actingAs($this->exec);
        Livewire::test(Pipeline::class)->call('move', $this->idea->id, 'Pilot');
        $this->assertSame('Pilot', $this->idea->fresh()->status);
        $this->assertDatabaseHas('activities', ['user_id' => $this->author->id, 'type' => 'stage', 'idea_id' => $this->idea->id]);
    }

    public function test_unknown_stage_is_rejected(): void
    {
        $this->actingAs($this->author);
        Livewire::test(Pipeline::class)->call('move', $this->idea->id, 'Nonsense')->assertStatus(422);
    }

    public function test_project_page_shows_post_link_updates_pending_and_comments(): void
    {
        $this->actingAs($this->author);
        $page = Livewire::test(ProjectShow::class, ['idea' => $this->idea])
            ->assertSee('View original post')->assertSee('Updates')->assertSee('Pending')->assertSee('Comments')
            ->set('updateText', 'Prototype screens are done')->call('postUpdate')->assertHasNoErrors()->assertSee('Prototype screens are done')
            ->set('tab', 'pending')->set('taskTitle', 'Book the demo room')->set('taskDue', now()->addDays(3)->toDateString())->call('addTask')->assertSee('Book the demo room')
            ->set('tab', 'comments')->set('comment', 'Looks good')->call('addComment')->assertSee('Looks good');

        $task = IdeaTask::firstOrFail();
        $page->set('tab', 'pending')->call('toggleTask', $task->id);
        $this->assertTrue($task->fresh()->done);
        $this->assertTrue(str_contains($this->get(route('projects.show', $this->idea))->getContent(), route('ideas.show', $this->idea)));
    }

    public function test_only_people_on_the_project_can_post_updates_or_tasks(): void
    {
        $this->actingAs($this->stranger);
        Livewire::test(ProjectShow::class, ['idea' => $this->idea])->set('updateText', 'hi')->call('postUpdate')->assertForbidden();
        Livewire::test(ProjectShow::class, ['idea' => $this->idea])->set('taskTitle', 'x')->call('addTask')->assertForbidden();
        $this->assertDatabaseCount('idea_tasks', 0);
    }

    public function test_tagging_a_member_lets_them_help_and_notifies_them(): void
    {
        $this->actingAs($this->author);
        Livewire::test(ProjectShow::class, ['idea' => $this->idea])->call('tagMember', $this->idea->id, $this->stranger->id);
        $this->assertTrue($this->idea->fresh()->members->contains($this->stranger));
        $this->assertDatabaseHas('activities', ['user_id' => $this->stranger->id, 'type' => 'tag', 'idea_id' => $this->idea->id]);

        $this->actingAs($this->stranger);
        Livewire::test(ProjectShow::class, ['idea' => $this->idea])->set('updateText', 'I can help with the SMS part')->call('postUpdate')->assertHasNoErrors();
        Livewire::test(Pipeline::class)->call('move', $this->idea->id, 'Prototype');
        $this->assertSame('Prototype', $this->idea->fresh()->status);

        // tagged people can work on the project but still cannot edit or delete the post
        $this->assertFalse($this->idea->fresh()->canBeManagedBy($this->stranger));

        $this->actingAs($this->author);
        Livewire::test(ProjectShow::class, ['idea' => $this->idea])->call('untagMember', $this->idea->id, $this->stranger->id);
        $this->assertFalse($this->idea->fresh()->members->contains($this->stranger));
    }

    public function test_note_saves_for_contributors_only(): void
    {
        $this->actingAs($this->author);
        Livewire::test(ProjectShow::class, ['idea' => $this->idea])->set("notes.{$this->idea->id}", 'Waiting on vendor quote')->call('saveNote', $this->idea->id);
        $this->assertSame('Waiting on vendor quote', $this->idea->fresh()->note);

        $this->actingAs($this->stranger);
        Livewire::test(ProjectShow::class, ['idea' => $this->idea])->set("notes.{$this->idea->id}", 'hacked')->call('saveNote', $this->idea->id)->assertForbidden();
        $this->assertSame('Waiting on vendor quote', $this->idea->fresh()->note);
    }

    public function test_projects_view_shows_cards_with_pending_timeline_note_and_team(): void
    {
        $this->actingAs($this->author);
        app(\App\Services\IdeaActions::class)->addTask($this->idea->load('members'), $this->author, 'Get legal sign-off');
        $this->idea->update(['note' => 'Focus: pick the SMS vendor']);
        $this->idea->members()->attach($this->stranger->id);
        app(\App\Services\IdeaActions::class)->moveStage($this->idea->load('members'), $this->author, 'Demo');

        Livewire::test(Pipeline::class, [])->set('view', 'projects')
            ->assertSee('Queue tickets')->assertSee('Get legal sign-off')->assertSee('Timeline')->assertSee('moved it from Idea to')
            ->assertSet("notes.{$this->idea->id}", 'Focus: pick the SMS vendor')->assertSee($this->stranger->first_name)->assertSee('Open project');
    }

    public function test_mine_filter_includes_projects_you_are_tagged_on(): void
    {
        $this->idea->members()->attach($this->stranger->id);
        $this->actingAs($this->stranger);
        Livewire::test(Pipeline::class)->set('mine', true)->assertSee('Queue tickets');
        $this->actingAs($this->exec);
        Livewire::test(Pipeline::class)->set('mine', true)->assertDontSee('Queue tickets');
    }

    public function test_deleting_a_post_removes_its_pipeline_data(): void
    {
        $this->actingAs($this->author);
        app(\App\Services\IdeaActions::class)->addTask($this->idea->load('members'), $this->author, 'x');
        app(\App\Services\IdeaActions::class)->postUpdate($this->idea->load('members'), $this->author, 'y');
        $this->idea->members()->attach($this->stranger->id);
        app(\App\Services\IdeaActions::class)->delete($this->idea->fresh(), $this->author);
        $this->assertDatabaseCount('idea_tasks', 0);
        $this->assertDatabaseCount('idea_updates', 0);
        $this->assertDatabaseCount('idea_members', 0);
    }
}
