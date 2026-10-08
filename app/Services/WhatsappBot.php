<?php

namespace App\Services;

use App\Models\Challenge;
use App\Models\Idea;
use App\Models\User;
use App\Models\WhatsappSession;
use Illuminate\Support\Str;

/**
 * Conversation logic for the WhatsApp assistant. One row in whatsapp_sessions holds the state per phone number.
 * Input is plain text or the id of a tapped button/list row. Output goes through WhatsappClient.
 */
class WhatsappBot
{
    public function __construct(
        private WhatsappClient $wa,
        private AuthCodes $codes,
        private IdeaActions $actions,
        private Ranking $ranking,
    ) {}

    public function handle(string $phone, ?string $text, ?string $reply = null): void
    {
        $s = WhatsappSession::firstOrCreate(['phone' => $phone]);
        if ($s->last_inbound_at && $s->last_inbound_at->lt(now()->subMinutes(config('ideas.whatsapp.session_minutes'))) && $s->state !== 'idle') {
            $s->state = 'idle'; // draft is kept so the user can say "continue"
        }
        $s->last_inbound_at = now();
        $s->save();

        $input = trim((string) ($reply ?? $text));
        $cmd = Str::lower($input);

        if ($s->locked_until && $s->locked_until->isFuture()) {
            $this->wa->text($phone, 'Too many wrong codes. Please try again in a few minutes.');

            return;
        }
        if (in_array($cmd, ['cancel', 'menu', 'hi', 'hello', 'start'], true) && $s->state !== 'link_email' && $s->state !== 'link_code') {
            $this->reset($s, keepDraft: $cmd !== 'cancel');
            if ($cmd === 'cancel') {
                $s->update(['draft' => null]);
                $this->wa->text($phone, 'Cancelled.');
            }
            if (! $s->user_id) {
                $this->startLink($s);

                return;
            }
            $this->menu($s);

            return;
        }
        if (! $s->user_id) {
            $this->linking($s, $input);

            return;
        }
        $user = $s->user;

        if (in_array($cmd, ['stop', 'stop notifications'], true)) {
            $user->update(['whatsapp_opt_in' => false]);
            $this->wa->text($phone, 'Notifications are off. Send "start notifications" to turn them back on.');

            return;
        }
        if ($cmd === 'start notifications') {
            $user->update(['whatsapp_opt_in' => true]);
            $this->wa->text($phone, 'Notifications are on.');

            return;
        }
        if ($cmd === 'unlink') {
            $user->update(['phone' => null]);
            $s->update(['user_id' => null, 'state' => 'idle', 'draft' => null]);
            $this->wa->text($phone, 'Your number is unlinked. Say "hi" to link it again.');

            return;
        }
        if ($cmd === 'help') {
            $this->wa->text($phone, "You can say:\nNew idea, My ideas, Top ideas, Challenges".($user->is_admin ? ', Top 5' : '').", Stop, Unlink, Cancel.");

            return;
        }

        match ($s->state) {
            'idea_title' => $this->askSummary($s, $input),
            'idea_summary' => $this->askDetails($s, $input),
            'idea_details' => $this->askChallenge($s, $input),
            'idea_challenge' => $this->askConfirm($s, $input),
            'idea_confirm' => $this->confirmIdea($s, $user, $cmd),
            'approve_note' => $this->approveNote($s, $input),
            'approve_confirm' => $this->approveConfirm($s, $user, $cmd),
            default => $this->intent($s, $user, $cmd, $input),
        };
    }

    // ---- linking ------------------------------------------------------------------

    private function startLink(WhatsappSession $s): void
    {
        $s->update(['state' => 'link_email', 'pending_email' => null]);
        $this->wa->text($s->phone, 'Welcome to ZB Ideas. Reply with your email address to link your account.');
    }

