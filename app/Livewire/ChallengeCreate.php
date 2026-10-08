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
    public string $title = '';

    public string $brief = '';

    public string $keywords = '';

    public string $deadline = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->is_admin, 403);
    }

    public function save(IdeaActions $actions)
    {
        $data = $this->validate([
            'title' => 'required|string|max:140', 'brief' => 'required|string|max:1500',
            'keywords' => 'nullable|string|max:300', 'deadline' => 'nullable|date|after:today',
        ]);
        $c = $actions->postChallenge(auth()->user(), $data);

        return $this->redirectRoute('challenges.show', $c, navigate: true);
    }

    public function render()
    {
        return view('livewire.challenge-create');
    }
}
