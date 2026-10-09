<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\Idea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZernioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ideas.zernio_secret' => 's3cret']);
        $this->seed(\Tests\Fixtures\SampleDataSeeder::class);
    }

    private function call_(string $path, array $body = [])
    {
        return $this->postJson('/api/zernio'.$path, $body, ['X-Zernio-Secret' => 's3cret']);
    }

    private function as_(User $u, array $extra = []): array
    {
        return ['contact' => ['phone' => $u->phone ?? '263771110000', 'fields' => ['email' => $u->email]]] + $extra;
    }

    public function test_secret_is_required(): void
    {
        $this->postJson('/api/zernio/challenges')->assertUnauthorized();
        $this->postJson('/api/zernio/challenges', [], ['X-Zernio-Secret' => 'wrong'])->assertUnauthorized();
        config(['ideas.zernio_secret' => '']);
        $this->postJson('/api/zernio/challenges', [], ['X-Zernio-Secret' => ''])->assertStatus(503);
    }

    public function test_link_creates_or_finds_the_user_and_reports_executive_status(): void
    {
        $this->call_('/link', ['email' => 'new.founder@example.com', 'phone' => '263772223333', 'contact' => ['name' => 'New Founder'], 'category' => 'Founder'])
            ->assertOk()->assertJson(['ok' => true, 'first_name' => 'New', 'is_executive' => false]);
        $u = User::where('email', 'new.founder@example.com')->firstOrFail();
        $this->assertSame('263772223333', $u->phone);
        $this->assertSame('Hub member', $u->role_label);

        $exec = User::where('is_admin', true)->firstOrFail();
        $this->call_('/link', ['email' => $exec->email, 'phone' => '263774445555'])->assertJson(['is_executive' => true]);
        $this->assertSame('263774445555', $exec->fresh()->phone);
    }

    public function test_unknown_people_are_told_to_register(): void
    {
        $this->call_('/ideas/mine', ['email' => 'nobody@example.com'])->assertNotFound()->assertJson(['ok' => false]);
    }

    public function test_post_an_idea_through_preview_and_create(): void
    {
        $u = User::where('is_admin', false)->firstOrFail();
        $ch = Challenge::first();
        $this->call_('/challenges/options', $this->as_($u))->assertOk()->assertSee('0. None');
        $draft = $this->as_($u, ['ideaTitle' => 'Queue tickets', 'ideaSummary' => 'Skip the line.', 'ideaDetails' => 'More.', 'ideaChallengeRef' => 'id:'.$ch->id, 'ideaVisibility' => 'Public']);

        $this->call_('/ideas/preview', $draft)->assertJson(['ok' => true])->assertSee('Visibility: Public');
        $this->call_('/ideas/preview', ['ideaTitle' => str_repeat('x', 91)] + $draft)->assertJson(['ok' => false]);

        $this->call_('/ideas', $draft)->assertOk()->assertJson(['ok' => true]);
        $idea = Idea::where('title', 'Queue tickets')->firstOrFail();
        $this->assertSame('whatsapp', $idea->source);
        $this->assertSame('public', $idea->visibility);
        $this->assertSame($ch->id, $idea->challenge_id);
        $this->assertSame($u->id, $idea->user_id);

        $this->call_('/ideas', ['ideaVisibility' => 'Private', 'ideaChallengeRef' => '0'] + $this->as_($u, ['ideaTitle' => 'Secret', 'ideaSummary' => 's']))->assertOk();
        $this->assertSame('private', Idea::where('title', 'Secret')->first()->visibility);
    }

    public function test_top_list_numbers_resolve_and_like_toggles(): void
    {
        $u = User::where('is_admin', false)->firstOrFail();
        $this->call_('/ideas/top', $this->as_($u))->assertOk()->assertSee('1.');
        $id = $this->call_('/ideas/detail', $this->as_($u, ['ideaRef' => 'top:1']))->assertJson(['ok' => true])->json('idea_id');
        $this->call_('/ideas/like', $this->as_($u, ['ideaRef' => 'id:'.$id]))->assertJson(['ok' => true]);
        $this->call_('/ideas/detail', $this->as_($u, ['ideaRef' => 'top:99']))->assertJson(['ok' => false]);
    }

    public function test_private_ideas_are_hidden_from_other_people_but_not_executives(): void
    {
        $author = User::where('is_admin', false)->firstOrFail();
        $other = User::where('is_admin', false)->where('id', '!=', $author->id)->firstOrFail();
        $exec = User::where('is_admin', true)->firstOrFail();
        $idea = Idea::create(['num' => Idea::nextNumber(), 'user_id' => $author->id, 'title' => 'Hidden one', 'summary' => 's', 'body' => 'b', 'status' => 'Idea', 'visibility' => 'private']);

        $this->call_('/ideas/detail', $this->as_($other, ['ideaRef' => 'id:'.$idea->id]))->assertJson(['ok' => false]);
        $this->call_('/ideas/detail', $this->as_($author, ['ideaRef' => 'id:'.$idea->id]))->assertJson(['ok' => true]);
        $this->call_('/ideas/detail', $this->as_($exec, ['ideaRef' => 'id:'.$idea->id]))->assertJson(['ok' => true]);
    }

    public function test_executive_endpoints_reject_everyone_else(): void
    {
        $u = User::where('is_admin', false)->firstOrFail();
        foreach (['/ideas/top5', '/reports', '/ideas/approve', '/challenges/ideas'] as $path) {
            $this->call_($path, $this->as_($u))->assertForbidden();
        }
    }

    public function test_executive_challenge_ideas_reports_and_approval(): void
    {
        $exec = User::where('is_admin', true)->firstOrFail();
        $this->call_('/challenges', $this->as_($exec))->assertOk();
        $d = $this->call_('/challenges/detail', $this->as_($exec, ['chRef' => 'list:1']))->assertJson(['ok' => true])->json();
        $this->call_('/challenges/ideas', $this->as_($exec, ['chDetail' => ['body' => ['challenge_id' => $d['challenge_id']]]]))->assertOk()->assertJson(['ok' => true]);

        foreach (['Programme summary', 'Top 10 by likes', 'Awaiting approval', 'Ideas by stage', 'By challenge'] as $report) {
            $this->call_('/reports', $this->as_($exec, ['reportChoice' => $report]))->assertOk()->assertJson(['ok' => true]);
        }

        $idea = Idea::where('approved', false)->firstOrFail();
        $this->call_('/ideas/approve', $this->as_($exec, ['ideaRef' => 'id:'.$idea->id, 'approveNote' => 'Skip']))->assertJson(['ok' => true]);
        $this->assertTrue($idea->fresh()->approved);
        $this->call_('/ideas/top5', $this->as_($exec))->assertOk()->assertSee('1.');
    }

    public function test_web_link_and_notifications(): void
    {
        $u = User::where('is_admin', false)->firstOrFail();
        $this->call_('/web-link', $this->as_($u))->assertOk()->assertSee('wa-login');
        $this->call_('/notifications', $this->as_($u, ['menuReply' => 'stop']))->assertOk();
        $this->assertFalse($u->fresh()->whatsapp_opt_in);
        $this->call_('/notifications', $this->as_($u, ['menuReply' => 'start notifications']))->assertOk();
        $this->assertTrue($u->fresh()->whatsapp_opt_in);
    }
}
