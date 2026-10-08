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
        $this->seed();
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

    public function test_only_work_emails_can_sign_in(): void
    {
        Livewire::test(Login::class)->set('email', 'someone@gmail.com')->call('sendCode')->assertSet('step', 'email');
        $this->assertDatabaseCount('login_codes', 0);
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
