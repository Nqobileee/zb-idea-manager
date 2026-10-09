<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Challenge;
use App\Models\Idea;
use App\Models\User;
use App\Services\IdeaActions;
use App\Services\PhoneAccounts;
use App\Services\Ranking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Backend for the Zernio "ZBIF Registration Chatbot". Zernio calls these endpoints; every reply is JSON with a
 * `message` already formatted for WhatsApp. Lists are numbered, and the number the person types comes back as a
 * reference such as `top:3`, which is resolved from the order we cached for that person (see rememberList).
 */
class ZernioController extends Controller
{
    public function __construct(private IdeaActions $actions, private Ranking $ranking, private PhoneAccounts $accounts) {}

    // ---- helpers ----------------------------------------------------------------

    /** Read a chat variable from wherever Zernio puts it: top level, `variables`, `vars`, `data`, or the contact's fields. */
    private function input(Request $r, string $key, mixed $default = null): mixed
    {
        foreach (['', 'variables.', 'vars.', 'data.', 'contact.fields.', 'contact.'] as $prefix) {
            $v = data_get($r->all(), $prefix.$key);
            if ($v !== null && $v !== '') {
                return $v;
            }
        }

        return $default;
    }

    private function phone(Request $r): ?string
    {
        $p = PhoneAccounts::normalize((string) ($this->input($r, 'phone') ?? data_get($r->all(), 'contact.phone_number') ?? ''));

        return $p ?: null;
    }

    private function email(Request $r): ?string
    {
        $e = strtolower(trim((string) ($this->input($r, 'email') ?? $this->input($r, 'imEmail') ?? '')));

        return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : null;
    }

    /**
     * The person behind the request. The WhatsApp number is verified by Meta, so it identifies the account. The email
     * typed into the chat is not verified, so it is only a fallback when Zernio sends no number at all.
     */
    private function user(Request $r): ?User
    {
        $phone = $this->phone($r);
        if ($phone) {
            return User::where('phone', $phone)->first();
        }
        $email = $this->email($r);

        return $email ? User::where('email', $email)->first() : null;
    }

    private function reply(string $message, array $extra = [], bool $ok = true): JsonResponse
    {
        return response()->json(['ok' => $ok, 'message' => Str::limit($message, 4090, "\n…")] + $extra);
    }

    private function fail(string $message, int $status = 200): JsonResponse
    {
        return response()->json(['ok' => false, 'message' => $message], $status);
    }

    private function unknownUser(): JsonResponse
    {
        return $this->fail('I could not find your Idea Manager account yet. Please finish registration first.', 404);
    }

    private function onlyExecutives(): JsonResponse
    {
        return $this->fail('That is only available to executives.', 403);
    }

    /** Remember the order of a numbered list for this person, so "3" can be turned back into an id. */
    private function rememberList(User $u, string $name, array $ids): void
    {
        Cache::put("zernio:{$u->id}:{$name}", array_values($ids), now()->addMinutes(30));
    }

    /** Turn `name:3` (position) or `id:42` into an id. Returns null when it cannot be resolved. */
    private function resolve(User $u, ?string $ref): ?int
    {
        if (! $ref || ! str_contains($ref, ':')) {
            return ctype_digit((string) $ref) ? (int) $ref : null;
        }
        [$kind, $n] = array_pad(explode(':', trim($ref), 2), 2, null);
        if (! ctype_digit((string) $n)) {
            return null;
        }
        if ($kind === 'id') {
            return (int) $n;
        }

        return Cache::get("zernio:{$u->id}:{$kind}", [])[(int) $n - 1] ?? null;
    }

    private function idea(User $u, ?string $ref): ?Idea
    {
        $id = $this->resolve($u, $ref);

        return $id ? Idea::visibleTo($u)->feed()->find($id) : null;
    }

    private function line(Idea $i): string
    {
        return "{$i->title}\n{$i->status}".($i->approved ? ', approved' : '').' · '.$i->likers_count.' likes · '.$i->comments_count.' comments';
    }

    private function openChallenges()
    {
        return Challenge::where(fn ($q) => $q->whereNull('deadline')->orWhere('deadline', '>=', now()->toDateString()))->orderBy('deadline')->orderBy('id')->take(8)->get();
    }

    // ---- account ------------------------------------------------------------------

