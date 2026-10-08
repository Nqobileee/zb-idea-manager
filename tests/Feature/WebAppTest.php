<?php

namespace Tests\Feature;

use App\Livewire\Admin\Ranking as RankingPage;
use App\Livewire\Auth\Login;
use App\Livewire\Chat;
use App\Livewire\Feed;
use App\Livewire\IdeaCreate;
use App\Livewire\IdeaShow;
use App\Models\Activity;
use App\Models\Idea;
use App\Models\SentEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class WebAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Tests\Fixtures\SampleDataSeeder::class);
    }

    private function employee(): User
    {
        return User::where('email', 'tinashe.moyo@zb.co.zw')->firstOrFail();
    }

    private function executive(): User
    {
        return User::where('is_admin', true)->firstOrFail();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/ideas/1')->assertRedirect('/login');
    }

    public function test_sign_in_with_emailed_code(): void
    {
        $page = Livewire::test(Login::class)->set('email', 'tinashe.moyo@zb.co.zw')->call('sendCode')->assertSet('step', 'code');
        preg_match('/code is (\d{6})/', SentEmail::where('type', 'Code')->latest('id')->firstOrFail()->body, $m);

        $page->set('code', '000000')->call('verify')->assertSet('error', fn ($e) => $e !== null);
        $this->assertGuest();

        $page->set('code', $m[1])->call('verify')->assertRedirect();
        $this->assertAuthenticatedAs($this->employee());
    }

    public function test_any_valid_email_can_sign_in_but_junk_cannot(): void
    {
        Livewire::test(Login::class)->set('email', 'not-an-email')->call('sendCode')->assertSet('step', 'email');
        $this->assertDatabaseCount('login_codes', 0);
        Livewire::test(Login::class)->set('email', 'someone@gmail.com')->call('sendCode')->assertSet('step', 'code');
        $this->assertDatabaseCount('login_codes', 1);
    }

    public function test_domain_restriction_still_works_when_configured(): void
    {
        config(['ideas.email_domain' => 'zb.co.zw']);
        Livewire::test(Login::class)->set('email', 'someone@gmail.com')->call('sendCode')->assertSet('step', 'email');
        Livewire::test(Login::class)->set('email', 'someone@zb.co.zw')->call('sendCode')->assertSet('step', 'code');
    }

    public function test_feed_shows_seeded_ideas_with_images(): void
    {
        $this->actingAs($this->employee());
        $this->get('/')->assertOk()->assertSee('Pre-booked cash pickup slots')->assertSee('images/ideas/inzila-drone.jpg');
        Livewire::test(Feed::class)->assertSee('Drone roof inspections')->set('search', 'cardless')->assertSee('Cardless ATM')->assertDontSee('Drone roof');
    }

    public function test_like_toggles_and_notifies_author(): void
    {
        $me = $this->employee();
        $idea = Idea::where('user_id', '!=', $me->id)->firstOrFail();
        $this->actingAs($me);
        Livewire::test(Feed::class)->call('like', $idea->id);
        $this->assertTrue($idea->fresh()->likers->contains($me->id));
        $this->assertDatabaseHas('activities', ['user_id' => $idea->user_id, 'type' => 'like', 'actor_id' => $me->id, 'idea_id' => $idea->id]);
        Livewire::test(Feed::class)->call('like', $idea->id);
        $this->assertFalse($idea->fresh()->likers->contains($me->id));
    }

    public function test_comment_notifies_author(): void
    {
        $me = $this->employee();
        $idea = Idea::where('user_id', '!=', $me->id)->firstOrFail();
        $this->actingAs($me);
        Livewire::test(IdeaShow::class, ['idea' => $idea])->set('comment', 'Great idea')->call('addComment');
        $this->assertDatabaseHas('comments', ['idea_id' => $idea->id, 'user_id' => $me->id, 'body' => 'Great idea']);
        $this->assertDatabaseHas('activities', ['user_id' => $idea->user_id, 'type' => 'comment']);
    }

    public function test_post_an_idea_with_files(): void
    {
        Storage::fake('public');
        $this->actingAs($this->employee());
        Livewire::test(IdeaCreate::class)
            ->set('title', 'Test idea')->set('summary', 'A short summary')->set('body', 'Body text')
            ->set('docs', [UploadedFile::fake()->create('plan.pdf', 100, 'application/pdf')])
            ->set('images', [UploadedFile::fake()->image('shot.jpg')])
            ->call('save')->assertHasNoErrors()->assertRedirect();
        $idea = Idea::where('title', 'Test idea')->firstOrFail();
        $this->assertSame(1, $idea->docs()->count());
        $this->assertSame(1, $idea->images()->count());
        $this->assertSame($idea->num, Idea::max('num'));
    }

    public function test_author_can_edit_their_idea_and_others_cannot(): void
    {
        $me = $this->employee();
        $mine = Idea::create(['num' => Idea::nextNumber(), 'user_id' => $me->id, 'title' => 'Old title', 'summary' => 'Old summary', 'body' => 'Old body', 'status' => 'Idea']);

        $this->actingAs($me);
        Livewire::test(IdeaCreate::class, ['idea' => $mine])
            ->assertSet('title', 'Old title')
            ->set('title', 'New title')->set('summary', 'New summary')->set('status', 'Prototype')
            ->call('save')->assertHasNoErrors()->assertRedirect();
        $mine->refresh();
        $this->assertSame('New title', $mine->title);
        $this->assertSame('Prototype', $mine->status);
        $this->assertSame($me->id, $mine->user_id);

        $other = User::where('id', '!=', $me->id)->where('is_admin', false)->first();
        $this->actingAs($other)->get('/ideas/'.$mine->id.'/edit')->assertForbidden();
    }

    public function test_author_can_delete_their_idea_with_its_files_and_comments(): void
    {
        Storage::fake('public');
        $me = $this->employee();
        $idea = Idea::create(['num' => Idea::nextNumber(), 'user_id' => $me->id, 'title' => 'Doomed', 'summary' => 's', 'body' => 'b', 'status' => 'Idea']);
        Storage::disk('public')->put('ideas/docs/x.pdf', 'pdf');
        $idea->files()->create(['kind' => 'doc', 'name' => 'x.pdf', 'path' => 'ideas/docs/x.pdf']);
        $idea->comments()->create(['user_id' => $me->id, 'body' => 'hi']);

        $this->actingAs($me);
        Livewire::test(IdeaShow::class, ['idea' => $idea])->call('deleteIdea')->assertRedirect(route('home'));

        $this->assertDatabaseMissing('ideas', ['id' => $idea->id]);
        $this->assertDatabaseMissing('comments', ['idea_id' => $idea->id]);
        $this->assertDatabaseMissing('idea_files', ['idea_id' => $idea->id]);
        Storage::disk('public')->assertMissing('ideas/docs/x.pdf');
    }

    public function test_only_author_or_executive_can_delete(): void
    {
        $me = $this->employee();
        $idea = Idea::create(['num' => Idea::nextNumber(), 'user_id' => $me->id, 'title' => 'Mine', 'summary' => 's', 'body' => 'b', 'status' => 'Idea']);
        $stranger = User::where('id', '!=', $me->id)->where('is_admin', false)->first();

        $this->actingAs($stranger);
        Livewire::test(IdeaShow::class, ['idea' => $idea])->call('deleteIdea')->assertForbidden();
        $this->assertDatabaseHas('ideas', ['id' => $idea->id]);

        $this->actingAs($this->executive());
        Livewire::test(IdeaShow::class, ['idea' => $idea])->call('deleteIdea')->assertRedirect();
        $this->assertDatabaseMissing('ideas', ['id' => $idea->id]);
    }

    public function test_new_accounts_get_no_made_up_role_department_or_bio(): void
    {
        config(['ideas.require_code' => false]);
        Livewire::test(Login::class)->set('email', 'blank.slate@example.com')->set('role', 'admin')->call('sendCode');
        $u = User::where('email', 'blank.slate@example.com')->firstOrFail();
        $this->assertNull($u->title);
        $this->assertNull($u->dept);
        $this->assertNull($u->bio);
        $this->assertTrue($u->is_admin);
    }

    public function test_executive_manages_their_own_challenges(): void
    {
        $exec = $this->executive();
        $this->actingAs($exec);
        $ch = \App\Models\Challenge::create(['user_id' => $exec->id, 'title' => 'Mine', 'brief' => 'b', 'keywords' => ['a'], 'deadline' => now()->addDays(5)]);
        $other = \App\Models\Challenge::create(['user_id' => User::where('is_admin', false)->first()->id, 'title' => 'Not mine', 'brief' => 'b', 'keywords' => ['a']]);

        Livewire::test(\App\Livewire\Challenges::class)->assertSet('tab', 'mine')->assertSee('Mine')->assertDontSee('Not mine')
            ->set('tab', 'all')->assertSee('Not mine');

        Livewire::test(\App\Livewire\ChallengeCreate::class, ['challenge' => $ch])->assertSet('title', 'Mine')
            ->set('title', 'Renamed')->set('keywords', 'queue, cash')->call('save')->assertHasNoErrors();
        $this->assertSame('Renamed', $ch->fresh()->title);
        $this->assertSame(['queue', 'cash'], $ch->fresh()->keywords);

        $idea = Idea::create(['num' => Idea::nextNumber(), 'user_id' => $exec->id, 'challenge_id' => $ch->id, 'title' => 't', 'summary' => 's', 'body' => 'b', 'status' => 'Idea']);
        Livewire::test(\App\Livewire\ChallengeShow::class, ['challenge' => $ch])->call('deleteChallenge')->assertRedirect();
        $this->assertDatabaseMissing('challenges', ['id' => $ch->id]);
        $this->assertNull($idea->fresh()->challenge_id); // the idea is kept

        $this->actingAs($this->employee())->get('/challenges/'.$other->id.'/edit')->assertForbidden();
    }

    public function test_profile_tabs_are_my_challenges_my_ideas_saved(): void
    {
        $exec = $this->executive();
        $this->actingAs($exec);
        $ch = \App\Models\Challenge::create(['user_id' => $exec->id, 'title' => 'Cut the queues', 'brief' => 'b', 'keywords' => ['queue']]);

        $page = Livewire::test(\App\Livewire\Profile::class, ['user' => $exec])
            ->assertSet('tab', 'challenges')->assertSee('My challenges')->assertSee('My ideas')->assertSee('Saved')->assertSee('Cut the queues');
        $page->set('tab', 'saved')->assertDontSee('Cut the queues');

        // an employee has no challenges tab, and others do not see a Saved tab
        $emp = $this->employee();
        Livewire::test(\App\Livewire\Profile::class, ['user' => $emp])->assertSet('tab', 'ideas')->assertDontSee('My challenges');
        $this->actingAs($emp);
        Livewire::test(\App\Livewire\Profile::class, ['user' => $exec])->assertSee('Challenges')->assertDontSee('Saved')->assertDontSee('My ideas')->assertDontSee('My challenges');
    }

    public function test_executive_pages_are_forbidden_to_employees(): void
    {
        $this->actingAs($this->employee())->get('/executive/ranking')->assertForbidden();
        $this->actingAs($this->executive())->get('/executive/ranking')->assertOk()->assertSee('AI ranking');
    }

    public function test_executive_approval_emails_author_and_adds_activity(): void
    {
        $exec = $this->executive();
        $idea = Idea::where('approved', false)->firstOrFail();
        $this->actingAs($exec);
        Livewire::test(RankingPage::class)->call('approve', $idea->id);
        $this->assertTrue($idea->fresh()->approved);
        $this->assertDatabaseHas('sent_emails', ['type' => 'Approval', 'to' => $idea->author->email]);
        $this->assertDatabaseHas('activities', ['user_id' => $idea->user_id, 'type' => 'approval']);
        Livewire::test(RankingPage::class)->call('digest');
        $this->assertDatabaseHas('sent_emails', ['type' => 'Digest', 'to' => $exec->email]);
    }

    public function test_employee_cannot_approve(): void
    {
        $idea = Idea::where('approved', false)->firstOrFail();
        $this->actingAs($this->employee());
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(\App\Services\IdeaActions::class)->approve($idea, $this->employee());
    }

    public function test_ranking_puts_challenge_ideas_first(): void
    {
        $challenge = \App\Models\Challenge::first();
        $list = app(\App\Services\Ranking::class)->rank($challenge);
        $this->assertTrue($list->first()['idea']->challenge_id === $challenge->id);
        $this->assertSame($list->pluck('total')->sortDesc()->values()->all(), $list->pluck('total')->all());
    }

    public function test_a_valid_email_alone_creates_an_account_when_codes_are_off(): void
    {
        config(['ideas.require_code' => false]);
        Livewire::test(Login::class)->set('email', 'brand.new@example.com')->call('sendCode')->assertRedirect();
        $user = User::where('email', 'brand.new@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Brand New', $user->name);
        $this->assertDatabaseCount('login_codes', 0);

        auth()->logout();
        Livewire::test(Login::class)->set('email', 'not-an-email')->call('sendCode')->assertNoRedirect();
        $this->assertGuest();
    }

    public function test_role_choice_at_sign_in_for_now(): void
    {
        $page = Livewire::test(Login::class)->set('email', 'new.person@example.com')->set('role', 'admin')->call('sendCode');
        preg_match('/code is (\d{6})/', SentEmail::where('type', 'Code')->latest('id')->firstOrFail()->body, $m);
        $page->set('code', $m[1])->call('verify');
        $this->assertTrue(User::where('email', 'new.person@example.com')->firstOrFail()->is_admin);

        auth()->logout();
        config(['ideas.allow_role_choice' => false]);
        $page = Livewire::test(Login::class)->set('email', 'other.person@example.com')->set('role', 'admin')->call('sendCode');
        preg_match('/code is (\d{6})/', SentEmail::where('type', 'Code')->latest('id')->firstOrFail()->body, $m);
        $page->set('code', $m[1])->call('verify');
        $this->assertFalse(User::where('email', 'other.person@example.com')->firstOrFail()->is_admin);
    }

    public function test_chat_send_and_privacy(): void
    {
        $me = $this->employee();
        $conv = $me->conversations()->firstOrFail();
        $this->actingAs($me);
        Livewire::test(Chat::class, ['conversation' => $conv])->set('body', 'See you then')->call('send');
        $this->assertDatabaseHas('messages', ['conversation_id' => $conv->id, 'user_id' => $me->id, 'body' => 'See you then']);

        $stranger = User::where('id', '!=', $me->id)->whereNotIn('id', [$conv->user_a, $conv->user_b])->first();
        $this->actingAs($stranger)->get('/chat/'.$conv->id)->assertForbidden();
    }
}
