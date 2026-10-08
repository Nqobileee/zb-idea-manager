<?php

namespace App\Services;

use App\Models\Idea;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Thin wrapper around the WhatsApp Cloud API. With no token configured it only logs, so local work needs no Meta account. */
class WhatsappClient
{
    public function text(string $phone, string $body): void
    {
        $this->send($phone, ['type' => 'text', 'text' => ['body' => mb_substr($body, 0, 4000), 'preview_url' => false]], 'text');
    }

    /** Up to three quick-reply buttons. $buttons is [id => title]. */
    public function buttons(string $phone, string $body, array $buttons): void
    {
        $this->send($phone, ['type' => 'interactive', 'interactive' => [
            'type' => 'button',
            'body' => ['text' => mb_substr($body, 0, 1000)],
            'action' => ['buttons' => collect($buttons)->take(3)->map(fn ($title, $id) => ['type' => 'reply', 'reply' => ['id' => (string) $id, 'title' => mb_substr($title, 0, 20)]])->values()->all()],
        ]], 'buttons');
    }

    /** A list menu (up to 10 rows). $rows is [id => title]. */
    public function list(string $phone, string $body, string $button, array $rows): void
    {
        $this->send($phone, ['type' => 'interactive', 'interactive' => [
            'type' => 'list',
            'body' => ['text' => mb_substr($body, 0, 1000)],
            'action' => ['button' => mb_substr($button, 0, 20), 'sections' => [['title' => 'Options', 'rows' => collect($rows)->take(10)->map(fn ($title, $id) => ['id' => (string) $id, 'title' => mb_substr($title, 0, 24)])->values()->all()]]],
        ]], 'list');
    }

    public function template(string $phone, string $name, array $params): void
    {
        $this->send($phone, ['type' => 'template', 'template' => [
            'name' => $name, 'language' => ['code' => 'en'],
            'components' => [['type' => 'body', 'parameters' => collect($params)->map(fn ($p) => ['type' => 'text', 'text' => (string) $p])->all()]],
        ]], 'template');
    }

    /** Tell an author their idea was approved. Inside the 24h window send free text, otherwise the approved template. */
    public function notifyApproved(User $to, Idea $idea, ?string $note): void
    {
        if (! $to->phone || ! $to->whatsapp_opt_in) {
            return;
        }
        $session = WhatsappSession::where('phone', $to->phone)->first();
        $open = $session?->last_inbound_at && $session->last_inbound_at->gt(now()->subHours(24));
        if ($open) {
            $this->text($to->phone, "Good news {$to->first_name}: your idea {$idea->code} was approved.".($note ? "\nNote: {$note}" : ''));
        } else {
            $this->template($to->phone, config('ideas.whatsapp.templates.approved'), [$to->first_name, $idea->code, $note ?: 'No note']);
        }
    }

    private function send(string $phone, array $payload, string $kind): void
    {
        $cfg = config('ideas.whatsapp');
        $log = WhatsappMessage::create(['phone' => $phone, 'direction' => 'out', 'intent' => $kind, 'status' => 'queued']);
        if (! $cfg['token'] || ! $cfg['phone_number_id']) {
            Log::info("WhatsApp (not sent, no token) to {$phone}", $payload);
            $log->update(['status' => 'logged']);

            return;
        }
        $res = Http::withToken($cfg['token'])->post("https://graph.facebook.com/{$cfg['api_version']}/{$cfg['phone_number_id']}/messages", ['messaging_product' => 'whatsapp', 'to' => $phone, ...$payload]);
        $log->update(['status' => $res->successful() ? 'sent' : 'failed', 'wa_id' => $res->json('messages.0.id')]);
        if ($res->failed()) {
            Log::warning('WhatsApp send failed', ['status' => $res->status(), 'body' => $res->body()]);
        }
    }
}
