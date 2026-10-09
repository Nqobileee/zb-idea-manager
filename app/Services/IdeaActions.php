<?php

namespace App\Services;

use App\Events\ActivityCreated;
use App\Models\Activity;
use App\Models\Challenge;
use App\Models\Comment;
use App\Models\Idea;
use App\Models\IdeaTask;
use App\Models\IdeaUpdate;
use App\Models\SentEmail;
use App\Models\User;
use App\Support\Outbox;
use App\Support\Realtime;

/** Every change to ideas goes through here so the web app and the WhatsApp bot behave the same. */
class IdeaActions
{
    public function __construct(private Ranking $ranking, private WhatsappClient $whatsapp) {}

    public function create(User $author, array $data, string $source = 'web'): Idea
    {
        return Idea::create([
            'num' => Idea::nextNumber(),
            'user_id' => $author->id,
            'challenge_id' => $data['challenge_id'] ?? null,
            'title' => $data['title'],
            'summary' => $data['summary'],
            'body' => $data['body'] ?: $data['summary'],
            'status' => $data['status'] ?? 'Idea',
            'source' => $source,
        ] + (Idea::hasVisibility() ? ['visibility' => ($data['visibility'] ?? 'private') === 'public' ? 'public' : 'private'] : []));
    }

    public function update(Idea $idea, User $by, array $data): Idea
    {
        abort_unless($idea->canBeManagedBy($by), 403);
        $from = $idea->status;
        $idea->update([
            'title' => $data['title'], 'summary' => $data['summary'], 'body' => $data['body'] ?: $data['summary'],
            'status' => $data['status'], 'challenge_id' => $data['challenge_id'] ?? null,
        ] + (isset($data['visibility']) ? (Idea::hasVisibility() ? ['visibility' => ($data['visibility'] ?? 'private') === 'public' ? 'public' : 'private'] : []) : []));
        $this->recordStage($idea, $from, $by);

        return $idea;
    }

    public function removeFile(Idea $idea, User $by, int $fileId): void
    {
        abort_unless($idea->canBeManagedBy($by), 403);
        $file = $idea->files()->findOrFail($fileId);
        $this->forgetStoredFile($file);
        $file->delete();
    }

    public function delete(Idea $idea, User $by): void
    {
        abort_unless($idea->canBeManagedBy($by), 403);
        foreach ($idea->files as $file) {
            $this->forgetStoredFile($file);
        }
        $idea->delete(); // likes, saves, comments, files and activity rows are removed by the database
    }

    private function forgetStoredFile(\App\Models\IdeaFile $file): void
    {
        if ($file->path) {
            try {
                \Illuminate\Support\Facades\Storage::disk(config('ideas.upload_disk'))->delete($file->path);
            } catch (\Throwable $e) {
                report($e); // a storage hiccup must not block deleting the post
            }
        }
    }

    public function toggleLike(Idea $idea, User $user): bool
    {
        $res = $idea->likers()->toggle($user->id);
        $liked = in_array($user->id, $res['attached'], true);
        if ($liked && $idea->user_id !== $user->id) {
            $this->notify($idea->author, 'like', $user, $idea);
        }

        return $liked;
    }

    public function toggleSave(Idea $idea, User $user): bool
    {
        return in_array($user->id, $idea->savers()->toggle($user->id)['attached'], true);
    }

    public function comment(Idea $idea, User $user, string $body): Comment
    {
        $c = $idea->comments()->create(['user_id' => $user->id, 'body' => trim($body)]);
        if ($idea->user_id !== $user->id) {
            $this->notify($idea->author, 'comment', $user, $idea, trim($body));
        }

        return $c;
    }

    /** Move an idea to another pipeline stage. The author and executives can do this. */
    public function moveStage(Idea $idea, User $by, string $stage): void
    {
        abort_unless($idea->canContribute($by), 403);
        abort_unless(in_array($stage, Idea::STATUSES, true), 422, 'Unknown stage.');
        $from = $idea->status;
        $idea->update(['status' => $stage]);
        $this->recordStage($idea, $from, $by);
    }

    public function setStatus(Idea $idea, string $status, User $by): void
    {
        $this->moveStage($idea, $by, $status);
    }

    /** Write the "moved from X to Y" line on the project and tell the author if someone else moved it. */
    private function recordStage(Idea $idea, string $from, User $by): void
    {
        if ($from === $idea->status) {
            return;
        }
        IdeaUpdate::create(['idea_id' => $idea->id, 'user_id' => $by->id, 'kind' => 'stage', 'from_stage' => $from, 'to_stage' => $idea->status]);
        if ($idea->user_id !== $by->id) {
            $this->notify($idea->author, 'stage', $by, $idea, $idea->status);
        }
    }

    public function postUpdate(Idea $idea, User $by, string $body): IdeaUpdate
    {
        abort_unless($idea->canContribute($by), 403);
        $u = IdeaUpdate::create(['idea_id' => $idea->id, 'user_id' => $by->id, 'kind' => 'update', 'body' => trim($body)]);
        if ($idea->user_id !== $by->id) {
            $this->notify($idea->author, 'update', $by, $idea, trim($body));
        }

        return $u;
    }

    public function addTask(Idea $idea, User $by, string $title, ?string $due = null): IdeaTask
    {
        abort_unless($idea->canContribute($by), 403);

        return IdeaTask::create(['idea_id' => $idea->id, 'user_id' => $by->id, 'title' => trim($title), 'due_date' => $due ?: null]);
    }

