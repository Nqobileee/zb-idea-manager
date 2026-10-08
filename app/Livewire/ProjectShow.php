<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesProject;
use App\Models\Idea;
use App\Models\IdeaTask;
use App\Services\IdeaActions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** One project in the pipeline: the original post, its progress updates, pending work and comments. */
#[Layout('layouts.app')]
#[Title('Project')]
class ProjectShow extends Component
{
    use ManagesProject;

    public Idea $idea;

    #[Url]
    public string $tab = 'updates';

    public string $updateText = '';

    public string $taskTitle = '';

    public ?string $taskDue = null;

    public string $comment = '';

    public function mount(Idea $idea): void
    {
        $this->idea = $idea;
        $this->notes[$idea->id] = (string) $idea->note;
    }

    #[On('realtime')]
    public function refresh(): void {}

    public function moveTo(string $stage, IdeaActions $actions): void
    {
        if ($stage === '' || $stage === $this->idea->status) {
            return;
        }
        $actions->moveStage(Idea::with('members')->findOrFail($this->idea->id), auth()->user(), $stage);
        $this->idea->refresh();
        $this->dispatch('toast', message: "Moved to {$stage}");
    }

    public function postUpdate(IdeaActions $actions): void
    {
        $this->validate(['updateText' => 'required|string|max:3000']);
        $actions->postUpdate(Idea::with('members')->findOrFail($this->idea->id), auth()->user(), $this->updateText);
        $this->reset('updateText');
    }

    public function addTask(IdeaActions $actions): void
    {
        $this->validate(['taskTitle' => 'required|string|max:200', 'taskDue' => 'nullable|date']);
        $actions->addTask(Idea::with('members')->findOrFail($this->idea->id), auth()->user(), $this->taskTitle, $this->taskDue);
        $this->reset('taskTitle', 'taskDue');
    }

    public function deleteTask(int $id, IdeaActions $actions): void
    {
        $actions->deleteTask(IdeaTask::with('idea.members')->where('idea_id', $this->idea->id)->findOrFail($id), auth()->user());
    }

    public function addComment(IdeaActions $actions): void
    {
        $this->validate(['comment' => 'required|string|max:2000']);
        $actions->comment($this->idea, auth()->user(), $this->comment);
        $this->reset('comment');
    }

    public function render()
    {
        $idea = Idea::with(['author', 'challenge', 'members', 'updates.author', 'tasks', 'comments.author'])->findOrFail($this->idea->id);
        if (! in_array($this->tab, ['updates', 'pending', 'comments'], true)) {
            $this->tab = 'updates';
        }

        return view('livewire.project-show', [
            'idea' => $idea,
            'me' => auth()->user(),
            'can' => $idea->canContribute(auth()->user()),
            'taggable' => $this->taggable(),
            'open' => $idea->tasks->where('done', false),
            'done' => $idea->tasks->where('done', true),
        ])->title($idea->title);
    }
}
