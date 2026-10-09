<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Members')]
class Members extends Component
{
    public string $q = '';

    public function approveExecutive(int $userId): void
    {
        abort_unless(auth()->user()->is_admin, 403);
        User::where('requested_role', 'executive')->findOrFail($userId)->update(['is_admin' => true, 'requested_role' => null]);
        $this->dispatch('toast', message: 'Executive access approved');
    }

    public function declineExecutive(int $userId): void
    {
        abort_unless(auth()->user()->is_admin, 403);
        User::where('requested_role', 'executive')->findOrFail($userId)->update(['requested_role' => null]);
        $this->dispatch('toast', message: 'Request declined');
    }

    public function render()
    {
        $requests = auth()->user()->is_admin && Schema::hasColumn('users', 'requested_role')
            ? User::where('requested_role', 'executive')->orderBy('name')->get()
            : collect();
        $users = User::withCount(['ideas' => fn ($i) => $i->visibleTo(auth()->user())])->when($this->q, fn ($s) => $s->where(fn ($w) => $w->whereLike('name', '%'.$this->q.'%')->orWhereLike('dept', '%'.$this->q.'%')->orWhereLike('title', '%'.$this->q.'%')))->orderBy('name')->get();

        return view('livewire.members', ['users' => $users, 'requests' => $requests]);
    }
}
