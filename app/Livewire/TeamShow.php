<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithIdeas;
use App\Models\Idea;
use App\Models\Team;
use App\Models\User;
use App\Services\Teams;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Team')]
class TeamShow extends Component
{
    use InteractsWithIdeas;

    public Team $team;

    public string $find = '';

    public bool $editing = false;

    public string $name = '';

    public string $about = '';

    /** Looked up here, not by route binding, so a link opened before the teams migration has run is a 404 and not an error. */
    public function mount(int|string $teamId): void
    {
        abort_unless(Team::ready(), 404);
        $this->team = Team::findOrFail($teamId);
    }

    #[On('realtime')]
    public function refresh(): void {}

    public function edit(): void
    {
        abort_unless($this->team->canBeManagedBy(auth()->user()), 403);
        $this->name = $this->team->name;
        $this->about = (string) $this->team->about;
        $this->editing = true;
    }

    public function save(Teams $teams): void
    {
        $data = $this->validate(['name' => 'required|string|max:80', 'about' => 'nullable|string|max:500']);
        $teams->update($this->team, auth()->user(), $data['name'], $data['about']);
        $this->team->refresh();
        $this->editing = false;
    }

    public function add(int $userId, Teams $teams): void
    {
        $teams->add($this->team, auth()->user(), User::findOrFail($userId));
        $this->find = '';
    }

    public function remove(int $userId, Teams $teams): void
    {
        $teams->remove($this->team, auth()->user(), User::findOrFail($userId));
    }

    public function leave(Teams $teams)
    {
        $teams->remove($this->team, auth()->user(), auth()->user());

        return $this->redirectRoute('teams', navigate: true);
    }

    public function deleteTeam(Teams $teams)
    {
        $teams->delete($this->team, auth()->user());

        return $this->redirectRoute('teams', navigate: true);
    }

    public function openChat()
    {
        $group = $this->team->groupChat;
        abort_unless($group && $group->involves(auth()->user()), 403);

        return $this->redirectRoute('chat', $group, navigate: true);
    }

    public function render()
    {
        $me = auth()->user();
        $team = Team::with(['leader', 'members'])->findOrFail($this->team->id);
        $people = $team->canBeManagedBy($me) && trim($this->find) !== ''
            ? User::whereNotIn('id', $team->members->pluck('id'))->where(fn ($w) => $w->whereLike('name', '%'.trim($this->find).'%')->orWhereLike('dept', '%'.trim($this->find).'%'))->orderBy('name')->limit(8)->get()
            : collect();

        return view('livewire.team-show', [
            'team' => $team,
            'me' => $me,
            'people' => $people,
            'ideas' => Idea::feed()->visibleTo($me)->where('team_id', $team->id)->latest()->get(),
        ])->title($team->name);
    }
}
