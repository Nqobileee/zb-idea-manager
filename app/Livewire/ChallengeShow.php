<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithIdeas;
use App\Models\Challenge;
use App\Models\Idea;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Challenge')]
class ChallengeShow extends Component
{
    use InteractsWithIdeas;

    public Challenge $challenge;

    public function mount(Challenge $challenge): void
    {
        $this->challenge = $challenge;
    }

    public function render()
    {
        return view('livewire.challenge-show', [
            'ideas' => Idea::feed()->where('challenge_id', $this->challenge->id)->latest()->get(),
            'me' => auth()->user(),
        ]);
    }
}