    /**
     * The first call of a conversation. The WhatsApp number identifies the person; an unknown number becomes a General
     * member straight away (no email, a temporary password). `is_new` is true until the welcome has been shown once.
     */
    public function link(Request $r): JsonResponse
    {
        $phone = $this->phone($r);
        if (! $phone) {
            return $this->fail('I need your WhatsApp number to set up your account.', 422);
        }
        $user = User::where('phone', $phone)->first() ?? $this->accounts->createMember($phone, (string) ($this->input($r, 'name') ?? data_get($r->all(), 'contact.name')));

        $isNew = false;
        $temp = '';
        if (PhoneAccounts::phoneOnly() && $user->wa_welcomed_at === null) {
            $isNew = true;
            if ($user->must_change_password) {
                // only a hash is stored, so a fresh temporary password is issued for the welcome
                $temp = $this->accounts->tempPassword();
                $user->password = Hash::make($temp);
            }
            $user->wa_welcomed_at = now();
            $user->save();
        }

        $this->recordLink($phone, $user);

        return $this->reply("Linked, {$user->first_name}.", [
            'user_id' => $user->id, 'first_name' => $user->first_name, 'is_executive' => (bool) $user->is_admin,
            'is_new' => $isNew, 'phone' => PhoneAccounts::display($phone), 'temp_password' => $temp, 'portal_url' => route('login'),
        ] + $this->greetingFields($user));
    }

    /** Optional: link an email address to the caller's account. */
    public function accountEmail(Request $r): JsonResponse
    {
        $phone = $this->phone($r);
        $user = $phone ? User::where('phone', $phone)->first() : null;
        if (! $user) {
            return $this->fail('Please say hi first so I can set up your account.');
        }
        $email = strtolower(trim((string) $this->input($r, 'leEmail', '')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->fail('That does not look like a valid email address.');
        }
        if (User::where('email', $email)->where('id', '!=', $user->id)->exists()) {
            return $this->fail('That email is already linked to another account. Please use a different email, or contact the Smile Factory team.');
        }
        $user->update(['email' => $email]);

        return $this->reply("Your email {$email} is now linked. You can also sign in with it on the website.");
    }

    /** Typing the executive access code in WhatsApp upgrades the account to Executive admin. Anything else is ignored. */
    public function accountCode(Request $r): JsonResponse
    {
        $secret = (string) config('ideas.executive_code');
        $phone = $this->phone($r);
        $text = trim((string) $this->input($r, 'switchCode', ''));
        $ignore = fn () => response()->json(['ok' => false]);
        if ($secret === '' || ! $phone || $text === '') {
            return $ignore();
        }
        // only text that looks like a code attempt can count against the lockout, so ordinary words never lock anyone out
        $looksLikeCode = Str::startsWith(Str::lower($text), 'zb-') || (mb_strlen($text) === mb_strlen($secret) && str_contains($text, '-') === str_contains($secret, '-'));
        $key = 'zernio-code:'.$phone;
        if ($looksLikeCode && RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['ok' => false, 'locked' => true, 'message' => 'Too many wrong codes. Please try again in 15 minutes.']);
        }
        if (! hash_equals(Str::lower($secret), Str::lower($text))) {
            if ($looksLikeCode) {
                RateLimiter::hit($key, 900);
            }

            return $ignore();
        }
        RateLimiter::clear($key);
        $user = User::where('phone', $phone)->first() ?? $this->accounts->createMember($phone, (string) ($this->input($r, 'name') ?? data_get($r->all(), 'contact.name')));
        if ($user->is_admin) {
            return $this->reply('You already have Executive admin access.', ['elevated' => true]);
        }
        $user->update(['is_admin' => true]);
        \Log::notice('Executive admin granted from WhatsApp', ['user_id' => $user->id, 'phone' => $phone, 'at' => now()->toDateTimeString()]);

        return $this->reply("Welcome, {$user->first_name}. You now have Executive admin access: you can post challenges, approve ideas and see reports and AI insights.", ['elevated' => true]);
    }

    /** The greeting fields every sign-in style reply carries, with a plain fallback so the bot's menu text is never empty. */
    private function greetingFields(User $u): array
    {
        [$greeting, $greeted] = $this->greeting($u);

        return ['greeting' => $greeting, 'greeted_today' => $greeted, 'menu_text' => $greeting !== '' ? $greeting : 'What would you like to do?'];
    }

    /** A time-aware greeting on the first call of the day (Africa/Harare), nothing after that. Returns [text, alreadyGreeted]. */
    private function greeting(User $u): array
    {
        if (! Schema::hasColumn('users', 'wa_greeted_on')) {
            return ['', false];
        }
        $now = now('Africa/Harare');
        $today = $now->toDateString();
        if ($u->wa_greeted_on?->toDateString() === $today) {
            return ['', true];
        }
        $part = $now->hour < 12 ? 'morning' : ($now->hour < 17 ? 'afternoon' : 'evening');
        $u->forceFill(['wa_greeted_on' => $today])->save();

        return ["Good {$part}, {$u->first_name}. What would you like to do today?", false];
    }

