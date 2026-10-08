<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithIdeas;
use App\Models\Challenge;
use App\Models\Idea;
use App\Services\Ranking;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Ideas')]
class Feed extends Component
{
    use InteractsWithIdeas;

    #[Url(as: 'tab')]
    public string $tab = 'foryou';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public array $statuses = [];

    #[Url]
    public string $challenge = 'all';

    public bool $showFilter = false;

    public bool $showSearch = false;

    public int $limit = 10;

    #[On('toggle-filter')]
    public function toggleFilter(): void
    {
        $this->showFilter = ! $this->showFilter;
    }

    #[On('toggle-search')]
    public function toggleSearch(): void
    {
        $this->showSearch = ! $this->showSearch;
    }

    #[On('realtime')]
    public function refresh(): void {}

    public function clearFilters(): void
    {
        $this->statuses = [];
        $this->challenge = 'all';
        $this->search = '';
    }

    public function more(): void
    {
        $this->limit += 10;
    }

    public function updated(): void
    {
        $this->limit = 10;
    }

    public function render(Ranking $ranking)
    {
        $me = auth()->user();
        $q = Idea::feed();
        if ($this->statuses) {
            $q->whereIn('status', $this->statuses);
        }
        if ($this->challenge !== 'all') {
            $q->where('challenge_id', (int) $this->challenge);
        }
        if (trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
            $q->where(fn ($w) => $w->whereLike('title', $term)->orWhereLike('summary', $term)->orWhereLike('body', $term));
        }
        $all = $q->get();

        $ideas = $this->tab === 'latest'
            ? $all->sortByDesc('created_at')
            : $all->sortByDesc(fn (Idea $i) => $this->score($i, $me, $ranking));

        // open challenges sit at the top of the feed, newest first, until their deadline passes
        $filtering = trim($this->search) !== '' || $this->statuses || $this->challenge !== 'all';
        $openChallenges = $filtering ? collect() : Challenge::with('owner')->withCount('ideas')
            ->where(fn ($w) => $w->whereNull('deadline')->orWhere('deadline', '>=', now()->toDateString()))
            ->latest()->take(5)->get();

        return view('livewire.feed', [
            'openChallenges' => $openChallenges,
            'ideas' => $ideas->values()->take($this->limit),
            'total' => $ideas->count(),
            'challenges' => Challenge::orderBy('title')->get(),
            'me' => $me,
        ]);
    }

    /** "For you": engagement, your department, challenge ideas and freshness. Your own posts drop down. */
    private function score(Idea $i, $me, Ranking $ranking): float
    {
        $s = $ranking->rawEngagement($i);
        if ($i->author->dept === $me->dept) {
            $s += 15;
        }
        if ($i->challenge_id) {
            $s += 12;
        }
        $s -= $i->created_at->diffInHours(now()) / 24 * .9;
        if ($i->user_id === $me->id) {
            $s -= 40;
        }

        return $s;
    }
}
