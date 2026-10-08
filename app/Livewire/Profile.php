<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithIdeas;
use App\Models\Challenge;
use App\Models\Conversation;
use App\Models\Idea;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Profile')]
class Profile extends Component
{
    use InteractsWithIdeas;

    public User $user;

    public string $tab = 'ideas';

    public function mount(User $user): void
    {
        $this->user = $user;
        // executives start on the challenges they set
        $this->tab = $user->is_admin ? 'challenges' : 'ideas';
    }

    public function message()
    {
        return $this->redirectRoute('chat', Conversation::between(auth()->user(), $this->user), navigate: true);
    }

    public function render()
    {
        $me = auth()->user();
        $mine = $me->id === $this->user->id;
        $tabs = [];
        if ($this->user->is_admin) {
            $tabs['challenges'] = $mine ? 'My challenges' : 'Challenges';
        }
        $tabs['ideas'] = $mine ? 'My ideas' : 'Ideas';
        if ($mine) {
            $tabs['saved'] = 'Saved';
        }
        if (! isset($tabs[$this->tab])) {
            $this->tab = array_key_first($tabs);
        }

        $challenges = $this->tab === 'challenges'
            ? Challenge::with('owner')->withCount('ideas')->where('user_id', $this->user->id)->orderBy('deadline')->get()
            : collect();

        $q = Idea::feed();
        $ideas = $this->tab === 'challenges' ? collect() : ($this->tab === 'saved' && $mine
            ? $q->whereHas('savers', fn ($s) => $s->where('users.id', $me->id))->latest()->get()
            : $q->where('user_id', $this->user->id)->latest()->get());

        return view('livewire.profile', ['ideas' => $ideas, 'challenges' => $challenges, 'tabs' => $tabs, 'me' => $me, 'mine' => $mine])->title($this->user->name);
    }
}