    public function webLink(Request $r): JsonResponse
    {
        $u = $this->user($r);

        return $u ? $this->reply("Open the web app (link works once, for 10 minutes):\n".$this->accounts->loginLink($u)) : $this->unknownUser();
    }

    public function notifications(Request $r): JsonResponse
    {
        $u = $this->user($r);
        if (! $u) {
            return $this->unknownUser();
        }
        $off = in_array(Str::lower(trim((string) $this->input($r, 'menuReply'))), ['stop', 'stop notifications'], true);
        $u->update(['whatsapp_opt_in' => ! $off]);

        return $this->reply($off ? 'Notifications are off. Send "start notifications" to turn them back on.' : 'Notifications are on.');
    }

    // ---- challenges -----------------------------------------------------------------

    public function challengeOptions(Request $r): JsonResponse
    {
        $u = $this->user($r);
        if (! $u) {
            return $this->unknownUser();
        }
        $list = $this->openChallenges();
        $this->rememberList($u, 'options', $list->pluck('id')->all());

        return $this->reply("Which challenge does it answer? Reply with a number.\n0. None\n".$list->values()->map(fn ($c, $n) => ($n + 1).". {$c->title}")->implode("\n"));
    }

    public function challenges(Request $r): JsonResponse
    {
        $u = $this->user($r);
        if (! $u) {
            return $this->unknownUser();
        }
        $list = $this->openChallenges();
        $this->rememberList($u, 'list', $list->pluck('id')->all());
        if ($list->isEmpty()) {
            return $this->reply('No open challenges right now.');
        }

        return $this->reply("Open challenges. Reply with a number.\n".$list->values()->map(fn ($c, $n) => ($n + 1).". {$c->title} · ".($c->deadline ? 'closes '.$c->deadline->format('j M') : 'no deadline'))->implode("\n"));
    }

    public function challengeDetail(Request $r): JsonResponse
    {
        $u = $this->user($r);
        if (! $u) {
            return $this->unknownUser();
        }
        $id = $this->resolve($u, (string) $this->input($r, 'chRef'));
        $c = $id ? Challenge::with('owner')->withCount(['ideas' => fn ($i) => $i->visibleTo($u)])->find($id) : null;
        if (! $c) {
            return $this->fail('I could not find that challenge. Pick one from the list.');
        }

        $head = "{$c->title}\nSet by ".($c->owner?->name ?? 'an executive').' · '.($c->deadline ? 'closes '.$c->deadline->format('j M Y') : 'no deadline')."\n{$c->ideas_count} ".Str::plural('idea', $c->ideas_count);
        $link = 'View on the web: '.route('challenges.show', $c);

        [$text, $more] = $this->withLink($head."\n\nBrief\n", (string) $c->brief, $link);

        return $this->reply($text, ['challenge_id' => $c->id, 'is_executive' => (bool) $u->is_admin] + ($more ? ['message_more' => $more] : []));
    }

    /**
     * Head + body + link, split over two messages when the text is longer than one WhatsApp message.
     * Returns [message, more|null]. When there is no overflow the link is the last line of `message`.
     * With overflow, `message` ends "(continued)" and the rest of the text plus the link is in `message_more`.
     */
    private function withLink(string $head, string $body, string $link): array
    {
        $body = trim($body);
        $limit = 4000;
        $room = $limit - mb_strlen($head) - mb_strlen($link) - 2;
        if (mb_strlen($body) <= $room) {
            return [$head.$body."\n\n".$link, null];
        }
        $first = mb_substr($body, 0, max(0, $limit - mb_strlen($head) - 20));
        $cut = max(mb_strrpos($first, "\n") ?: 0, mb_strrpos($first, ' ') ?: 0) ?: mb_strlen($first); // break between words
        $first = rtrim(mb_substr($body, 0, $cut));
        $rest = ltrim(mb_substr($body, $cut));
        $moreRoom = $limit - mb_strlen($link) - 2;
        if (mb_strlen($rest) > $moreRoom) {
            $rest = rtrim(mb_substr($rest, 0, max(0, $moreRoom - 40))).'… (the rest is on the web)';
        }

        return [$head.$first."\n\n(continued)", $rest."\n\n".$link];
    }

