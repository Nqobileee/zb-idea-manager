<?php

namespace App\Livewire;

use App\Models\Team;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Browse teams, search and filter them, and start one. */
#[Layout('layouts.app')]
#[Title('Teams')]
class Teams extends Component
{
    #[Url(as: 'q')]
    public string $search = '';

    /** all | mine | led */
    #[Url(as: 'show')]
    public string $filter = 'all';

    public bool $creating = false;

    public string $name = '';

    public string $about = '';

    public function create(\App\Services\Teams $teams)
    {
        abort_unless(Team::ready(), 404);
        $data = $this->validate(['name' => 'required|string|max:80', 'about' => 'nullable|string|max:500']);
        $team = $teams->create(auth()->user(), $data['name'], $data['about']);
        $this->dispatch('toast', message: 'Team created. Its group chat is ready.');

        return $this->redirectRoute('teams.show', $team, navigate: true);
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filter = 'all';
    }

    public function render()
    {
        $me = auth()->user();
        $teams = collect();
        if (Team::ready()) {
            $term = '%'.trim($this->search).'%';
            $teams = Team::with(['leader', 'members'])->withCount('members', 'ideas')
                ->when($this->filter === 'mine', fn ($q) => $q->whereHas('members', fn ($m) => $m->where('users.id', $me->id)))
                ->when($this->filter === 'led', fn ($q) => $q->where('user_id', $me->id))
                ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($w) => $w->whereLike('name', $term)->orWhereLike('about', $term)
                    ->orWhereHas('members', fn ($m) => $m->whereLike('users.name', $term))))
                ->orderBy('name')->get();
        }

        return view('livewire.teams', ['teams' => $teams, 'me' => $me, 'ready' => Team::ready()]);
    }
}