    private function linking(WhatsappSession $s, string $input): void
    {
        if ($s->state === 'link_code') {
            if ($this->codes->check($s->pending_email, $input)) {
                $this->completeLink($s, $s->pending_email);

                return;
            }
            $fails = ($s->draft['fails'] ?? 0) + 1;
            if ($fails >= config('ideas.max_code_attempts')) {
                $s->update(['state' => 'idle', 'draft' => null, 'locked_until' => now()->addMinutes(config('ideas.lockout_minutes'))]);
                $this->wa->text($s->phone, 'Too many wrong codes. Try again in 30 minutes.');

                return;
            }
            $s->update(['draft' => ['fails' => $fails]]);
            $this->wa->text($s->phone, 'That code did not work. Check the email and reply with the 6 digits.');

            return;
        }

        if ($s->state === 'link_email') {
            if (! $this->codes->isWorkEmail($input)) {
                $this->wa->text($s->phone, config('ideas.email_domain') ? 'Please send your ZB work email, ending in @'.config('ideas.email_domain').'.' : 'Please send a valid email address.');

                return;
            }
            if (! config('ideas.require_code')) {
                $this->completeLink($s, strtolower($input));

                return;
            }
            $this->codes->send($input);
            $s->update(['state' => 'link_code', 'pending_email' => strtolower($input), 'draft' => null]);
            $this->wa->text($s->phone, 'I sent a 6-digit code to that address. Reply with it here.');

            return;
        }
        $this->startLink($s);
    }

    /** Attach this phone number to the account for $email (creating the account if needed). */
    private function completeLink(WhatsappSession $s, string $email): void
    {
        $user = $this->codes->userFor($email);
        if (! $user) {
            $this->wa->text($s->phone, 'I could not find that account. Please contact the programme team.');
            $s->update(['state' => 'idle']);

            return;
        }
        User::where('phone', $s->phone)->where('id', '!=', $user->id)->update(['phone' => null]);
        $user->update(['phone' => $s->phone]);
        $s->update(['user_id' => $user->id, 'state' => 'idle', 'pending_email' => null]);
        $this->wa->text($s->phone, "Linked, {$user->first_name}.");
        $this->menu($s);
    }

    // ---- menu and intents -----------------------------------------------------------

    private function menu(WhatsappSession $s): void
    {
        $this->wa->buttons($s->phone, 'What would you like to do?', ['new_idea' => 'New idea', 'my_ideas' => 'My ideas', 'top_ideas' => 'Top ideas']);
    }

    private function intent(WhatsappSession $s, User $user, string $cmd, string $input): void
    {
        $id = match (true) {
            in_array($cmd, ['new idea', 'new_idea', 'idea'], true) => 'new_idea',
            in_array($cmd, ['my ideas', 'my_ideas'], true) => 'my_ideas',
            in_array($cmd, ['top ideas', 'top_ideas'], true) => 'top_ideas',
            $cmd === 'challenges' => 'challenges',
            in_array($cmd, ['top 5', 'top5'], true) => 'top5',
            in_array($cmd, ['continue', 'continue my idea'], true) => 'continue',
            str_starts_with($cmd, 'like_') => $cmd,
            str_starts_with($cmd, 'approve_') => $cmd,
            default => 'unknown',
        };

        switch (true) {
            case $id === 'new_idea':
                $s->update(['state' => 'idea_title', 'draft' => []]);
                $this->wa->text($s->phone, 'What is your idea called? (Send "cancel" to stop.)');
                break;
            case $id === 'continue':
                if ($s->draft && ! empty($s->draft['title'])) {
                    $s->update(['state' => 'idea_confirm']);
                    $this->showDraft($s);
                } else {
                    $this->wa->text($s->phone, 'There is no draft to continue. Say "new idea" to start one.');
                }
                break;
            case $id === 'my_ideas':
                $this->myIdeas($s, $user);
                break;
            case $id === 'top_ideas':
                $this->topIdeas($s);
                break;
            case $id === 'challenges':
                $this->challenges($s);
                break;
            case $id === 'top5':
                $this->top5($s, $user);
                break;
            case str_starts_with($id, 'like_'):
                $idea = Idea::find((int) substr($id, 5));
                if ($idea) {
                    $liked = $this->actions->toggleLike($idea, $user);
                    $this->wa->text($s->phone, ($liked ? 'Liked ' : 'Like removed from ')."{$idea->code}.");
                }
                break;
            case str_starts_with($id, 'approve_'):
                $this->startApprove($s, $user, (int) substr($id, 8));
                break;
            default:
                $this->wa->text($s->phone, "I did not understand that. Here is the menu.");
                $this->menu($s);
        }
    }