    public function challengeIdeas(Request $r): JsonResponse
    {
        $u = $this->user($r);
        if (! $u) {
            return $this->unknownUser();
        }
        if (! $u->is_admin) {
            return $this->onlyExecutives();
        }
        $id = (int) ($this->input($r, 'chDetail.body.challenge_id') ?? $this->input($r, 'challenge_id') ?? $this->resolve($u, (string) $this->input($r, 'chRef')));
        $c = Challenge::find($id);
        if (! $c) {
            return $this->fail('I could not find that challenge.');
        }
        $ideas = Idea::feed()->where('challenge_id', $c->id)->orderByDesc('likers_count')->latest()->take(10)->get();
        $total = Idea::where('challenge_id', $c->id)->count();
        $this->rememberList($u, 'chideas', $ideas->pluck('id')->all());
        if ($ideas->isEmpty()) {
            return $this->reply("{$c->title}\nNo ideas yet.");
        }
        $text = "{$c->title} · ideas by likes. Reply with a number.\n".$ideas->values()->map(fn ($i, $n) => ($n + 1).". {$i->title} · {$i->likers_count} ♥ · {$i->author->name}".($i->approved ? ' · approved' : ''))->implode("\n");
        if ($total > 10) {
            $text .= "\n\nShowing 10 of {$total}. See all: ".route('challenges.show', $c);
        }

        return $this->reply($text);
    }

    // ---- ideas ----------------------------------------------------------------------

    /** Validate the draft and work out its fields. Returns [data|null, errorMessage|null]. */
    private function draft(Request $r, User $u): array
    {
        $title = trim((string) $this->input($r, 'ideaTitle'));
        $summary = trim((string) $this->input($r, 'ideaSummary'));
        if ($title === '' || mb_strlen($title) > 90) {
            return [null, 'The title must be between 1 and 90 characters. Please send a shorter title.'];
        }
        if ($summary === '' || mb_strlen($summary) > 300) {
            return [null, 'The summary must be between 1 and 300 characters. Please shorten it.'];
        }
        $ref = trim((string) $this->input($r, 'ideaChallengeRef', '0'));
        $challengeId = $ref === '' || $ref === '0' || $ref === 'options:0' ? null : $this->resolve($u, $ref);
        $challengeId = $challengeId && Challenge::whereKey($challengeId)->exists() ? $challengeId : null;

        return [[
            'title' => $title, 'summary' => $summary, 'body' => trim((string) $this->input($r, 'ideaDetails')) ?: $summary,
            'challenge_id' => $challengeId,
            'visibility' => Str::contains(Str::lower((string) $this->input($r, 'ideaVisibility')), 'public') ? 'public' : 'private',
        ], null];
    }

    public function previewIdea(Request $r): JsonResponse
    {
        $u = $this->user($r);
        if (! $u) {
            return $this->unknownUser();
        }
        [$d, $error] = $this->draft($r, $u);
        if (! $d) {
            return $this->reply($error, [], false);
        }
        $ch = $d['challenge_id'] ? Challenge::find($d['challenge_id'])?->title : 'None';

        $details = $d['body'] !== $d['summary'] ? "\n\nDetails\n".Str::limit($d['body'], 2500) : '';

        return $this->reply("Ready to post?\n\n{$d['title']}\n\nSummary\n{$d['summary']}{$details}\n\nChallenge: {$ch}\nVisibility: ".ucfirst($d['visibility'])."\n\nPhotos and documents can be added on the web app.");
    }

    public function createIdea(Request $r): JsonResponse
    {
        $u = $this->user($r);
        if (! $u) {
            return $this->unknownUser();
        }
        [$d, $error] = $this->draft($r, $u);
        if (! $d) {
            return $this->reply($error, [], false);
        }
        $idea = $this->actions->create($u, $d, 'whatsapp');

        return $this->reply("Posted as {$idea->code}. You will be told here when someone responds.\n".route('ideas.show', $idea), ['idea_id' => $idea->id, 'code' => $idea->code]);
    }

    public function myIdeas(Request $r): JsonResponse
    {
        $u = $this->user($r);
        if (! $u) {
            return $this->unknownUser();
        }
        $ideas = Idea::feed()->where('user_id', $u->id)->latest()->take(8)->get();
        if ($ideas->isEmpty()) {
            return $this->reply('You have not posted an idea yet.');
        }

        $this->rememberList($u, 'mine', $ideas->pluck('id')->all());

        return $this->reply("Your ideas. Reply with a number to open one.\n\n".$ideas->values()->map(fn ($i, $n) => ($n + 1).". {$i->code} ".$this->line($i).' · '.($i->is_public ? 'Public' : 'Private'))->implode("\n\n"));
    }

    private function visibleRanked(User $u)
    {
        return $this->ranking->rank(null)->filter(fn ($x) => $x['idea']->isVisibleTo($u))->values();
    }

    public function topIdeas(Request $r): JsonResponse
    {
        $u = $this->user($r);
        if (! $u) {
            return $this->unknownUser();
        }
        $top = $this->visibleRanked($u)->take(5);
        if ($top->isEmpty()) {
            return $this->reply('There are no ideas to show yet.');
        }
        $this->rememberList($u, 'top', $top->map(fn ($x) => $x['idea']->id)->all());

        return $this->reply("Top ideas. Reply with a number to open one.\n".$top->values()->map(fn ($x, $n) => ($n + 1).". {$x['idea']->title}\n   by {$x['idea']->author->name} · {$x['idea']->likers_count} ♥")->implode("\n"));
    }

