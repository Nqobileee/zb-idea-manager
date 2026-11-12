<?php

namespace App\Livewire;

use App\Services\IdeaActions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('New challenge')]
class ChallengeCreate extends Component
{
    public ?int $challengeId = null;

    public string $title = '';

    public string $brief = '';

    public string $keywords = '';

    public string $deadline = '';

    /** How many executives must approve an answer before it counts as accepted. */
    public int $approvals = 1;

    public function mount(?\App\Models\Challenge $challenge = null): void
    {
        abort_unless(auth()->user()->is_admin, 403);
        if ($challenge?->exists) {
            $this->challengeId = $challenge->id;
            $this->title = $challenge->title;
            $this->brief = $challenge->brief;
            $this->keywords = implode(', ', $challenge->keywords ?? []);
            $this->deadline = $challenge->deadline?->format('Y-m-d') ?? '';
            $this->approvals = (int) ($challenge->approvals_required ?? 1);
        }
    }

    public function save(IdeaActions $actions)
    {
        $data = $this->validate([
            'title' => 'required|string|max:140', 'brief' => 'required|string|max:1500',
            'keywords' => 'nullable|string|max:300', 'deadline' => $this->challengeId ? 'nullable|date' : 'nullable|date|after:today',
            'approvals' => 'required|integer|min:1|max:9',
        ]);
        $data['approvals_required'] = $data['approvals'];
        $c = $this->challengeId
            ? $actions->updateChallenge(\App\Models\Challenge::findOrFail($this->challengeId), auth()->user(), $data)
            : $actions->postChallenge(auth()->user(), $data);

        return $this->redirectRoute('challenges.show', $c, navigate: true);
    }

    public function render()
    {
        return view('livewire.challenge-create', ['executives' => max(1, \App\Models\User::where('is_admin', true)->count())])->title($this->challengeId ? 'Edit challenge' : 'New challenge');
    }
}
