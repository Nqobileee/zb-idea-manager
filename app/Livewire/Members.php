<?php

namespace App\Livewire;

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Members')]
class Members extends Component
{
    public string $q = '';

    public function render()
    {
        $users = User::withCount(['ideas' => fn ($i) => $i->visibleTo(auth()->user())])->when($this->q, fn ($s) => $s->where(fn ($w) => $w->whereLike('name', '%'.$this->q.'%')->orWhereLike('dept', '%'.$this->q.'%')->orWhereLike('title', '%'.$this->q.'%')))->orderBy('name')->get();

        return view('livewire.members', ['users' => $users]);
    }
}
