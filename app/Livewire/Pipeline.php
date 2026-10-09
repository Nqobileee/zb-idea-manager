<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesProject;
use App\Models\Challenge;
use App\Models\Idea;
use App\Services\IdeaActions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Pipeline')]
class Pipeline extends Component
{
    use ManagesProject;

    /** "board" = stage columns, "projects" = one card per project. */
    #[Url]
    public string $view = 'board';

    #[Url]
    public bool $mine = false;

    #[Url]
    public string $challenge = 'all';

    #[On('realtime')]
    public function refresh(): void {}

    /** Move a project to another stage (drag and drop, or the "Move to" menu). */
    public function move(int $ideaId, string $stage, IdeaActions $actions): void
    {
        $idea = Idea::with('members')->visibleTo(auth()->user())->findOrFail($ideaId);
        if ($stage === '' || $idea->status === $stage) {
            return;
        }
        $actions->moveStage($idea, auth()->user(), $stage);
        $this->dispatch('toast', message: "Moved to {$stage}");
    }

    public function render()
    {
        $me = auth()->user();
        if (! in_array($this->view, ['board', 'projects'], true)) {
            $this->view = 'board';
        }

        $q = Idea::query()
            ->visibleTo($me)
            ->with(['author', 'challenge', 'members'])
            ->withCount(['comments', 'tasks as open_tasks_count' => fn ($t) => $t->where('done', false), 'updates'])
            ->latest('updated_at');
        if ($this->view === 'projects') {
            $q->with(['tasks' => fn ($t) => $t->where('done', false), 'updates.author']);
        }
        if ($this->mine) {
            $q->where(fn ($w) => $w->where('user_id', $me->id)->orWhereHas('members', fn ($m) => $m->where('users.id', $me->id)));
        }
        if ($this->challenge !== 'all') {
            $q->where('challenge_id', (int) $this->challenge);
        }
        $ideas = $q->get();

        // keep the note boxes filled with the saved text the first time a project is shown
        foreach ($ideas as $i) {
            $this->notes[$i->id] ??= (string) $i->note;
        }

        $grouped = $ideas->groupBy('status');

        return view('livewire.pipeline', [
            'ideas' => $ideas,
            'columns' => collect(Idea::STATUSES)->mapWithKeys(fn ($s) => [$s => $grouped->get($s, collect())]),
            'challenges' => Challenge::orderBy('title')->get(),
            'taggable' => $this->taggable(),
            'me' => $me,
        ]);
    }
}
