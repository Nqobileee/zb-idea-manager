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
        $this->seed();
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

    private function link(string $email = 'tinashe.moyo@zb.co.zw', ?string $phone = null): void
    {
        $this->say('hi', phone: $phone);
        $this->say($email, phone: $phone);
        preg_match('/code is (\d{6})/', SentEmail::where('type', 'Code')->latest('id')->firstOrFail()->body, $m);
        $this->say($m[1], phone: $phone);
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

    public function test_link_a_number_with_an_email_code(): void
    {
        $this->say('hi');
        $this->assertSame('link_email', WhatsappSession::first()->state);
        $this->say('not-a-zb-email@gmail.com');
        $this->assertSame('link_email', WhatsappSession::first()->state);
        $this->say('tinashe.moyo@zb.co.zw');
        $this->assertSame('link_code', WhatsappSession::first()->state);
        $this->say('000000');
        $this->assertNull(WhatsappSession::first()->user_id);

        $this->link();
        $user = User::where('email', 'tinashe.moyo@zb.co.zw')->first();
        $this->assertSame($this->phone, $user->phone);
        $this->assertSame($user->id, WhatsappSession::first()->user_id);
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

    public function test_stop_and_unlink(): void
    {
        $this->link();
        $user = User::where('email', 'tinashe.moyo@zb.co.zw')->first();
        $this->say('stop');
        $this->assertFalse($user->fresh()->whatsapp_opt_in);
        $this->say('unlink');
        $this->assertNull($user->fresh()->phone);
        $this->assertNull(WhatsappSession::first()->user_id);
    }

    public function test_lockout_after_too_many_wrong_codes(): void
    {
        $this->say('hi');
        $this->say('tinashe.moyo@zb.co.zw');
        foreach (range(1, 5) as $i) {
            $this->say('11111'.$i);
        }
        $this->assertNotNull(WhatsappSession::first()->locked_until);
        $this->say('hi');
        $this->assertNull(WhatsappSession::first()->user_id);
    }

    public function test_duplicate_deliveries_are_ignored(): void
    {
        $payload = ['entry' => [['changes' => [['value' => ['messages' => [['from' => $this->phone, 'id' => 'wamid.same', 'type' => 'text', 'text' => ['body' => 'hi']]]]]]]]];
        $this->postJson('/webhooks/whatsapp', $payload)->assertOk();
        $this->postJson('/webhooks/whatsapp', $payload)->assertOk();
        $this->assertSame(1, \App\Models\WhatsappMessage::where('direction', 'in')->count());
    }
}
