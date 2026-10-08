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
        $users = User::withCount('ideas')->when($this->q, fn ($s) => $s->where(fn ($w) => $w->where('name', 'like', '%'.$this->q.'%')->orWhere('dept', 'like', '%'.$this->q.'%')->orWhere('title', 'like', '%'.$this->q.'%')))->orderBy('name')->get();

        return view('livewire.members', ['users' => $users]);
    }
}
