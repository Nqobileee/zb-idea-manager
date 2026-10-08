<?php

namespace App\Livewire;

use App\Models\Activity;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Activity')]
class ActivityFeed extends Component
{
    #[On('realtime')]
    public function refresh(): void {}

    public function markAllRead(): void
    {
        auth()->user()->activities()->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function render()
    {
        $items = auth()->user()->activities()->with(['actor', 'idea', 'challenge'])->latest()->take(60)->get();
        $unread = $items->whereNull('read_at')->pluck('id')->all();

        return view('livewire.activity-feed', ['items' => $items, 'unread' => $unread]);
    }
}