    public function toggleTask(IdeaTask $task, User $by): void
    {
        abort_unless($task->idea->canContribute($by), 403);
        $task->update(['done' => ! $task->done, 'done_at' => $task->done ? null : now()]);
    }

    public function deleteTask(IdeaTask $task, User $by): void
    {
        abort_unless($task->idea->canContribute($by), 403);
        $task->delete();
    }

    public function saveNote(Idea $idea, User $by, ?string $note): void
    {
        abort_unless($idea->canContribute($by), 403);
        $idea->update(['note' => trim((string) $note) ?: null]);
    }

    /** Tag a member on the project. They are told, and can then help with it. */
    public function tag(Idea $idea, User $by, User $member): void
    {
        abort_unless($idea->canContribute($by), 403);
        if ($idea->members->contains('id', $member->id) || $member->id === $idea->user_id) {
            return;
        }
        $idea->members()->attach($member->id, ['added_by' => $by->id]);
        $idea->load('members');
        if ($member->id !== $by->id) {
            $this->notify($member, 'tag', $by, $idea, $idea->title);
        }
    }

    public function untag(Idea $idea, User $by, User $member): void
    {
        abort_unless($idea->canContribute($by), 403);
        $idea->members()->detach($member->id);
        $idea->load('members');
    }

    public function approve(Idea $idea, User $by, ?string $note = null): void
    {
        abort_unless($by->is_admin, 403);
        $idea->update(['approved' => true, 'approved_by' => $by->id, 'approved_at' => now(), 'approval_note' => $note]);
        $author = $idea->author;
        $this->email('Approval', $author->email, $by->email, "Your idea {$idea->code} has been approved", "Hi {$author->first_name},\n\n{$by->name} approved your idea \"{$idea->title}\" on ZB Idea Manager.\n\n".($note ? "Note from the reviewer:\n{$note}\n\n" : '')."Reply to this email or from your Activity page.\n\nZB Idea Manager");
        $this->notify($author, 'approval', $by, $idea, $note);
    }

    public function postChallenge(User $by, array $data): Challenge
    {
        abort_unless($by->is_admin, 403);
        $ch = Challenge::create(['user_id' => $by->id, 'title' => $data['title'], 'brief' => $data['brief'], 'keywords' => $this->keywordsFrom($data), 'deadline' => $data['deadline'] ?: null]);
        User::where('id', '!=', $by->id)->each(function (User $u) use ($by, $ch) {
            Activity::create(['user_id' => $u->id, 'type' => 'challenge', 'actor_id' => $by->id, 'challenge_id' => $ch->id]);
            Realtime::send(new ActivityCreated($u->id));
        });

        return $ch;
    }

    public function updateChallenge(Challenge $challenge, User $by, array $data): Challenge
    {
        abort_unless($by->is_admin, 403);
        $challenge->update(['title' => $data['title'], 'brief' => $data['brief'], 'keywords' => $this->keywordsFrom($data), 'deadline' => $data['deadline'] ?: null]);

        return $challenge;
    }

    /** Ideas that answered the challenge are kept; they just lose the link. */
    public function deleteChallenge(Challenge $challenge, User $by): void
    {
        abort_unless($by->is_admin, 403);
        $challenge->delete();
    }

    private function keywordsFrom(array $data): array
    {
        $keywords = collect(explode(',', $data['keywords'] ?? ''))->map(fn ($k) => strtolower(trim($k)))->filter()->values()->all();

        return $keywords ?: collect(preg_split('/\W+/', strtolower($data['title'])))->filter(fn ($w) => strlen($w) > 3)->take(6)->values()->all();
    }

    /** Email the executive the current top five (simulated: stored in the email log). */
    public function digest(User $to, ?Challenge $challenge, array $weights = Ranking::DEFAULT_WEIGHTS): SentEmail
    {
        $list = $this->ranking->rank($challenge, $weights)->take(5);
        $lines = $list->map(fn ($x, $n) => ($n + 1).". {$x['idea']->title} ({$x['total']}/100) by {$x['idea']->author->name}")->implode("\n");

        return $this->email('Digest', $to->email, 'ranker@ideas.zb.co.zw', 'Top 5 ideas: '.($challenge?->title ?? 'All ideas'), "Hi {$to->first_name},\n\nThese are the five highest ranked ideas right now. Open ZB Idea Manager to read them in full.\n\n{$lines}\n\nZB Idea Manager");
    }

    public function email(string $type, ?string $to, ?string $from, string $subject, string $body): SentEmail
    {
        // members who joined with only a WhatsApp number have no email: the row is logged and nothing is sent
        return Outbox::send($type, $to ?: 'none@'.\App\Services\PhoneAccounts::PLACEHOLDER_DOMAIN, $from ?: (string) config('mail.from.address'), $subject, $body);
    }

    private function notify(User $to, string $type, User $actor, Idea $idea, ?string $note = null): void
    {
        Activity::create(['user_id' => $to->id, 'type' => $type, 'actor_id' => $actor->id, 'idea_id' => $idea->id, 'note' => $note]);
        Realtime::send(new ActivityCreated($to->id));

        if ($type === 'approval') {
            $this->whatsapp->notifyApproved($to, $idea, $note);
        }
    }
}
