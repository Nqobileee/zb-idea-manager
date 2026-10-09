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
        $mine = Idea::create(['num' => Idea::nextNumber(), 'user_id' => $me->id, 'title' => 'Old title', 'summary' => 'Old summary', 'body' => 'Old body', 'status' => 'Idea', 'visibility' => 'public']);

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
        $idea = Idea::create(['num' => Idea::nextNumber(), 'user_id' => $me->id, 'title' => 'Doomed', 'summary' => 's', 'body' => 'b', 'status' => 'Idea', 'visibility' => 'public']);
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
        $idea = Idea::create(['num' => Idea::nextNumber(), 'user_id' => $me->id, 'title' => 'Mine', 'summary' => 's', 'body' => 'b', 'status' => 'Idea', 'visibility' => 'public']);
        $stranger = User::where('id', '!=', $me->id)->where('is_admin', false)->first();

        $this->actingAs($stranger);
        Livewire::test(IdeaShow::class, ['idea' => $idea])->call('deleteIdea')->assertForbidden();
        $this->assertDatabaseHas('ideas', ['id' => $idea->id]);

        $this->actingAs($this->executive());
        Livewire::test(IdeaShow::class, ['idea' => $idea])->call('deleteIdea')->assertRedirect();
        $this->assertDatabaseMissing('ideas', ['id' => $idea->id]);
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

        $idea = Idea::create(['num' => Idea::nextNumber(), 'user_id' => $exec->id, 'challenge_id' => $ch->id, 'title' => 't', 'summary' => 's', 'body' => 'b', 'status' => 'Idea', 'visibility' => 'public']);
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

    public function test_login_page_has_email_and_password_and_whatsapp_steps_for_new_people(): void
    {
        $this->get('/login')->assertOk()->assertSee('Smile Factory')->assertSee('New here?')->assertSee('+263 77 736 6886')->assertSee('type="password"', false);
    }

    public function test_sign_in_with_the_email_and_password_made_in_the_chatbot(): void
    {
        $u = $this->employee();
        $u->update(['password' => \Illuminate\Support\Facades\Hash::make('chat-pass-1')]);

        Livewire::test(Login::class)->set('email', $u->email)->set('password', 'wrong')->call('signIn')->assertNoRedirect();
        $this->assertGuest();
        Livewire::test(Login::class)->set('email', 'nobody@example.com')->set('password', 'chat-pass-1')->call('signIn')->assertNoRedirect();
        $this->assertGuest();

        Livewire::test(Login::class)->set('email', strtoupper($u->email))->set('password', 'chat-pass-1')->call('signIn')->assertRedirect();
        $this->assertAuthenticatedAs($u);
    }

    public function test_accounts_without_a_password_cannot_sign_in_with_one(): void
    {
        $u = $this->employee();
        $u->update(['password' => null]);
        Livewire::test(Login::class)->set('email', $u->email)->set('password', '')->call('signIn')->assertHasErrors('password');
        $this->assertGuest();
    }

    public function test_repeated_wrong_passwords_are_locked_out(): void
    {
        $u = $this->employee();
        $u->update(['password' => \Illuminate\Support\Facades\Hash::make('right-pass')]);
        foreach (range(1, 5) as $i) {
            Livewire::test(Login::class)->set('email', $u->email)->set('password', 'bad'.$i)->call('signIn');
        }
        Livewire::test(Login::class)->set('email', $u->email)->set('password', 'right-pass')->call('signIn')->assertNoRedirect();
        $this->assertGuest();
    }

    public function test_demo_accounts_are_created_from_the_environment_and_can_sign_in(): void
    {
        config(['ideas.demo' => [
            'admin' => ['email' => 'demo.exec@example.test', 'password' => 'demo-exec-pass'],
            'member' => ['email' => 'demo.mem@example.test', 'password' => 'demo-mem-pass'],
        ]]);
        $this->artisan('ideas:seed-demo')->assertSuccessful();
        $this->artisan('ideas:seed-demo')->assertSuccessful(); // safe to repeat
        $this->assertSame(1, User::where('email', 'demo.exec@example.test')->count());
        $this->assertTrue(User::where('email', 'demo.exec@example.test')->first()->is_admin);
        $this->assertFalse(User::where('email', 'demo.mem@example.test')->first()->is_admin);

        Livewire::test(Login::class)->set('email', 'demo.mem@example.test')->set('password', 'demo-mem-pass')->call('signIn')->assertRedirect();
        $this->assertAuthenticatedAs(User::where('email', 'demo.mem@example.test')->first());

        config(['ideas.demo' => ['admin' => ['email' => '', 'password' => ''], 'member' => ['email' => '', 'password' => '']]]);
        $this->artisan('ideas:seed-demo')->assertFailed();
    }

    public function test_whatsapp_link_signs_in_once_and_expires(): void
    {
        $accounts = app(\App\Services\PhoneAccounts::class);
        $link = $accounts->loginLink($this->employee());
        $this->get($link)->assertRedirect();
        $this->assertAuthenticatedAs($this->employee());

        auth()->logout();
        $this->get($link)->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get('/wa-login/not-a-real-token')->assertRedirect(route('login'));
    }

    public function test_accounts_from_a_whatsapp_number_start_blank_and_are_not_admins(): void
    {
        $u = app(\App\Services\PhoneAccounts::class)->forPhone('263770000001', 'Edith Muyambiri');
        $this->assertSame('Edith Muyambiri', $u->name);
        $this->assertNull($u->title);
        $this->assertNull($u->dept);
        $this->assertNull($u->bio);
        $this->assertFalse($u->is_admin);
        $this->assertSame('General', $u->role_label);
        $this->assertTrue(app(\App\Services\PhoneAccounts::class)->forPhone('263770000001')->is($u));
    }

    public function test_ideas_are_private_by_default_and_public_ones_are_visible_to_everyone(): void
    {
        $author = User::factory()->create(['is_admin' => false]);
        $other = User::factory()->create(['is_admin' => false]);
        $this->actingAs($author);
        Livewire::test(IdeaCreate::class)->set('title', 'Secret plan')->set('summary', 'Only for me')->call('save');
        $private = Idea::where('title', 'Secret plan')->firstOrFail();
        $this->assertSame('private', $private->visibility);

        Livewire::test(IdeaCreate::class)->set('title', 'Open plan')->set('summary', 'For all')->set('visibility', 'public')->call('save');
        $public = Idea::where('title', 'Open plan')->firstOrFail();
        $this->assertSame('public', $public->visibility);

        $this->get(route('ideas.show', $private))->assertOk();

        $this->actingAs($other);
        $this->get(route('ideas.show', $private))->assertNotFound();
        $this->get(route('projects.show', $private))->assertNotFound();
        $this->get(route('ideas.show', $public))->assertOk();
        Livewire::test(\App\Livewire\Pipeline::class)->assertDontSee('Secret plan')->assertSee('Open plan');
        Livewire::test(Feed::class)->assertDontSee('Secret plan');

        $this->actingAs($this->executive());
        $this->get(route('ideas.show', $private))->assertOk();
        Livewire::test(\App\Livewire\Pipeline::class)->assertSee('Secret plan');
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

    public function test_old_accounts_without_a_password_get_the_generic_one_and_must_change_it(): void
    {
        config(['ideas.default_password' => 'Pass123']);
        $old = User::factory()->create(['email' => 'old.account@example.com', 'password' => null, 'is_admin' => false]);
        $has = User::factory()->create(['email' => 'has.pass@example.com', 'password' => \Illuminate\Support\Facades\Hash::make('keep-me-1234')]);
        $wa = User::factory()->create(['email' => '263770000099@whatsapp.invalid', 'password' => null]);

        $this->artisan('ideas:set-default-passwords')->assertSuccessful();
        $this->assertTrue($old->fresh()->must_change_password);
        $this->assertFalse($has->fresh()->must_change_password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('keep-me-1234', $has->fresh()->password));
        $this->assertNull($wa->fresh()->password);

        Livewire::test(Login::class)->set('email', 'old.account@example.com')->set('password', 'Pass123')->call('signIn')->assertRedirect(route('password.change'));
        $this->assertAuthenticatedAs($old);

        $this->get('/')->assertRedirect(route('password.change'));
        $this->get('/pipeline')->assertRedirect(route('password.change'));

        Livewire::test(\App\Livewire\Auth\ChangePassword::class)->set('password', 'Pass123')->set('password_confirmation', 'Pass123')->call('save')->assertHasErrors('password');
        Livewire::test(\App\Livewire\Auth\ChangePassword::class)->set('password', 'short')->set('password_confirmation', 'short')->call('save')->assertHasErrors('password');
        Livewire::test(\App\Livewire\Auth\ChangePassword::class)->set('password', 'my-own-pass-9')->set('password_confirmation', 'my-own-pass-9')->call('save')->assertRedirect();

        $old->refresh();
        $this->assertFalse($old->must_change_password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('my-own-pass-9', $old->password));
        $this->get('/')->assertOk();
        auth()->logout();
        Livewire::test(Login::class)->set('email', 'old.account@example.com')->set('password', 'Pass123')->call('signIn')->assertNoRedirect();
        $this->assertGuest();
    }

    public function test_generic_password_can_skip_executive_admins(): void
    {
        config(['ideas.default_password' => 'Pass123']);
        $admin = User::factory()->create(['email' => 'boss.old@example.com', 'password' => null, 'is_admin' => true]);
        $this->artisan('ideas:set-default-passwords --except-admins')->assertSuccessful();
        $this->assertNull($admin->fresh()->password);
    }
}