    public function topFive(Request $r): JsonResponse
    {
        $u = $this->user($r);
        if (! $u) {
            return $this->unknownUser();
        }
        if (! $u->is_admin) {
            return $this->onlyExecutives();
        }
        $top = $this->ranking->rank(null)->take(5);
        $this->rememberList($u, 'top5', $top->map(fn ($x) => $x['idea']->id)->all());

        return $this->reply($top->isEmpty() ? 'There are no ideas yet.' : "Top 5 by score. Reply with a number.\n".$top->values()->map(fn ($x, $n) => ($n + 1).". {$x['idea']->title} ({$x['total']}/100)\n   by {$x['idea']->author->name}".($x['idea']->approved ? ' · already approved' : ''))->implode("\n"));
    }

    public function ideaDetail(Request $r): JsonResponse
    {
        $u = $this->user($r);
        if (! $u) {
            return $this->unknownUser();
        }
        $idea = $this->idea($u, (string) $this->input($r, 'ideaRef'));
        if (! $idea) {
            return $this->fail('I could not find that idea. Pick one from the list.');
        }
        $score = $this->ranking->rank(null)->first(fn ($x) => $x['idea']->id === $idea->id)['total'] ?? null;
        $head = "{$idea->code} {$idea->title}\nBy {$idea->author->name} · {$idea->status} · ".($idea->is_public ? 'Public' : 'Private')
            ."\n{$idea->likers_count} likes · {$idea->comments_count} comments".($score !== null ? " · Score {$score}/100" : '')
            .($idea->challenge ? "\nChallenge: {$idea->challenge->title}" : '');
        // an idea posted without separate details has the summary as its body: show it once, as the full text
        if ($idea->body === $idea->summary || trim((string) $idea->body) === '') {
            $head .= "\n\n";
            $body = (string) $idea->summary;
        } else {
            $head .= "\n\nSummary\n{$idea->summary}\n\nDetails\n";
            $body = (string) $idea->body;
        }

        [$text, $more] = $this->withLink($head, $body, 'View on the web: '.route('ideas.show', $idea));

        return $this->reply($text, ['idea_id' => $idea->id, 'approved' => (bool) $idea->approved, 'is_executive' => (bool) $u->is_admin] + ($more ? ['message_more' => $more] : []));
    }

    public function like(Request $r): JsonResponse
    {
        $u = $this->user($r);
        if (! $u) {
            return $this->unknownUser();
        }
        $idea = $this->idea($u, (string) $this->input($r, 'ideaRef'));
        if (! $idea) {
            return $this->fail('I could not find that idea.');
        }
        $liked = $this->actions->toggleLike($idea, $u);

        return $this->reply(($liked ? 'Liked ' : 'Like removed from ')."{$idea->code}.", ['liked' => $liked]);
    }

    public function approve(Request $r): JsonResponse
    {
        $u = $this->user($r);
        if (! $u) {
            return $this->unknownUser();
        }
        if (! $u->is_admin) {
            return $this->onlyExecutives();
        }
        $idea = $this->idea($u, (string) $this->input($r, 'ideaRef'));
        if (! $idea) {
            return $this->fail('I could not find that idea.');
        }
        $note = trim((string) $this->input($r, 'approveNote'));
        $this->actions->approve($idea, $u, $note === '' || Str::lower($note) === 'skip' ? null : $note);

        return $this->reply("Approved {$idea->code}. The author has been told.");
    }

    // ---- sign-in and registration (the bot asks for the email first) ------------------------
    // Never log these request bodies and never store the plain password: only a hash is kept.

    private function regEmail(Request $r): ?string
    {
        $e = strtolower(trim((string) $this->input($r, 'regEmail', '')));

        return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : null;
    }

    /** Remember that this number has signed in to this account (a successful sign-in, registration or /link only). */
    private function recordLink(?string $phone, User $u): void
    {
        if ($phone && Schema::hasTable('whatsapp_account_links')) {
            \DB::table('whatsapp_account_links')->upsert([['phone' => $phone, 'user_id' => $u->id, 'last_used_at' => now()]], ['phone', 'user_id'], ['last_used_at']);
        }
    }

    /** "p•••a@gmail.com": first letter, last letter of the name, and the domain. */
    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $mask = mb_strlen($name) <= 2 ? mb_substr($name, 0, 1).'•••' : mb_substr($name, 0, 1).'•••'.mb_substr($name, -1);

