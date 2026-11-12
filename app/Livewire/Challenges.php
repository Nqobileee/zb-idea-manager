<?php

namespace App\Livewire;

use App\Models\Challenge;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Challenges')]
class Challenges extends Component
{
    public string $tab = '';

    #[Url(as: 'q')]
    public string $search = '';

    /** all | open | closed */
    #[Url(as: 'show')]
    public string $filter = 'all';

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filter = 'all';
    }

    public function mount(): void
    {
        // executives start on the challenges they set; everyone else sees all of them
        $this->tab = auth()->user()->is_admin ? 'mine' : 'all';
    }

    public function render()
    {
        $me = auth()->user();
        $q = Challenge::with('owner')->withCount(['ideas' => fn ($i) => $i->visibleTo($me)])->orderBy('deadline');
        if ($this->tab === 'mine' && $me->is_admin) {
            $q->where('user_id', $me->id);
        }

        $today = now()->toDateString();
        $term = '%'.trim($this->search).'%';
        $q->when($this->filter === 'open', fn ($c) => $c->where(fn ($w) => $w->whereNull('deadline')->orWhere('deadline', '>=', $today)))
            ->when($this->filter === 'closed', fn ($c) => $c->where('deadline', '<', $today))
            ->when(trim($this->search) !== '', fn ($c) => $c->where(fn ($w) => $w->whereLike('title', $term)->orWhereLike('brief', $term)
                ->orWhereHas('owner', fn ($o) => $o->whereLike('name', $term))));

        return view('livewire.challenges', ['challenges' => $q->get(), 'me' => $me]);
    }
}