    private function myIdeas(WhatsappSession $s, User $user): void
    {
        $ideas = Idea::where('user_id', $user->id)->withCount('likers', 'comments')->latest()->take(8)->get();
        if ($ideas->isEmpty()) {
            $this->wa->text($s->phone, 'You have not posted an idea yet. Say "new idea" to start.');

            return;
        }
        $this->wa->text($s->phone, $ideas->map(fn ($i) => "{$i->code} {$i->title}\n{$i->status}".($i->approved ? ', approved' : '')." · {$i->likers_count} likes · {$i->comments_count} comments")->implode("\n\n"));
    }

    private function topIdeas(WhatsappSession $s): void
    {
        $list = $this->ranking->rank(null)->take(5);
        foreach ($list as $n => $x) {
            $i = $x['idea'];
            $this->wa->buttons($s->phone, ($n + 1).". {$i->title}\nby {$i->author->name} · {$x['total']}/100", ["like_{$i->id}" => 'Like']);
        }
    }

    private function challenges(WhatsappSession $s): void
    {
        $list = Challenge::where(fn ($q) => $q->whereNull('deadline')->orWhere('deadline', '>=', now()->toDateString()))->orderBy('deadline')->take(5)->get();
        $this->wa->text($s->phone, $list->isEmpty() ? 'No open challenges right now.' : $list->map(fn ($c) => "{$c->title}\nDeadline: ".($c->deadline?->format('j M Y') ?? 'none'))->implode("\n\n"));
    }

    // ---- posting an idea -----------------------------------------------------------

    private function askSummary(WhatsappSession $s, string $input): void
    {
        if ($input === '' || mb_strlen($input) > 90) {
            $this->wa->text($s->phone, 'Please send a title of up to 90 characters.');

            return;
        }
        $s->update(['state' => 'idea_summary', 'draft' => ['title' => $input]]);
        $this->wa->text($s->phone, 'Summarise it in two sentences.');
    }

    private function askDetails(WhatsappSession $s, string $input): void
    {
        if ($input === '' || mb_strlen($input) > 300) {
            $this->wa->text($s->phone, 'Please send a summary of up to 300 characters.');

            return;
        }
        $s->update(['state' => 'idea_details', 'draft' => [...$s->draft, 'summary' => $input]]);
        $this->wa->text($s->phone, 'Tell me the details. What is the problem and how does your idea fix it?');
    }

    private function askChallenge(WhatsappSession $s, string $input): void
    {
        $s->update(['state' => 'idea_challenge', 'draft' => [...$s->draft, 'body' => $input]]);
        $rows = Challenge::latest()->take(8)->pluck('title', 'id')->mapWithKeys(fn ($t, $id) => ["ch_{$id}" => $t])->all();
        $this->wa->list($s->phone, 'Which challenge does it answer?', 'Choose', ['ch_none' => 'None', ...$rows]);
    }

    private function askConfirm(WhatsappSession $s, string $input): void
    {
        $key = Str::lower($input);
        $challengeId = null;
        if (str_starts_with($key, 'ch_') && $key !== 'ch_none') {
            $challengeId = Challenge::find((int) substr($key, 3))?->id;
        } elseif ($key !== 'ch_none' && $key !== 'none') {
            $challengeId = Challenge::whereRaw('lower(title) = ?', [$key])->value('id');
        }
        $s->update(['state' => 'idea_confirm', 'draft' => [...$s->draft, 'challenge_id' => $challengeId]]);
        $this->showDraft($s->fresh());
    }