        return $mask.'@'.$domain;
    }

    /** After a correct password, this WhatsApp number belongs to that account: it is taken off any other account first. */
    private function attachPhone(User $u, ?string $phone): void
    {
        if ($phone && $u->phone !== $phone) {
            User::where('phone', $phone)->where('id', '!=', $u->id)->update(['phone' => null]);
            $u->update(['phone' => $phone]);
        }
        $this->recordLink($phone, $u);
    }

    /** The person types the switch code in WhatsApp: unlink this number so sign-in starts again from the email question. */
    public function accountSwitch(Request $r): JsonResponse
    {
        $secret = (string) config('ideas.switch_code');
        $phone = $this->phone($r);
        if ($secret === '' || ! $phone) {
            return $this->fail('Switching accounts is not available.', $secret === '' ? 200 : 422);
        }
        $key = 'zernio-switch:'.$phone;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['ok' => false, 'locked' => true, 'message' => 'Too many tries. Please wait 15 minutes.']);
        }
        $given = trim((string) $this->input($r, 'switchCode', ''));
        if ($given === '' || ! hash_equals(Str::lower($secret), Str::lower($given))) {
            RateLimiter::hit($key, 900);

            return $this->fail('That is not the switch code.');
        }
        RateLimiter::clear($key);
        $was = User::where('phone', $phone)->first();
        if ($was) {
            $this->recordLink($phone, $was); // the account being left must be on the list
        }
        User::where('phone', $phone)->update(['phone' => null]);

        $ids = $this->linkedAccountIds($phone);
        if ($ids === []) {
            return $this->reply('Switched'.($was ? " (you were signed in as {$was->first_name})" : '').'. Let us sign in again. What is your email address?', ['restart' => true, 'was_signed_in' => (bool) $was, 'accounts' => 0]);
        }
        Cache::put('zernio:phone-'.$phone.':accounts', $ids, now()->addMinutes(30)); // per number: nobody is signed in at this point
        $users = User::whereIn('id', $ids)->get()->keyBy('id');
        $lines = [];
        foreach ($ids as $n => $id) {
            $a = $users[$id];
            $lines[] = ($n + 1).". {$a->name} · ".($a->is_admin ? 'Executive admin' : 'General Member').' · '.$this->maskEmail($a->email);
        }
        $lines[] = (count($ids) + 1).'. Create a new account';

        return $this->reply("Switched. Which account would you like to use?\n".implode("\n", $lines)."\nReply with a number, or send an email address.", ['restart' => true, 'was_signed_in' => (bool) $was, 'accounts' => count($ids)]);
    }

    /** Accounts this number has signed in to, newest first. */
    private function linkedAccountIds(string $phone): array
    {
        if (! Schema::hasTable('whatsapp_account_links')) {
            return [];
        }

        return \DB::table('whatsapp_account_links')->join('users', 'users.id', '=', 'whatsapp_account_links.user_id')
            ->where('whatsapp_account_links.phone', $phone)->orderByDesc('whatsapp_account_links.last_used_at')->orderByDesc('whatsapp_account_links.id')
            ->pluck('whatsapp_account_links.user_id')->map(fn ($i) => (int) $i)->all();
    }

    public function accountSwitchPick(Request $r): JsonResponse
    {
        $phone = $this->phone($r);
        $pick = fn (string $message, array $extra = [], bool $ok = true) => response()->json(['ok' => $ok, 'message' => $message] + $extra);
        $bad = fn () => $pick('Please pick a number from the list.', [], false);
        if (! $phone) {
            return $bad();
        }
        $ref = trim((string) $this->input($r, 'accountRef', ''));
        if (! preg_match('/^accounts:(\d+)$/', $ref, $m)) {
            return $bad();
        }
        $n = (int) $m[1];
        $ids = Cache::get('zernio:phone-'.$phone.':accounts');
        if (! is_array($ids) || $n < 1 || $n > count($ids) + 1) {
            return $bad();
        }
        if ($n === count($ids) + 1) {
            return $pick("Let's create a new account.", ['action' => 'new']);
        }
        $user = in_array($ids[$n - 1], $this->linkedAccountIds($phone), true) ? User::find($ids[$n - 1]) : null;
        if (! $user) {
            return $bad();
        }

        // Nothing is signed in here: the bot sends the password to /account/login next.
        return $pick('Please enter the password for '.$user->name.' ('.($user->is_admin ? 'Executive admin' : 'General Member').').', ['action' => 'login', 'email' => $user->email]);
    }

    public function accountCheck(Request $r): JsonResponse
    {
        $email = $this->regEmail($r);
        if (! $email) {
            return $this->fail('That does not look like an email address. Please send it again.', 422);
        }
        $user = User::where('email', $email)->first();

        return $this->reply($user ? "Welcome back, {$user->first_name}." : 'No account found for that email yet.', ['exists' => (bool) $user] + ($user ? ['first_name' => $user->first_name] : []));
    }

    public function accountLogin(Request $r): JsonResponse
    {
        $email = $this->regEmail($r);
        $phone = $this->phone($r);
        $password = (string) $this->input($r, 'regPassword', '');
        if (! $email || $password === '') {
            return $this->fail('I need your email and password.', 422);
        }
        $reset = route('login');
        $key = 'zernio-login:'.$email.'|'.$phone;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['ok' => false, 'locked' => true, 'message' => 'Too many wrong passwords. Sign-in is locked for 15 minutes. You can also use the web sign-in page: '.$reset]);
        }
        $user = User::where('email', $email)->first();
        if (! $user || ! $user->password || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($key, 900);

            return $this->fail("That password is not correct. Please try again, or sign in on the web: {$reset}");
        }
        RateLimiter::clear($key);
        $this->attachPhone($user, $phone);
        $temporary = $password === (string) config('ideas.default_password') && Schema::hasColumn('users', 'must_change_password');
        if ($temporary) {
            $user->forceFill(['must_change_password' => true])->save();
        }

        return $this->reply("You are signed in, {$user->first_name}.".($temporary ? ' That is a temporary password: please choose your own when you sign in at '.route('login').'.' : ''), ['full_name' => $user->name, 'first_name' => $user->first_name, 'role' => $user->is_admin ? 'executive' : 'general', 'user_id' => $user->id, 'must_change_password' => $temporary] + $this->greetingFields($user));
    }

    public function accountRegister(Request $r): JsonResponse
    {
        $name = trim((string) $this->input($r, 'regName', ''));
        $email = $this->regEmail($r);
        $password = (string) $this->input($r, 'regPassword', '');
        $phone = $this->phone($r);
        $wantsExecutive = Str::contains(Str::lower((string) $this->input($r, 'regRole', '')), 'exec');

        if (mb_strlen($name) < 2) {
            return $this->fail('Please send your full name.');
        }
        if (! $email) {
            return $this->fail('That does not look like an email address.');
        }
        if (mb_strlen($password) < 8) {
            return $this->fail('Your password must be at least 8 characters.');
        }
        if (User::where('email', $email)->exists()) {
            return $this->fail('That email is already registered. Please sign in instead.');
        }
        if ($phone && User::where('phone', $phone)->exists()) {
            return $this->fail('This WhatsApp number is already linked to an account.');
        }

        if ($wantsExecutive) {
            $secret = (string) config('ideas.executive_code');
            $key = 'zernio-exec:'.$email.'|'.$phone;
            if ($secret === '') {
                return $this->fail('Executive admin registration is not open. You can register as a General Member instead.');
            }
            if (RateLimiter::tooManyAttempts($key, 5)) {
                return response()->json(['ok' => false, 'locked' => true, 'message' => 'Too many wrong codes. Please try again in 15 minutes, or register as a General Member.']);
            }
            $given = trim((string) $this->input($r, 'regExecCode', ''));
            if ($given === '') {
                return response()->json(['ok' => false, 'code_required' => true, 'message' => 'Executive admin needs the executive access code. Please send it.']);
            }
            if (! hash_equals($secret, $given)) {
                RateLimiter::hit($key, 900);

                return response()->json(['ok' => false, 'code_required' => true, 'message' => 'That executive access code is not right.']);
            }
            RateLimiter::clear($key);
        }

        $user = User::create([
            'name' => $name, 'email' => $email, 'phone' => $phone, 'password' => Hash::make($password),
            'is_admin' => $wantsExecutive, 'member_type' => 'general',
            'joined' => (string) now()->year, 'color' => '#049016',
        ]);

        $this->recordLink($phone, $user);

        return $this->reply('You are registered as '.($wantsExecutive ? 'an Executive admin' : 'a General Member').'. Use this email and password to sign in at '.route('login').'.',
            ['role' => $wantsExecutive ? 'executive' : 'general', 'user_id' => $user->id, 'first_name' => $user->first_name] + $this->greetingFields($user));
    }

    // ---- pipeline and alerts -------------------------------------------------------------

    public function pipeline(Request $r): JsonResponse
    {
        $u = $this->user($r);
        if (! $u) {
            return $this->unknownUser();
        }
        $all = Idea::feed()->visibleTo($u)->orderByDesc('likers_count')->latest()->get()->groupBy('status');
        $n = 0;
        $ids = [];
        $out = ['Pipeline', ''];
        $hidden = false;
        foreach (Idea::STATUSES as $stage) {
            $rows = $all->get($stage, collect());
            $out[] = "{$stage} ({$rows->count()})";
            foreach ($rows->take(3) as $i) {
                $out[] = (++$n).". {$i->title} · {$i->likers_count} likes";
                $ids[] = $i->id;
            }
            $hidden = $hidden || $rows->count() > 3;
        }
        $this->rememberList($u, 'pipeline', $ids);
        if ($hidden) {
            $out[] = '';
            $out[] = 'Full pipeline: '.route('pipeline');
        }

        return $this->reply(implode("\n", $out));
    }

    public function notificationList(Request $r): JsonResponse
    {
        $u = $this->user($r);
        if (! $u) {
            return $this->unknownUser();
        }
        $labels = ['like' => 'liked', 'comment' => 'commented on', 'approval' => 'approved', 'stage' => 'moved'];
        $items = Activity::with(['actor', 'idea', 'challenge'])->where('user_id', $u->id)->latest()->latest('id')->take(5)->get()->map(function (Activity $a) use ($labels) {
            $who = $a->actor?->name ?? 'Someone';
            $text = match ($a->type) {
                'challenge' => "{$who} set a new challenge: ".($a->challenge?->title ?? ''),
                'approval' => 'Your idea '.($a->idea?->title ?? '').' was approved',
                'stage' => ($a->idea?->title ?? 'Your idea').' moved to a new stage',
                default => "{$who} ".($labels[$a->type] ?? $a->type).' your idea '.($a->idea?->title ?? ''),
            };

            return '• '.trim($text).' · '.$a->created_at->timezone('Africa/Harare')->format('j M');
        });
        $enabled = (bool) $u->whatsapp_opt_in;

        return $this->reply('WhatsApp alerts are '.($enabled ? 'on' : 'off').".\n\n".($items->isEmpty() ? 'No activity yet.' : "Latest activity\n".$items->implode("\n")), ['enabled' => $enabled]);
    }

    // ---- reports (executives) ----------------------------------------------------------

    public function reports(Request $r): JsonResponse
    {
        $u = $this->user($r);
        if (! $u) {
            return $this->unknownUser();
        }
        if (! $u->is_admin) {
            return $this->onlyExecutives();
        }
        $choice = Str::lower(trim((string) $this->input($r, 'reportChoice')));

        if (Str::contains($choice, 'top 10')) {
            $rows = Idea::feed()->orderByDesc('likers_count')->latest()->take(10)->get();

            return $this->reply("Top 10 by likes\n".($rows->isEmpty() ? 'No ideas yet.' : $rows->values()->map(fn ($i, $n) => ($n + 1).". {$i->title} · {$i->likers_count} ♥ · {$i->author->name}")->implode("\n")));
        }
        if (Str::contains($choice, 'awaiting')) {
            $rows = Idea::feed()->where('approved', false)->latest()->take(10)->get();

            return $this->reply("Awaiting approval ({$rows->count()}".(Idea::where('approved', false)->count() > 10 ? '+' : '').")\n".($rows->isEmpty() ? 'Nothing is waiting.' : $rows->values()->map(fn ($i, $n) => ($n + 1).". {$i->title} · {$i->likers_count} ♥ · {$i->author->name}")->implode("\n")));
        }
        if (Str::contains($choice, 'stage')) {
            return $this->reply("Ideas by stage\n".collect(Idea::STATUSES)->map(fn ($s) => "{$s}: ".Idea::where('status', $s)->count())->implode("\n"));
        }
        if (Str::contains($choice, 'challenge')) {
            $rows = Challenge::withCount('ideas')->orderByDesc('ideas_count')->get()->map(function ($c) {
                $likes = (int) \DB::table('idea_likes')->join('ideas', 'ideas.id', '=', 'idea_likes.idea_id')->where('ideas.challenge_id', $c->id)->count();

                return "{$c->title}: {$c->ideas_count} ".Str::plural('idea', $c->ideas_count).", {$likes} ♥";
            });

            return $this->reply("By challenge\n".($rows->isEmpty() ? 'No challenges yet.' : $rows->implode("\n")));
        }

        // Programme summary is the default
        $likes = (int) \DB::table('idea_likes')->count();

        return $this->reply(implode("\n", [
            'Programme summary',
            'Ideas: '.Idea::count(),
            'Approved: '.Idea::where('approved', true)->count(),
            "Likes: {$likes}",
            'Comments: '.\DB::table('comments')->count(),
            'New this week: '.Idea::where('created_at', '>=', now()->subDays(7))->count(),
            'From WhatsApp: '.Idea::where('source', 'whatsapp')->count(),
            'Members: '.User::count(),
        ]));
    }
}
