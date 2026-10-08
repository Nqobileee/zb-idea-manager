<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('All users')]
class Users extends Component
{
    public string $q = '';

    public function toggleAdmin(int $id): void
    {
        $u = User::findOrFail($id);
        abort_if($u->id === auth()->id(), 422, 'You cannot change your own role.');
        $u->update(['is_admin' => ! $u->is_admin]);
    }

    public function render()
    {
        $users = User::withCount('ideas')->when($this->q, fn ($s) => $s->where(fn ($w) => $w->whereLike('name', "%{$this->q}%")->orWhereLike('email', "%{$this->q}%")->orWhereLike('dept', "%{$this->q}%")))->orderBy('name')->get();

        return view('livewire.admin.users', ['users' => $users]);
    }
}
