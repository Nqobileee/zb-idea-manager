<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithIdeas;
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
    }

    public function message()
    {
        return $this->redirectRoute('chat', Conversation::between(auth()->user(), $this->user), navigate: true);
    }

    public function render()
    {
        $me = auth()->user();
        $q = Idea::feed();
        $ideas = $this->tab === 'saved' && $me->id === $this->user->id
            ? $q->whereHas('savers', fn ($s) => $s->where('users.id', $me->id))->latest()->get()
            : $q->where('user_id', $this->user->id)->latest()->get();

        return view('livewire.profile', ['ideas' => $ideas, 'me' => $me, 'mine' => $me->id === $this->user->id])->title($this->user->name);
    }
}
