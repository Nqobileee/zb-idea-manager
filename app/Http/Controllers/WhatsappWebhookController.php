<?php

namespace App\Http\Controllers;

use App\Models\WhatsappMessage;
use App\Services\WhatsappBot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class WhatsappWebhookController extends Controller
{
    /** Meta calls this once with a challenge when you register the webhook. */
    public function verify(Request $request)
    {
        $ok = $request->query('hub_mode') === 'subscribe'
            && hash_equals((string) config('ideas.whatsapp.verify_token'), (string) $request->query('hub_verify_token'));

        return $ok ? response($request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain') : response('Forbidden', 403);
    }

    public function receive(Request $request, WhatsappBot $bot)
    {
        if (! $this->validSignature($request)) {
            return response('Invalid signature', 401);
        }

        foreach ($request->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                foreach ($value['statuses'] ?? [] as $st) {
                    WhatsappMessage::where('wa_id', $st['id'] ?? null)->update(['status' => $st['status'] ?? null]);
                }
                $names = collect($value['contacts'] ?? [])->mapWithKeys(fn ($c) => [($c['wa_id'] ?? '') => $c['profile']['name'] ?? null]);
                foreach ($value['messages'] ?? [] as $m) {
                    $phone = $m['from'] ?? null;
                    $id = $m['id'] ?? null;
                    if (! $phone || ($id && WhatsappMessage::where('wa_id', $id)->exists())) {
                        continue; // WhatsApp retries; ignore messages we already handled
                    }
                    if (RateLimiter::tooManyAttempts('wa:'.$phone, config('ideas.whatsapp.rate_per_minute'))) {
                        continue;
                    }
                    RateLimiter::hit('wa:'.$phone, 60);
                    WhatsappMessage::create(['wa_id' => $id, 'phone' => $phone, 'direction' => 'in', 'intent' => $m['type'] ?? null, 'status' => 'received']);

                    $text = $m['text']['body'] ?? null;
                    $reply = $m['interactive']['button_reply']['id'] ?? $m['interactive']['list_reply']['id'] ?? null;
                    $bot->handle($phone, $text, $reply, $names->get($phone));
                }
            }
        }

        return response('ok', 200);
    }

    private function validSignature(Request $request): bool
    {
        $secret = config('ideas.whatsapp.app_secret');
        if (! $secret) {
            return app()->environment('local', 'testing'); // allow unsigned calls only in dev
        }
        $given = (string) $request->header('X-Hub-Signature-256');

        return hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $secret), $given);
    }
}
