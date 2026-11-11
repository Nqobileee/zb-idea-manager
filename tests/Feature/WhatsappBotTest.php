<?php

namespace Tests\Feature;

use App\Models\Idea;
use App\Models\SentEmail;
use App\Models\User;
use App\Models\WhatsappSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsappBotTest extends TestCase
{
    use RefreshDatabase;

    private string $phone = '263771234567';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Tests\Fixtures\SampleDataSeeder::class);
    }

    /** Send a text or a button tap through the real webhook, the way Meta would. */
    private function say(string $text, bool $button = false, ?string $phone = null)
    {
        $msg = ['from' => $phone ?? $this->phone, 'id' => 'wamid.'.uniqid(), 'type' => $button ? 'interactive' : 'text'];
        $button
            ? $msg['interactive'] = ['type' => 'button_reply', 'button_reply' => ['id' => $text, 'title' => $text]]
            : $msg['text'] = ['body' => $text];

        return $this->postJson('/webhooks/whatsapp', ['entry' => [['changes' => [['value' => ['messages' => [$msg]]]]]]]);
    }

    /** Put the user's number on their account, as Smile Factory registration would, then say hi. */
    private function link(string $email = 'tinashe.moyo@zb.co.zw', ?string $phone = null): void
    {
        User::where('email', $email)->update(['phone' => $phone ?? $this->phone]);
        $this->say('hi', phone: $phone);
    }

    public function test_webhook_verification_handshake(): void
    {
        config(['ideas.whatsapp.verify_token' => 'secret']);
        $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=secret&hub_challenge=12345')->assertOk()->assertSee('12345');
        $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=1')->assertForbidden();
    }

    public function test_unsigned_requests_are_rejected_when_a_secret_is_set(): void
    {
        config(['ideas.whatsapp.app_secret' => 'shh']);
        $this->postJson('/webhooks/whatsapp', ['entry' => []])->assertUnauthorized();
        $body = json_encode(['entry' => []]);
        $this->call('POST', '/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'shh')], $body)->assertOk();
    }

    public function test_a_new_number_gets_an_account_with_no_email_or_code_step(): void
    {
        $this->say('hi', phone: '263779999999');
        $user = User::where('phone', '263779999999')->firstOrFail();
        $this->assertSame('General', $user->role_label);
        $this->assertSame($user->id, WhatsappSession::where('phone', '263779999999')->first()->user_id);
        $this->assertSame('idle', WhatsappSession::where('phone', '263779999999')->first()->state);
        $this->assertDatabaseCount('login_codes', 0);
    }

    public function test_known_numbers_keep_their_account(): void
    {
        $this->link();
        $this->assertSame(User::where('email', 'tinashe.moyo@zb.co.zw')->first()->id, WhatsappSession::first()->user_id);
    }

    public function test_web_command_sends_a_one_use_sign_in_link(): void
    {
        $this->link();
        $this->partialMock(\App\Services\WhatsappClient::class, fn ($m) => $m->shouldReceive('text')->once()->withArgs(fn ($phone, $body) => str_contains($body, '/wa-login/')));
        $this->say('web');
    }

    public function test_stop_turns_notifications_off(): void
    {
        $this->link();
        $user = User::where('email', 'tinashe.moyo@zb.co.zw')->first();
        $this->say('stop');
        $this->assertFalse($user->fresh()->whatsapp_opt_in);
    }

    public function test_post_an_idea_by_chat(): void
    {
        $this->link();
        $this->say('new idea');
        $this->say('Digital queue tickets');
        $this->say('Customers get a ticket on their phone and skip the line.');
        $this->say('Details of how the idea works.');
        $this->say('ch_none', true);
        $this->assertSame('idea_confirm', WhatsappSession::first()->state);
        $this->say('post', true);

        $idea = Idea::where('title', 'Digital queue tickets')->firstOrFail();
        $this->assertSame('whatsapp', $idea->source);
        $this->assertSame('Tinashe Moyo', $idea->author->name);
        $this->assertSame('idle', WhatsappSession::first()->state);
    }

    public function test_cancel_clears_the_draft(): void
    {
        $this->link();
        $this->say('new idea');
        $this->say('Half done');
        $this->say('cancel');
        $s = WhatsappSession::first();
        $this->assertSame('idle', $s->state);
        $this->assertNull($s->draft);
    }

    public function test_employees_cannot_use_the_executive_commands(): void
    {
        $this->link();
        $idea = Idea::where('approved', false)->first();
        $this->say('approve_'.$idea->id, true);
        $this->assertFalse($idea->fresh()->approved);
        $this->assertSame('idle', WhatsappSession::first()->state);
    }

    public function test_executive_approves_from_whatsapp_and_author_is_notified(): void
    {
        $exec = User::where('is_admin', true)->first();
        $this->link($exec->email);
        $idea = Idea::where('approved', false)->first();
        $this->say('approve_'.$idea->id, true);
        $this->say('Worth a pilot.');
        $this->say('yes', true);

        $idea->refresh();
        $this->assertTrue($idea->approved);
        $this->assertSame($exec->id, $idea->approved_by);
        $this->assertSame('Worth a pilot.', $idea->approval_note);
        $this->assertDatabaseHas('activities', ['user_id' => $idea->user_id, 'type' => 'approval']);
    }

    public function test_duplicate_deliveries_are_ignored(): void
    {
        $payload = ['entry' => [['changes' => [['value' => ['messages' => [['from' => $this->phone, 'id' => 'wamid.same', 'type' => 'text', 'text' => ['body' => 'hi']]]]]]]]];
        $this->postJson('/webhooks/whatsapp', $payload)->assertOk();
        $this->postJson('/webhooks/whatsapp', $payload)->assertOk();
        $this->assertSame(1, \App\Models\WhatsappMessage::where('direction', 'in')->count());
    }
}
