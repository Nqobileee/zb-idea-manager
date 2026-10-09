<?php

namespace App\Http\Controllers;

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
        $p = preg_replace('/\D/', '', (string) ($this->input($r, 'phone') ?? data_get($r->all(), 'contact.phone_number') ?? ''));

        return $p ?: null;
    }

    private function email(Request $r): ?string
    {
        $e = strtolower(trim((string) ($this->input($r, 'email') ?? $this->input($r, 'imEmail') ?? '')));

        return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : null;
    }

    /** The person behind the request: matched by email, then by WhatsApp number. */
    private function user(Request $r): ?User
    {
        $email = $this->email($r);
        $phone = $this->phone($r);

        return ($email ? User::where('email', $email)->first() : null) ?? ($phone ? User::where('phone', $phone)->first() : null);
    }

    private function reply(string $message, array $extra = [], bool $ok = true): JsonResponse
    {
        return response()->json(['ok' => $ok, 'message' => Str::limit($message, 3990, "\n…")] + $extra);
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

    public function link(Request $r): JsonResponse
    {
        $email = $this->email($r);
        $phone = $this->phone($r);
        if (! $email && ! $phone) {
            return $this->fail('I need an email address or a WhatsApp number to link your account.', 422);
        }
        $user = $this->user($r);
        if (! $user) {
            $user = User::create([
                'name' => trim((string) $this->input($r, 'name')) ?: 'Member',
                'email' => $email ?? $phone.'@'.PhoneAccounts::PLACEHOLDER_DOMAIN,
                'phone' => $phone,
                'is_admin' => false,
                'member_type' => stripos((string) $this->input($r, 'category'), 'employee') !== false ? 'employee' : 'hub_member',
                'joined' => (string) now()->year,
                'color' => '#049016',
            ]);
        } elseif ($phone && ! $user->phone) {
            User::where('phone', $phone)->where('id', '!=', $user->id)->update(['phone' => null]);
            $user->update(['phone' => $phone]);
        }

        return $this->reply("Linked, {$user->first_name}.", ['user_id' => $user->id, 'first_name' => $user->first_name, 'is_executive' => (bool) $user->is_admin]);
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

        return $this->reply("{$c->title}\nSet by ".($c->owner?->name ?? 'an executive').' · '.($c->deadline ? 'closes '.$c->deadline->format('j M Y') : 'no deadline')."\n{$c->ideas_count} ".Str::plural('idea', $c->ideas_count), ['challenge_id' => $c->id, 'is_executive' => (bool) $u->is_admin]);
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

        return $this->reply("Ready to post?\n\n{$d['title']}\n{$d['summary']}\nChallenge: {$ch}\nVisibility: ".ucfirst($d['visibility'])."\n\nPhotos and documents can be added on the web app.");
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

        return $this->reply("Your ideas\n\n".$ideas->map(fn ($i) => "{$i->code} ".$this->line($i).' · '.($i->is_public ? 'Public' : 'Private'))->implode("\n\n"));
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

        return $this->reply("{$idea->code} {$idea->title}\nby {$idea->author->name} · {$idea->status}".($idea->approved ? ', approved' : '')."\n{$idea->likers_count} likes · {$idea->comments_count} comments".($score !== null ? " · score {$score}/100" : '')."\n\n{$idea->summary}", ['idea_id' => $idea->id, 'approved' => (bool) $idea->approved, 'is_executive' => (bool) $u->is_admin]);
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
