<?php

namespace App\Livewire;

use App\Models\Challenge;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Challenges')]
class Challenges extends Component
{
    public string $tab = '';

    public function mount(): void
    {
        // executives start on the challenges they set; everyone else sees all of them
        $this->tab = auth()->user()->is_admin ? 'mine' : 'all';
    }

    public function render()
    {
        $me = auth()->user();
        $q = Challenge::with('owner')->withCount(['ideas' => fn ($i) => $i->visibleTo($me)])->orderBy('deadline');
        if ($this->tab === 'mine' && $me->is_admin) {
            $q->where('user_id', $me->id);
        }

        return view('livewire.challenges', ['challenges' => $q->get(), 'me' => $me]);
    }
}
