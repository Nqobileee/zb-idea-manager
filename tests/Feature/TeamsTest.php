<?php

namespace Tests\Feature;

use App\Livewire\Challenges;
use App\Livewire\Chat;
use App\Livewire\Feed;
use App\Livewire\IdeaCreate;
use App\Livewire\IdeaShow;
use App\Livewire\TeamShow;
use App\Livewire\Teams;
use App\Models\Challenge;
use App\Models\Conversation;
use App\Models\Idea;
use App\Models\Team;
use App\Models\User;
use App\Services\IdeaActions;
use App\Services\Teams as TeamActions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TeamsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Tests\Fixtures\SampleDataSeeder::class);
    }

    private function me(): User
    {
        return User::where('email', 'tinashe.moyo@zb.co.zw')->firstOrFail();
    }

    /** Members who are not executives and not the signed-in employee. */
    private function colleagues(int $n): \Illuminate\Support\Collection
    {
        return User::where('is_admin', false)->where('id', '!=', $this->me()->id)->orderBy('id')->take($n)->get();
    }

    private function executives(int $n): \Illuminate\Support\Collection
    {
        while (User::where('is_admin', true)->count() < $n) {
            User::factory()->create(['is_admin' => true]);
        }

        return User::where('is_admin', true)->orderBy('id')->take($n)->get();
    }

    private function team(string $name = 'Queue busters', ?User $leader = null, array $members = []): Team
    {
        $team = app(TeamActions::class)->create($leader ?? $this->me(), $name, 'Fewer queues, faster payday.');
        foreach ($members as $m) {
            app(TeamActions::class)->add($team, $leader ?? $this->me(), $m);
        }

        return $team->fresh();
    }

    private function challenge(int $executivesNeeded = 1, ?User $by = null, array $extra = []): Challenge
    {
        return Challenge::create(['user_id' => ($by ?? $this->executives(1)->first())->id, 'title' => 'Shorten the payday queue', 'brief' => 'Cut waiting time.', 'keywords' => ['queue'], 'deadline' => now()->addMonth(), 'approvals_required' => $executivesNeeded] + $extra);
    }

    private function answer(User $author, Challenge $challenge, ?Team $team = null, string $visibility = 'private'): Idea
    {
        return Idea::create(['num' => Idea::nextNumber(), 'user_id' => $author->id, 'challenge_id' => $challenge->id, 'team_id' => $team?->id, 'title' => 'Pre-book cash slots', 'summary' => 'Book a slot.', 'body' => 'Book a slot.', 'status' => 'Idea', 'visibility' => $visibility]);
    }

    // ---- teams and their group chat ------------------------------------------------------------

    public function test_creating_a_team_makes_its_group_chat_with_the_leader_in_it(): void
    {
        $me = $this->me();
        $this->actingAs($me);
        Livewire::test(Teams::class)->set('name', 'Queue busters')->set('about', 'Fewer queues')->call('create')->assertHasNoErrors()->assertRedirect();

        $team = Team::where('name', 'Queue busters')->firstOrFail();
        $this->assertSame($me->id, $team->user_id);
        $this->assertTrue($team->hasMember($me));
        $group = $team->groupChat;
        $this->assertNotNull($group);
        $this->assertTrue($group->is_group);
        $this->assertSame('team', $group->scope);
        $this->assertTrue($group->involves($me));
        Livewire::test(Teams::class)->set('name', '')->call('create')->assertHasErrors('name');
    }

    public function test_adding_and_removing_members_keeps_the_group_chat_in_step(): void
    {
        [$a, $b] = $this->colleagues(2)->all();
        $team = $this->team();
        $group = $team->groupChat;

        $this->actingAs($this->me());
        Livewire::test(TeamShow::class, ['teamId' => $team->id])->call('add', $a->id);
        $this->assertTrue($team->fresh()->hasMember($a));
        $this->assertTrue($group->involves($a));
        $this->assertSame(1, $group->unreadFor($a), 'the new member is told through the group');

        // someone who is not the leader cannot add people
        $this->actingAs($b);
        Livewire::test(TeamShow::class, ['teamId' => $team->id])->call('add', $b->id)->assertForbidden();

        // the leader removes a member, who leaves the group too
        $this->actingAs($this->me());
        Livewire::test(TeamShow::class, ['teamId' => $team->id])->call('remove', $a->id);
        $this->assertFalse($team->fresh()->hasMember($a));
        $this->assertFalse($group->fresh()->involves($a));
    }

    public function test_a_member_can_leave_but_the_leader_cannot(): void
    {
        [$a] = $this->colleagues(1)->all();
        $team = $this->team('Leavers', null, [$a]);

        $this->actingAs($a);
        Livewire::test(TeamShow::class, ['teamId' => $team->id])->call('leave')->assertRedirect(route('teams'));
        $this->assertFalse($team->fresh()->hasMember($a));

        $this->actingAs($this->me());
        Livewire::test(TeamShow::class, ['teamId' => $team->id])->call('leave')->assertStatus(422);
        $this->assertTrue($team->fresh()->hasMember($this->me()));
    }

    public function test_only_members_can_open_a_team_chat(): void
    {
        [$a, $outsider] = $this->colleagues(2)->all();
        $team = $this->team('Insiders', null, [$a]);
        $group = $team->groupChat;

        $this->actingAs($a)->get('/chat/'.$group->id)->assertOk();
        $this->actingAs($outsider)->get('/chat/'.$group->id)->assertForbidden();

        // a message reaches members only, and the outsider's unread badge and chat list stay clean
        $this->actingAs($this->me());
        Livewire::test(Chat::class, ['conversation' => $group])->set('body', 'Kick-off at 9')->call('send');
        $this->assertSame(1, $group->unreadFor($a));
        $this->actingAs($outsider);
        Livewire::test(Chat::class)->assertDontSee('Insiders')->assertDontSee('Kick-off at 9');
        $this->actingAs($a);
        Livewire::test(Chat::class)->assertSee('Insiders')->assertSee('Kick-off at 9');
    }

    public function test_the_all_staff_group_is_still_the_only_one_everyone_belongs_to(): void
    {
        $this->team('Insiders');
        $all = Conversation::everyone();
        $this->assertSame('All staff', $all->name);
        $this->assertNull($all->scope);
        $outsider = $this->colleagues(1)->first();
        $this->assertTrue($all->involves($outsider));
        $this->assertSame(1, Conversation::where('is_group', true)->whereNull('scope')->count());
    }

    public function test_deleting_a_team_removes_its_chat_but_keeps_its_ideas(): void
    {
        $team = $this->team();
        $idea = $this->answer($this->me(), $this->challenge(), $team);
        $groupId = $team->groupChat->id;

        $this->actingAs($this->me());
        Livewire::test(TeamShow::class, ['teamId' => $team->id])->call('deleteTeam')->assertRedirect(route('teams'));
        $this->assertNull(Team::find($team->id));
        $this->assertNull(Conversation::find($groupId));
        $this->assertNull($idea->fresh()->team_id);
    }

    // ---- applying with a team ----------------------------------------------------------------------

    public function test_applying_with_a_team_shares_the_idea_with_every_teammate(): void
    {
        [$mate, $outsider] = $this->colleagues(2)->all();
        $team = $this->team('Applicants', null, [$mate]);
        $challenge = $this->challenge();

        $this->actingAs($this->me());
        Livewire::withQueryParams(['challenge' => $challenge->id, 'team' => $team->id])->test(IdeaCreate::class)
            ->assertSet('team', $team->id)
            ->set('title', 'Team answer')->set('summary', 'We can do this')->set('body', 'Details')
            ->call('save')->assertHasNoErrors();

        $idea = Idea::where('title', 'Team answer')->firstOrFail();
        $this->assertSame($team->id, $idea->team_id);
        $this->assertSame('private', $idea->visibility);
        $this->assertDatabaseHas('activities', ['user_id' => $mate->id, 'type' => 'tag', 'idea_id' => $idea->id]);

        $this->actingAs($mate)->get('/ideas/'.$idea->id)->assertOk()->assertSee('Applicants');
        $this->assertTrue($idea->fresh()->canContribute($mate));
        $this->actingAs($outsider)->get('/ideas/'.$idea->id)->assertNotFound();
        Livewire::actingAs($mate)->test(\App\Livewire\Pipeline::class)->set('mine', true)->assertSee('Team answer');
    }

    public function test_an_idea_cannot_be_put_forward_with_a_team_you_are_not_in(): void
    {
        [$other] = $this->colleagues(1)->all();
        $theirs = $this->team('Not mine', $other);

        $this->actingAs($this->me());
        // the link is ignored on the form...
        Livewire::withQueryParams(['team' => $theirs->id])->test(IdeaCreate::class)->assertSet('team', null);
        // ...and refused if someone forces the value
        Livewire::test(IdeaCreate::class)->set('team', $theirs->id)->set('title', 'Sneaky')->set('summary', 's')->call('save')->assertHasErrors('team');
        $this->assertDatabaseMissing('ideas', ['title' => 'Sneaky']);
    }

    public function test_the_team_choice_only_shows_when_you_belong_to_one(): void
    {
        $this->actingAs($this->me());
        Livewire::test(IdeaCreate::class)->assertDontSee('by a team');
        $this->team('Shown');
        Livewire::test(IdeaCreate::class)->assertSee('Shown');
    }

    // ---- group decisions and the project group ---------------------------------------------------------

    public function test_a_challenge_can_ask_for_several_executives_before_an_answer_is_accepted(): void
    {
        [$e1, $e2] = $this->executives(2)->all();
        $mate = $this->colleagues(1)->first();
        $team = $this->team('Deciders', null, [$mate]);
        $idea = $this->answer($this->me(), $this->challenge(2, $e1), $team);
        $told = fn () => \App\Models\Activity::where(['user_id' => $this->me()->id, 'type' => 'approval', 'idea_id' => $idea->id])->count();

        $this->actingAs($e1);
        Livewire::test(IdeaShow::class, ['idea' => $idea->fresh()])->assertSee('Group decision 0 of 2')->call('approve')->assertDispatched('toast');
        $this->assertFalse($idea->fresh()->approved, 'one of two is not enough');
        $this->assertSame(0, Conversation::where('scope', 'project')->count());
        $this->assertSame(0, $told(), 'the author is only told once the idea is accepted');
        Livewire::test(IdeaShow::class, ['idea' => $idea->fresh()])->assertSee('You approved this')->assertDontSee('Add my approval');

        $this->actingAs($e2);
        Livewire::test(IdeaShow::class, ['idea' => $idea->fresh()])->assertSee('Group decision 1 of 2')->call('approve');
        $idea->refresh();
        $this->assertTrue($idea->approved);
        $this->assertSame($e2->id, $idea->approved_by);
        $this->assertSame(1, $told());
    }

    public function test_a_second_vote_from_the_same_executive_does_not_count_twice(): void
    {
        [$e1] = $this->executives(2)->all();
        $idea = $this->answer($this->me(), $this->challenge(2, $e1));
        $result = app(IdeaActions::class)->approve($idea, $e1);
        $again = app(IdeaActions::class)->approve($idea, $e1);
        $this->assertSame(['accepted' => false, 'votes' => 1, 'needed' => 2], $result);
        $this->assertSame(['accepted' => false, 'votes' => 1, 'needed' => 2], $again);
        $this->assertFalse($idea->fresh()->approved);
    }

    public function test_accepting_an_answer_opens_a_group_with_the_executives_the_author_and_the_team(): void
    {
        [$e1, $e2] = $this->executives(2)->all();
        $this->executives(3); // a third executive who did not take part
        $e3 = User::where('is_admin', true)->whereNotIn('id', [$e1->id, $e2->id])->firstOrFail();
        [$mate1, $mate2, $outsider] = $this->colleagues(3)->all();
        $team = $this->team('Winners', null, [$mate1, $mate2]);
        $idea = $this->answer($this->me(), $this->challenge(2, $e1), $team);

        app(IdeaActions::class)->approve($idea, $e1);
        app(IdeaActions::class)->approve($idea, $e2, 'Well done');

        $group = Conversation::where(['scope' => 'project', 'idea_id' => $idea->id])->firstOrFail();
        $this->assertSame($idea->title, $group->name);
        $in = $group->members()->pluck('users.id')->all();
        foreach ([$e1, $e2, $this->me(), $mate1, $mate2] as $u) {
            $this->assertContains($u->id, $in, $u->name.' belongs in the group');
            $this->assertTrue($group->involves($u));
        }
        $this->assertNotContains($e3->id, $in);
        $this->assertFalse($group->involves($e3));
        $this->assertFalse($group->involves($outsider));

        $welcome = $group->messages()->firstOrFail();
        $this->assertSame($e2->id, $welcome->user_id);
        $this->assertStringContainsString($idea->code, $welcome->body);
        $this->assertStringContainsString($e1->name, $welcome->body);
        $this->assertStringContainsString($e2->name, $welcome->body);

        // it shows up in a teammate's chat list and can be opened by them
        $this->actingAs($mate1)->get('/chat/'.$group->id)->assertOk();
        Livewire::test(Chat::class)->assertSee($idea->title);
        $this->actingAs($outsider)->get('/chat/'.$group->id)->assertForbidden();
    }

    public function test_a_single_executive_challenge_is_accepted_at_once_and_still_opens_a_group(): void
    {
        $exec = $this->executives(1)->first();
        $idea = $this->answer($this->me(), $this->challenge(1, $exec));

        $result = app(IdeaActions::class)->approve($idea, $exec);
        $this->assertSame(['accepted' => true, 'votes' => 1, 'needed' => 1], $result);
        $this->assertTrue($idea->fresh()->approved);
        $group = Conversation::where('idea_id', $idea->id)->firstOrFail();
        $this->assertEqualsCanonicalizing([$exec->id, $this->me()->id], $group->members()->pluck('users.id')->all());
    }

    public function test_the_challenge_owner_joins_even_if_another_executive_approves(): void
    {
        [$owner, $other] = $this->executives(2)->all();
        $idea = $this->answer($this->me(), $this->challenge(1, $owner));
        app(IdeaActions::class)->approve($idea, $other);
        $members = Conversation::where('idea_id', $idea->id)->firstOrFail()->members()->pluck('users.id')->all();
        $this->assertContains($owner->id, $members);
        $this->assertContains($other->id, $members);
    }

    public function test_approving_twice_does_not_open_a_second_group(): void
    {
        $exec = $this->executives(1)->first();
        $idea = $this->answer($this->me(), $this->challenge(1, $exec));
        app(IdeaActions::class)->approve($idea, $exec);
        app(IdeaActions::class)->approve($idea->fresh(), $exec);
        $this->assertSame(1, Conversation::where('idea_id', $idea->id)->count());
        $this->assertSame(1, Conversation::where('idea_id', $idea->id)->firstOrFail()->messages()->count());
    }

    public function test_ideas_that_do_not_answer_a_challenge_get_no_group(): void
    {
        $exec = $this->executives(1)->first();
        $idea = Idea::create(['num' => Idea::nextNumber(), 'user_id' => $this->me()->id, 'title' => 'Free idea', 'summary' => 's', 'body' => 'b', 'status' => 'Idea', 'visibility' => 'public']);
        $this->assertSame(['accepted' => true, 'votes' => 1, 'needed' => 1], app(IdeaActions::class)->approve($idea, $exec));
        $this->assertSame(0, Conversation::where('scope', 'project')->count());
    }

    public function test_the_number_of_executives_needed_never_exceeds_the_executives_there_are(): void
    {
        [$e1, $e2] = $this->executives(2)->all();
        $this->assertSame(2, User::where('is_admin', true)->count());
        $idea = $this->answer($this->me(), $this->challenge(5, $e1));
        $this->assertSame(2, $idea->approvalsNeeded(), 'asking for 5 with 2 executives would never be reachable');
        $this->assertFalse(app(IdeaActions::class)->approve($idea, $e1)['accepted']);
        $this->assertTrue(app(IdeaActions::class)->approve($idea, $e2)['accepted']);
    }

    public function test_an_executive_sets_how_many_must_accept_when_posting_a_challenge(): void
    {
        $this->executives(3);
        $this->actingAs(User::where('is_admin', true)->firstOrFail());
        Livewire::test(\App\Livewire\ChallengeCreate::class)
            ->set('title', 'Group call')->set('brief', 'Decided together')->set('approvals', 3)
            ->call('save')->assertHasNoErrors();
        $this->assertSame(3, Challenge::where('title', 'Group call')->firstOrFail()->approvals_required);
        Livewire::test(\App\Livewire\ChallengeCreate::class)->set('title', 'Bad')->set('brief', 'x')->set('approvals', 0)->call('save')->assertHasErrors('approvals');
    }

    // ---- the live database may not have the teams tables yet ---------------------------------------------

    public function test_everything_keeps_working_before_the_teams_migration_has_run(): void
    {
        $me = $this->me();
        $exec = $this->executives(1)->first();
        $idea = Idea::where('user_id', '!=', $me->id)->firstOrFail();
        \Illuminate\Support\Facades\Artisan::call('migrate:rollback', ['--step' => 1, '--force' => true]);
        Team::forgetReady();
        try {
            $this->assertFalse(Team::ready());
            $this->actingAs($me);
            foreach (['/', '/challenges', '/ideas/create', '/ideas/'.$idea->id, '/chat', '/pipeline', '/members'] as $url) {
                $this->get($url)->assertOk();
            }
            $this->get('/teams')->assertOk()->assertSee('not switched on');
            $this->get('/teams/1')->assertNotFound();

            // posting, chatting and approving behave as before
            Livewire::test(IdeaCreate::class)->set('title', 'Before teams')->set('summary', 's')->call('save')->assertHasNoErrors();
            $conv = $me->conversations()->firstOrFail();
            Livewire::test(Chat::class, ['conversation' => $conv])->set('body', 'Still works')->call('send');
            $this->assertSame(1, Conversation::everyone()->id === Conversation::where('is_group', true)->value('id') ? 1 : 0);

            $this->actingAs($exec);
            $unapproved = Idea::where('approved', false)->firstOrFail();
            $this->assertSame(['accepted' => true, 'votes' => 1, 'needed' => 1], app(IdeaActions::class)->approve($unapproved, $exec));
            Livewire::test(\App\Livewire\ChallengeCreate::class)->set('title', 'Old style')->set('brief', 'b')->call('save')->assertHasNoErrors();
            $this->get('/challenges/create')->assertOk();
        } finally {
            Team::forgetReady();
        }
    }

    // ---- search and filter ------------------------------------------------------------------------------

    public function test_the_feed_search_finds_ideas_by_their_author(): void
    {
        $me = $this->me();
        $idea = Idea::with('author')->where('user_id', '!=', $me->id)->firstOrFail();
        $other = Idea::where('user_id', '!=', $idea->user_id)->where('user_id', '!=', $me->id)->firstOrFail();

        $this->actingAs($me);
        Livewire::test(Feed::class)->set('search', $idea->author->name)->assertSee($idea->title)->assertDontSee($other->title);
        Livewire::test(Feed::class)->set('showSearch', true)->set('search', 'zzzz-nothing')->assertSee('No ideas match')->call('closeSearch')->assertSet('search', '')->assertSet('showSearch', false);
    }

    public function test_challenges_can_be_searched_and_filtered_by_open_or_closed(): void
    {
        $exec = $this->executives(1)->first();
        Challenge::create(['user_id' => $exec->id, 'title' => 'Zebra crossing review', 'brief' => 'Old business', 'keywords' => ['z'], 'deadline' => now()->subWeek()]);
        Challenge::create(['user_id' => $exec->id, 'title' => 'Giraffe parking plan', 'brief' => 'Current business', 'keywords' => ['g'], 'deadline' => now()->addWeek()]);

        $this->actingAs($this->me());
        Livewire::test(Challenges::class)->assertSee('Zebra crossing review')->assertSee('Giraffe parking plan')
            ->set('filter', 'closed')->assertSee('Zebra crossing review')->assertDontSee('Giraffe parking plan')
            ->set('filter', 'open')->assertDontSee('Zebra crossing review')->assertSee('Giraffe parking plan')
            ->set('filter', 'all')->set('search', 'giraffe')->assertSee('Giraffe parking plan')->assertDontSee('Zebra crossing review')
            ->set('search', $exec->name)->assertSee('Giraffe parking plan')
            ->set('search', 'no-such-thing')->assertSee('No challenges match')->call('clearFilters')->assertSet('search', '')->assertSet('filter', 'all');
    }

    public function test_teams_can_be_searched_by_name_topic_or_member_and_filtered(): void
    {
        [$a, $b] = $this->colleagues(2)->all();
        $this->team('Alpha squad', $a);
        $mine = $this->team('Bravo crew', null, [$b]);
        $mine->update(['about' => 'Mobile onboarding']);

        $this->actingAs($this->me());
        Livewire::test(Teams::class)->assertSee('Alpha squad')->assertSee('Bravo crew')
            ->set('search', 'alpha')->assertSee('Alpha squad')->assertDontSee('Bravo crew')
            ->set('search', 'onboarding')->assertSee('Bravo crew')->assertDontSee('Alpha squad')
            ->set('search', $b->name)->assertSee('Bravo crew')->assertDontSee('Alpha squad')
            ->call('clearFilters')->set('filter', 'mine')->assertSee('Bravo crew')->assertDontSee('Alpha squad')
            ->set('filter', 'led')->assertSee('Bravo crew')->assertDontSee('Alpha squad')
            ->set('search', 'zzz')->assertSee('No teams match');
    }

    public function test_pages_load_for_a_signed_in_member(): void
    {
        $team = $this->team();
        $this->actingAs($this->me());
        $this->get('/teams')->assertOk()->assertSee('Queue busters');
        $this->get('/teams/'.$team->id)->assertOk()->assertSee('Team chat');
        $this->get('/challenges')->assertOk();
        $this->get('/')->assertOk();
        $this->get('/chat')->assertOk()->assertSee('Queue busters');
    }
}
