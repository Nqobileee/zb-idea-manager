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
    public function render()
    {
        return view('livewire.challenges', ['challenges' => Challenge::with('owner')->withCount('ideas')->orderBy('deadline')->get()]);
    }
}
