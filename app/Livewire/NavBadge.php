<?php

namespace App\Livewire;

use App\Models\Message;
use Livewire\Attributes\On;
use Livewire\Component;

/** Unread counter shown on the Activity and Chat links. Refreshes when Reverb pushes an event. */
class NavBadge extends Component
{
    public string $kind = 'activity';

    public bool $float = false;

    #[On('realtime')]
    public function refresh(): void {}

    public function render()
    {
        $user = auth()->user();
        $n = $this->kind === 'activity'
            ? $user->unreadActivityCount()
            : Message::whereNull('read_at')->where('user_id', '!=', $user->id)
                ->whereIn('conversation_id', $user->conversations()->select('id'))->count();

        return view('livewire.nav-badge', ['n' => $n]);
    }
}
