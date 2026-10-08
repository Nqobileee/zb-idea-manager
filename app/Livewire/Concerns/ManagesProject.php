<?php

namespace App\Livewire\Concerns;

use App\Models\Idea;
use App\Models\IdeaTask;
use App\Models\User;
use App\Services\IdeaActions;

/** Quick actions on a project: tick tasks, add a task, save the note, tag and untag members. */
trait ManagesProject
{
    /** Project note text being edited, keyed by idea id. */
    public array $notes = [];

    /** New pending item text, keyed by idea id. */
    public array $newTask = [];

    /** The project whose "tag a member" box is open, and what is typed in it. */
    public ?int $taggingFor = null;

    public string $tagSearch = '';

    public function toggleTask(int $id, IdeaActions $actions): void
    {
        $actions->toggleTask(IdeaTask::with('idea.members')->findOrFail($id), auth()->user());
    }

    public function addTaskTo(int $ideaId, IdeaActions $actions): void
    {
        $title = trim($this->newTask[$ideaId] ?? '');
        if ($title === '') {
            return;
        }
        $actions->addTask($this->projectFor($ideaId), auth()->user(), mb_substr($title, 0, 200));
        $this->newTask[$ideaId] = '';
    }

    public function saveNote(int $ideaId, IdeaActions $actions): void
    {
        $actions->saveNote($this->projectFor($ideaId), auth()->user(), mb_substr($this->notes[$ideaId] ?? '', 0, 3000));
        $this->dispatch('toast', message: 'Note saved');
    }

    public function openTagging(int $ideaId): void
    {
        $this->taggingFor = $this->taggingFor === $ideaId ? null : $ideaId;
        $this->tagSearch = '';
    }

    public function tagMember(int $ideaId, int $userId, IdeaActions $actions): void
    {
        $actions->tag($this->projectFor($ideaId), auth()->user(), User::findOrFail($userId));
        $this->taggingFor = null;
        $this->tagSearch = '';
    }

    public function untagMember(int $ideaId, int $userId, IdeaActions $actions): void
    {
        $actions->untag($this->projectFor($ideaId), auth()->user(), User::findOrFail($userId));
    }

    /** People who can still be tagged on the open project, filtered by what was typed. */
    protected function taggable(): \Illuminate\Support\Collection
    {
        if (! $this->taggingFor) {
            return collect();
        }
        $idea = Idea::with('members')->find($this->taggingFor);
        if (! $idea) {
            return collect();
        }

        return User::whereNotIn('id', $idea->members->pluck('id')->push($idea->user_id))
            ->when(trim($this->tagSearch) !== '', fn ($q) => $q->where(fn ($w) => $w->whereLike('name', '%'.trim($this->tagSearch).'%')->orWhereLike('dept', '%'.trim($this->tagSearch).'%')))
            ->orderBy('name')->limit(6)->get();
    }

    private function projectFor(int $ideaId): Idea
    {
        return Idea::with('members')->findOrFail($ideaId);
    }
}
