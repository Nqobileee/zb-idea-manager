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
        if (! $u->phone) {
            $u->update(['phone' => '2637'.str_pad((string) $u->id, 8, '0', STR_PAD_LEFT)]);
        }

        return ['contact' => ['phone' => $u->phone, 'fields' => ['email' => $u->email]]] + $extra;
    }

    public function test_secret_is_required(): void
    {
        $this->postJson('/api/zernio/challenges')->assertUnauthorized();
        $this->postJson('/api/zernio/challenges', [], ['X-Zernio-Secret' => 'wrong'])->assertUnauthorized();
        config(['ideas.zernio_secret' => '']);
        $this->postJson('/api/zernio/challenges', [], ['X-Zernio-Secret' => ''])->assertStatus(503);
    }

    public function test_link_creates_a_new_person_with_the_chatbot_password(): void
    {
        $this->call_('/link', ['email' => 'new.founder@example.com', 'phone' => '263772223333', 'contact' => ['name' => 'New Founder'], 'category' => 'Founder', 'password' => 'chat-made-pass'])
            ->assertOk()->assertJson(['ok' => true, 'first_name' => 'New', 'is_executive' => false]);
        $u = User::where('email', 'new.founder@example.com')->firstOrFail();
        $this->assertSame('263772223333', $u->phone);
        $this->assertSame('Hub member', $u->role_label);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('chat-made-pass', $u->password));

        // asking again from the same number finds the same account and never changes the password
        $this->call_('/link', ['email' => 'new.founder@example.com', 'phone' => '263772223333', 'password' => 'other'])->assertJson(['user_id' => $u->id]);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('chat-made-pass', $u->fresh()->password));
    }

    public function test_an_email_typed_in_chat_cannot_take_over_an_existing_account(): void
    {
        $exec = User::where('is_admin', true)->firstOrFail();
        $this->call_('/link', ['email' => $exec->email, 'phone' => '263774445555', 'password' => 'attacker'])->assertStatus(409)->assertJson(['ok' => false]);
        $this->assertNull($exec->fresh()->phone);
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('attacker', (string) $exec->fresh()->password));

        // and an unknown number cannot borrow someone's account just by sending their email
        $this->call_('/reports', ['email' => $exec->email, 'phone' => '263774445555', 'reportChoice' => 'Programme summary'])->assertNotFound();
    }

    public function test_unknown_people_are_told_to_register(): void
    {
        $this->call_('/ideas/mine', ['email' => 'nobody@example.com', 'phone' => '263779990000'])->assertNotFound()->assertJson(['ok' => false]);
    }

    public function test_post_an_idea_through_preview_and_create(): void
    {
        $u = User::where('is_admin', false)->firstOrFail();
        $ch = Challenge::first();
        $this->call_('/challenges/options', $this->as_($u))->assertOk()->assertSee('0. None');
        $draft = $this->as_($u, ['ideaTitle' => 'Queue tickets', 'ideaSummary' => 'Skip the line.', 'ideaDetails' => 'More.', 'ideaChallengeRef' => 'id:'.$ch->id, 'ideaVisibility' => 'Public']);

        $this->call_('/ideas/preview', $draft)->assertJson(['ok' => true])->assertSee('Visibility: Public');
        $this->call_('/ideas/preview', $draft)->assertSee('Details')->assertSee('More.');
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

    public function test_link_greets_once_a_day_with_the_first_name(): void
    {
        $this->travelTo(now('Africa/Harare')->setTime(9, 0));
        $body = ['email' => 'gina.m@example.com', 'phone' => '263775550001', 'contact' => ['name' => 'Gina Moyo']];
        $first = $this->call_('/link', $body)->assertJson(['greeted_today' => false])->json();
        $this->assertSame('Good morning, Gina. What would you like to do today?', $first['greeting']);

        $this->call_('/link', $body)->assertJson(['greeting' => '', 'greeted_today' => true]);

        $this->travelTo(now('Africa/Harare')->addDay()->setTime(14, 0));
        $this->assertStringStartsWith('Good afternoon, Gina', $this->call_('/link', $body)->json('greeting'));
        $this->travelTo(now('Africa/Harare')->addDay()->setTime(19, 0));
        $this->assertStringStartsWith('Good evening, Gina', $this->call_('/link', $body)->json('greeting'));
    }

    public function test_idea_and_challenge_details_are_full_text_ending_with_a_link(): void
    {
        $u = User::where('is_admin', false)->firstOrFail();
        $idea = Idea::create(['num' => Idea::nextNumber(), 'user_id' => $u->id, 'title' => 'Long one', 'summary' => 'Short summary', 'body' => str_repeat('detail words ', 600), 'status' => 'Pilot', 'visibility' => 'public']);
        $msg = $this->call_('/ideas/detail', $this->as_($u, ['ideaRef' => 'id:'.$idea->id]))->assertJson(['ok' => true, 'idea_id' => $idea->id])->json('message');
        $this->assertStringContainsString('Summary', $msg);
        $this->assertStringContainsString('Details', $msg);
        $this->assertStringContainsString('Public', $msg);
        $this->assertLessThanOrEqual(4000, mb_strlen($msg));
        $this->assertStringEndsWith('(continued)', $msg);
        $more = $this->call_('/ideas/detail', $this->as_($u, ['ideaRef' => 'id:'.$idea->id]))->json('message_more');
        $this->assertLessThanOrEqual(4000, mb_strlen($more));
        $this->assertStringEndsWith('View on the web: '.route('ideas.show', $idea), $more);

        $short = Idea::create(['num' => Idea::nextNumber(), 'user_id' => $u->id, 'title' => 'Short one', 'summary' => 'Short', 'body' => 'A bit more detail.', 'status' => 'Idea', 'visibility' => 'public']);
        $res = $this->call_('/ideas/detail', $this->as_($u, ['ideaRef' => 'id:'.$short->id]))->assertJsonMissingPath('message_more')->json('message');
        $this->assertStringEndsWith('View on the web: '.route('ideas.show', $short), $res);

        $ch = Challenge::first();
        $msg = $this->call_('/challenges/detail', $this->as_($u, ['chRef' => 'id:'.$ch->id]))->assertJson(['ok' => true, 'challenge_id' => $ch->id])->json('message');
        $this->assertStringContainsString($ch->brief, $msg);
        $this->assertStringEndsWith('View on the web: '.route('challenges.show', $ch), $msg);
    }

    public function test_my_ideas_is_numbered_and_resolves_mine_refs(): void
    {
        $u = Idea::first()->author;
        $this->call_('/ideas/mine', $this->as_($u))->assertOk()->assertSee('1.');
        $this->call_('/ideas/detail', $this->as_($u, ['ideaRef' => 'mine:1']))->assertJson(['ok' => true]);
        $this->call_('/ideas/like', $this->as_($u, ['ideaRef' => 'mine:1']))->assertJson(['ok' => true]);
    }

    public function test_pipeline_groups_by_stage_with_one_running_number(): void
    {
        $u = User::where('is_admin', false)->firstOrFail();
        $msg = $this->call_('/pipeline', $this->as_($u))->assertOk()->assertJson(['ok' => true])->json('message');
        $this->assertStringStartsWith('Pipeline', $msg);
        foreach (Idea::STATUSES as $stage) {
            $this->assertStringContainsString($stage.' (', $msg);
        }
        $this->assertStringContainsString('1. ', $msg);
        $this->call_('/ideas/detail', $this->as_($u, ['ideaRef' => 'pipeline:1']))->assertJson(['ok' => true]);

        for ($i = 0; $i < 5; $i++) {
            Idea::create(['num' => Idea::nextNumber(), 'user_id' => $u->id, 'title' => "Extra {$i}", 'summary' => 's', 'body' => 'b', 'status' => 'Idea', 'visibility' => 'public']);
        }
        $this->assertStringContainsString('Full pipeline: '.route('pipeline'), $this->call_('/pipeline', $this->as_($u))->json('message'));
    }

    public function test_pipeline_hides_private_ideas_from_other_people(): void
    {
        $author = User::where('is_admin', false)->firstOrFail();
        $other = User::where('is_admin', false)->where('id', '!=', $author->id)->firstOrFail();
        Idea::create(['num' => Idea::nextNumber(), 'user_id' => $author->id, 'title' => 'Hush hush', 'summary' => 's', 'body' => 'b', 'status' => 'Idea', 'visibility' => 'private']);
        $this->assertStringNotContainsString('Hush hush', $this->call_('/pipeline', $this->as_($other))->json('message'));
        // the author's own private idea is counted for them (shown only if it ranks in the top 3 of its stage)
        $this->assertStringContainsString('Idea (7)', $this->call_('/pipeline', $this->as_($author))->json('message'));
        $this->assertStringContainsString('Idea (6)', $this->call_('/pipeline', $this->as_($other))->json('message'));
    }

    public function test_notification_list_shows_status_and_latest_activity(): void
    {
        $u = Idea::first()->author;
        $this->call_('/notifications/list', $this->as_($u))->assertOk()->assertJson(['ok' => true, 'enabled' => true])->assertSee('WhatsApp alerts are on');
        $this->call_('/notifications', $this->as_($u, ['menuReply' => 'stop']));
        $this->call_('/notifications/list', $this->as_($u))->assertJson(['enabled' => false])->assertSee('WhatsApp alerts are off');
    }

    public function test_account_check_only_says_whether_the_email_exists(): void
    {
        $u = User::where('is_admin', false)->firstOrFail();
        $this->call_('/account/check', ['regEmail' => strtoupper($u->email)])->assertJson(['ok' => true, 'exists' => true, 'first_name' => $u->first_name])->assertJsonMissing(['email' => $u->email]);
        $this->call_('/account/check', ['variables' => ['regEmail' => 'nobody@example.com']])->assertJson(['exists' => false])->assertJsonMissingPath('first_name');
        $this->call_('/account/check', ['regEmail' => 'not an email'])->assertStatus(422);
    }

    public function test_account_login_checks_the_hash_attaches_the_number_and_locks_after_five_wrong_tries(): void
    {
        $u = User::where('is_admin', false)->firstOrFail();
        $u->update(['password' => \Illuminate\Support\Facades\Hash::make('right-pass-1'), 'phone' => null]);
        $body = ['regEmail' => $u->email, 'contact' => ['phone' => '263776000111']];

        $this->call_('/account/login', $body + ['regPassword' => 'wrong'])->assertJson(['ok' => false])->assertSee('not correct');
        $this->assertNull($u->fresh()->phone);

        $this->call_('/account/login', $body + ['regPassword' => 'right-pass-1'])->assertJson(['ok' => true, 'role' => 'general', 'full_name' => $u->name]);
        $this->assertSame('263776000111', $u->fresh()->phone);

        $exec = User::where('is_admin', true)->firstOrFail();
        $exec->update(['password' => \Illuminate\Support\Facades\Hash::make('exec-pass-1')]);
        $this->call_('/account/login', ['regEmail' => $exec->email, 'regPassword' => 'exec-pass-1', 'contact' => ['phone' => '263776000222']])->assertJson(['ok' => true, 'role' => 'executive']);

        $bad = ['regEmail' => $u->email, 'contact' => ['phone' => '263776000999']];
        foreach (range(1, 5) as $i) {
            $this->call_('/account/login', $bad + ['regPassword' => 'nope'.$i])->assertJson(['ok' => false]);
        }
        $this->call_('/account/login', $bad + ['regPassword' => 'right-pass-1'])->assertJson(['ok' => false, 'locked' => true]);
    }

    public function test_register_a_general_member_who_can_then_sign_in_on_the_web(): void
    {
        $body = ['regName' => 'Pelagia Dube', 'regEmail' => 'pelagia@example.com', 'regPassword' => 'longenough1', 'regRole' => 'General Member', 'contact' => ['phone' => '263778000001']];
        $this->call_('/account/register', $body)->assertJson(['ok' => true, 'role' => 'general']);
        $u = User::where('email', 'pelagia@example.com')->firstOrFail();
        $this->assertSame('General', $u->role_label);
        $this->assertSame('263778000001', $u->phone);
        $this->assertNotSame('longenough1', $u->password);

        \Livewire\Livewire::test(\App\Livewire\Auth\Login::class)->set('email', 'pelagia@example.com')->set('password', 'longenough1')->call('signIn')->assertRedirect();
        $this->assertAuthenticatedAs($u);
    }

    public function test_register_validates_and_blocks_duplicates(): void
    {
        $base = ['regName' => 'Test Person', 'regEmail' => 'test.person@example.com', 'regPassword' => 'longenough1', 'regRole' => 'General Member', 'contact' => ['phone' => '263778000002']];
        $this->call_('/account/register', ['regPassword' => 'short'] + $base)->assertJson(['ok' => false])->assertSee('8 characters');
        $this->call_('/account/register', ['regEmail' => 'bad'] + $base)->assertJson(['ok' => false]);
        $this->call_('/account/register', ['regName' => ''] + $base)->assertJson(['ok' => false]);
        $this->call_('/account/register', ['regEmail' => User::first()->email] + $base)->assertJson(['ok' => false])->assertSee('already registered');
        $this->assertNull(User::where('email', 'test.person@example.com')->first());
    }

    public function test_executive_admin_registration_needs_the_secret_code(): void
    {
        $req = ['regName' => 'Wants Power', 'regEmail' => 'wants.power@example.com', 'regPassword' => 'longenough1', 'regRole' => 'Executive admin', 'contact' => ['phone' => '263778000003']];

        config(['ideas.executive_code' => '']);
        $this->call_('/account/register', $req + ['regExecCode' => 'anything'])->assertJson(['ok' => false]);
        $this->assertNull(User::where('email', 'wants.power@example.com')->first());

        config(['ideas.executive_code' => 'ZB-EXEC-2026']);
        $this->call_('/account/register', $req)->assertJson(['ok' => false, 'code_required' => true]);
        $this->call_('/account/register', $req + ['regExecCode' => 'wrong'])->assertJson(['ok' => false, 'code_required' => true]);
        $this->assertNull(User::where('email', 'wants.power@example.com')->first());

        $this->call_('/account/register', $req + ['regExecCode' => 'ZB-EXEC-2026'])->assertJson(['ok' => true, 'role' => 'executive']);
        $u = User::where('email', 'wants.power@example.com')->firstOrFail();
        $this->assertTrue($u->is_admin);
        $this->assertSame('Executive admin', $u->role_label);
    }

    public function test_wrong_executive_codes_lock_out_after_five_tries(): void
    {
        config(['ideas.executive_code' => 'ZB-EXEC-2026']);
        $req = ['regName' => 'Guesser', 'regEmail' => 'guesser@example.com', 'regPassword' => 'longenough1', 'regRole' => 'Executive admin', 'contact' => ['phone' => '263778000005']];
        foreach (range(1, 5) as $i) {
            $this->call_('/account/register', $req + ['regExecCode' => 'guess'.$i])->assertJson(['ok' => false]);
        }
        $this->call_('/account/register', $req + ['regExecCode' => 'ZB-EXEC-2026'])->assertJson(['ok' => false, 'locked' => true]);
        $this->assertNull(User::where('email', 'guesser@example.com')->first());
    }

    public function test_switch_code_unlinks_the_number_so_another_account_can_sign_in(): void
    {
        config(['ideas.switch_code' => 'SWITCH-1234']);
        $a = User::where('is_admin', false)->firstOrFail();
        $b = User::where('is_admin', true)->firstOrFail();
        $a->update(['phone' => '263779100100', 'password' => \Illuminate\Support\Facades\Hash::make('pass-a-1234')]);
        $b->update(['phone' => null, 'password' => \Illuminate\Support\Facades\Hash::make('pass-b-1234')]);
        $contact = ['contact' => ['phone' => '263779100100']];

        $this->call_('/account/switch', $contact + ['switchCode' => 'wrong'])->assertJson(['ok' => false]);
        $this->assertSame('263779100100', $a->fresh()->phone);

        $this->call_('/account/switch', $contact + ['switchCode' => ' switch-1234 '])->assertJson(['ok' => true, 'restart' => true, 'was_signed_in' => true]);
        $this->assertNull($a->fresh()->phone);

        $this->call_('/account/login', $contact + ['regEmail' => $b->email, 'regPassword' => 'pass-b-1234'])->assertJson(['ok' => true, 'role' => 'executive']);
        $this->assertSame('263779100100', $b->fresh()->phone);
        $this->assertNull($a->fresh()->phone);
    }

    public function test_signing_in_to_an_account_moves_the_number_even_from_another_phone(): void
    {
        $u = User::where('is_admin', false)->firstOrFail();
        $u->update(['phone' => '263779200001', 'password' => \Illuminate\Support\Facades\Hash::make('move-pass-1')]);
        $this->call_('/account/login', ['regEmail' => $u->email, 'regPassword' => 'move-pass-1', 'contact' => ['phone' => '263779200002']])->assertJson(['ok' => true]);
        $this->assertSame('263779200002', $u->fresh()->phone);
    }

    public function test_switch_is_closed_without_a_code_and_locks_after_five_wrong_tries(): void
    {
        $this->call_('/account/switch', ['contact' => ['phone' => '263779300001'], 'switchCode' => 'x'])->assertJson(['ok' => false]);
        config(['ideas.switch_code' => 'SWITCH-9999']);
        foreach (range(1, 5) as $i) {
            $this->call_('/account/switch', ['contact' => ['phone' => '263779300002'], 'switchCode' => "g{$i}"])->assertJson(['ok' => false]);
        }
        $this->call_('/account/switch', ['contact' => ['phone' => '263779300002'], 'switchCode' => 'SWITCH-9999'])->assertJson(['ok' => false, 'locked' => true]);
    }

    public function test_chatbot_sign_in_with_the_generic_password_flags_a_forced_change(): void
    {
        config(['ideas.default_password' => 'Pass123']);
        $u = User::where('is_admin', false)->firstOrFail();
        $u->update(['password' => \Illuminate\Support\Facades\Hash::make('Pass123')]);
        $this->call_('/account/login', ['regEmail' => $u->email, 'regPassword' => 'Pass123', 'contact' => ['phone' => '263779400001']])
            ->assertJson(['ok' => true, 'must_change_password' => true])->assertSee('temporary password');
        $this->assertTrue($u->fresh()->must_change_password);
    }

    public function test_sign_in_and_register_replies_carry_a_menu_text_so_the_next_message_is_never_empty(): void
    {
        $u = User::where('is_admin', false)->firstOrFail();
        $u->update(['password' => \Illuminate\Support\Facades\Hash::make('menu-pass-12')]);
        $body = ['regEmail' => $u->email, 'regPassword' => 'menu-pass-12', 'contact' => ['phone' => '263779500001']];

        $first = $this->call_('/account/login', $body)->assertJson(['ok' => true, 'greeted_today' => false])->json();
        $this->assertStringStartsWith('Good', $first['greeting']);
        $this->assertSame($first['greeting'], $first['menu_text']);

        $again = $this->call_('/account/login', $body)->assertJson(['greeted_today' => true, 'greeting' => ''])->json();
        $this->assertSame('What would you like to do?', $again['menu_text']);

        $reg = $this->call_('/account/register', ['regName' => 'Menu Person', 'regEmail' => 'menu.person@example.com', 'regPassword' => 'longenough1', 'regRole' => 'General Member', 'contact' => ['phone' => '263779500002']])->assertJson(['ok' => true])->json();
        $this->assertNotSame('', $reg['menu_text']);
    }

    public function test_switch_lists_this_numbers_linked_accounts_with_masked_emails_and_the_picker_returns_the_choice(): void
    {
        config(['ideas.switch_code' => 'SWITCH-7777']);
        $phone = '263779600001';
        $a = User::where('is_admin', false)->firstOrFail();
        $b = User::where('is_admin', true)->firstOrFail();
        $stranger = User::where('is_admin', false)->where('id', '!=', $a->id)->firstOrFail();
        $a->update(['password' => \Illuminate\Support\Facades\Hash::make('pass-a-12345')]);
        $b->update(['password' => \Illuminate\Support\Facades\Hash::make('pass-b-12345')]);
        $c = ['contact' => ['phone' => $phone]];

        // before anything is linked the switch keeps the old reply
        $this->call_('/account/switch', $c + ['switchCode' => 'SWITCH-7777'])->assertJson(['ok' => true, 'restart' => true, 'accounts' => 0])->assertSee('email address');

        $this->call_('/account/login', $c + ['regEmail' => $a->email, 'regPassword' => 'pass-a-12345'])->assertJson(['ok' => true]);
        $this->call_('/account/login', $c + ['regEmail' => $b->email, 'regPassword' => 'wrong'])->assertJson(['ok' => false]);
        $this->assertDatabaseMissing('whatsapp_account_links', ['phone' => $phone, 'user_id' => $b->id]); // failed sign-in adds nothing
        $this->travel(5)->minutes();
        $this->call_('/account/login', $c + ['regEmail' => $b->email, 'regPassword' => 'pass-b-12345'])->assertJson(['ok' => true]);
        \Illuminate\Support\Facades\DB::table('whatsapp_account_links')->insert(['phone' => '263779600999', 'user_id' => $stranger->id, 'last_used_at' => now()]);

        $msg = $this->call_('/account/switch', $c + ['switchCode' => 'SWITCH-7777'])->assertJson(['ok' => true, 'accounts' => 2])->json('message');
        $this->assertStringContainsString("1. {$b->name} · Executive admin · ", $msg);
        $this->assertStringContainsString("2. {$a->name} · General Member · ", $msg);
        $this->assertStringContainsString('3. Create a new account', $msg);
        $this->assertStringContainsString('Reply with a number, or send an email address.', $msg);
        $this->assertStringNotContainsString($a->email, $msg);
        $this->assertStringNotContainsString($stranger->name, $msg);
        $this->assertMatchesRegularExpression('/[a-z0-9]•••[a-z0-9]@/i', $msg);
        $this->assertNull($b->fresh()->phone);

        // the picker returns the email but signs nothing in
        $this->call_('/account/switch/pick', $c + ['accountRef' => 'accounts:2'])->assertOk()->assertJson(['ok' => true, 'action' => 'login', 'email' => $a->email]);
        $this->assertNull($a->fresh()->phone);
        $this->call_('/account/switch/pick', $c + ['accountRef' => 'accounts:3'])->assertJson(['ok' => true, 'action' => 'new']);
        $this->call_('/account/switch/pick', $c + ['accountRef' => 'accounts:9'])->assertOk()->assertJson(['ok' => false]);
        $this->call_('/account/switch/pick', $c + ['accountRef' => 'top:1'])->assertJson(['ok' => false]);
        $this->call_('/account/switch/pick', ['contact' => ['phone' => '263779600777'], 'accountRef' => 'accounts:1'])->assertJson(['ok' => false]); // another number has no list

        // a wrong password after picking changes nothing; the right one moves the number
        $this->call_('/account/login', $c + ['regEmail' => $a->email, 'regPassword' => 'nope'])->assertJson(['ok' => false]);
        $this->assertNull($a->fresh()->phone);
        $this->call_('/account/login', $c + ['regEmail' => $a->email, 'regPassword' => 'pass-a-12345'])->assertJson(['ok' => true]);
        $this->assertSame($phone, $a->fresh()->phone);
    }
}
