<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithIdeas;
use App\Models\Conversation;
use App\Models\Idea;
use App\Services\IdeaActions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Idea')]
class IdeaShow extends Component
{
    use InteractsWithIdeas;

    public Idea $idea;

    public string $comment = '';

    public string $note = '';

    public string $status = '';

    public function mount(Idea $idea): void
    {
        abort_unless($idea->isVisibleTo(auth()->user()), 404);
        $this->idea = $idea;
        $this->status = $idea->status;
    }

    #[On('realtime')]
    public function refresh(): void {}

    public function addComment(IdeaActions $actions): void
    {
        $this->validate(['comment' => 'required|string|max:2000']);
        $actions->comment($this->idea, auth()->user(), $this->comment);
        $this->reset('comment');
    }

    public function changeStatus(IdeaActions $actions): void
    {
        abort_unless(auth()->user()->is_admin, 403);
        abort_unless(in_array($this->status, Idea::STATUSES, true), 422);
        $actions->setStatus($this->idea, $this->status, auth()->user());
        $this->dispatch('toast', message: 'Stage set to '.$this->status);
    }

    public function approve(IdeaActions $actions): void
    {
        $actions->approve($this->idea, auth()->user(), trim($this->note) ?: null);
        $this->reset('note');
        $this->dispatch('toast', message: 'Approved. Email sent to '.$this->idea->author->email);
    }

    public function deleteIdea(IdeaActions $actions)
    {
        $actions->delete($this->idea, auth()->user());

        return $this->redirectRoute('home', navigate: true);
    }

    public function messageAuthor()
    {
        $conv = Conversation::between(auth()->user(), $this->idea->author);

        return $this->redirectRoute('chat', $conv, navigate: true);
    }

    public function render()
    {
        $idea = Idea::feed()->visibleTo(auth()->user())->with(['comments.author', 'approver'])->findOrFail($this->idea->id);

        return view('livewire.idea-show', ['idea' => $idea, 'me' => auth()->user()])->title($idea->title);
    }
}