    private function showDraft(WhatsappSession $s): void
    {
        $d = $s->draft;
        $ch = ! empty($d['challenge_id']) ? Challenge::find($d['challenge_id'])?->title : 'None';
        $this->wa->buttons($s->phone, "Ready to post?\n\n{$d['title']}\n{$d['summary']}\nChallenge: {$ch}\n\nPhotos and documents can be added on the web app.", ['post' => 'Post', 'edit' => 'Edit', 'cancel' => 'Cancel']);
    }

    private function confirmIdea(WhatsappSession $s, User $user, string $cmd): void
    {
        if ($cmd === 'edit') {
            $s->update(['state' => 'idea_title']);
            $this->wa->text($s->phone, 'What is your idea called?');

            return;
        }
        if ($cmd !== 'post') {
            $this->wa->text($s->phone, 'Tap Post, Edit or Cancel.');

            return;
        }
        $d = $s->draft;
        $idea = $this->actions->create($user, ['title' => $d['title'], 'summary' => $d['summary'], 'body' => $d['body'] ?? $d['summary'], 'challenge_id' => $d['challenge_id'] ?? null], 'whatsapp');
        $s->update(['state' => 'idle', 'draft' => null]);
        $this->wa->text($s->phone, "Posted as {$idea->code}. You will be told here when someone responds.\n".url('/ideas/'.$idea->id));
    }

    // ---- executive approval ----------------------------------------------------------

    private function top5(WhatsappSession $s, User $user): void
    {
        if (! $user->is_admin) {
            $this->wa->text($s->phone, 'Ranking is only available to executives.');

            return;
        }
        foreach ($this->ranking->rank(null)->take(5) as $n => $x) {
            $i = $x['idea'];
            $this->wa->buttons($s->phone, ($n + 1).". {$i->title} ({$x['total']}/100)\nby {$i->author->name}".($i->approved ? "\nAlready approved" : ''), $i->approved ? ["like_{$i->id}" => 'Like'] : ["approve_{$i->id}" => 'Approve']);
        }
    }

    private function startApprove(WhatsappSession $s, User $user, int $ideaId): void
    {
        $idea = Idea::find($ideaId);
        if (! $user->is_admin || ! $idea) {
            $this->wa->text($s->phone, 'Only executives can approve ideas.');

            return;
        }
        $s->update(['state' => 'approve_note', 'draft' => ['idea_id' => $idea->id]]);
        $this->wa->text($s->phone, "Add a note for the author of {$idea->code}, or reply Skip.");
    }

    private function approveNote(WhatsappSession $s, string $input): void
    {
        $note = Str::lower($input) === 'skip' ? null : $input;
        $s->update(['state' => 'approve_confirm', 'draft' => [...$s->draft, 'note' => $note]]);
        $idea = Idea::find($s->draft['idea_id']);
        $this->wa->buttons($s->phone, "Approve {$idea->code}".($note ? " with this note?\n{$note}" : '?'), ['yes' => 'Yes', 'no' => 'No']);
    }

    private function approveConfirm(WhatsappSession $s, User $user, string $cmd): void
    {
        $d = $s->draft;
        $s->update(['state' => 'idle', 'draft' => null]);
        if ($cmd !== 'yes') {
            $this->wa->text($s->phone, 'Not approved.');

            return;
        }
        $idea = Idea::findOrFail($d['idea_id']);
        $this->actions->approve($idea, $user, $d['note'] ?? null);
        $this->wa->text($s->phone, 'Approved. The author has been told.');
    }

    private function reset(WhatsappSession $s, bool $keepDraft): void
    {
        $s->update(['state' => 'idle', 'draft' => $keepDraft ? $s->draft : null]);
    }
}
