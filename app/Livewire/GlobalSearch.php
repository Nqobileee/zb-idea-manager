<?php

namespace App\Livewire;

use App\Models\Challenge;
use App\Models\Idea;
use App\Models\Team;
use App\Models\User;
use Livewire\Component;

/** The search box in the desktop top bar: shows matching ideas, challenges, people and teams in a dropdown. */
class GlobalSearch extends Component
{
    public string $q = '';

    public function render()
    {
        $term = trim($this->q);
        $results = ['ideas' => collect(), 'challenges' => collect(), 'people' => collect(), 'teams' => collect()];

        if (mb_strlen($term) >= 2) {
            $like = '%'.$term.'%';
            $results['ideas'] = Idea::visibleTo(auth()->user())->with('author')
                ->where(fn ($w) => $w->whereLike('title', $like)->orWhereLike('summary', $like)
                    ->orWhereHas('author', fn ($a) => $a->whereLike('name', $like)))
                ->latest('updated_at')->limit(5)->get();
            $results['challenges'] = Challenge::where(fn ($w) => $w->whereLike('title', $like)->orWhereLike('brief', $like))
                ->latest()->limit(3)->get();
            $results['people'] = User::where(fn ($w) => $w->whereLike('name', $like)->orWhereLike('dept', $like)->orWhereLike('title', $like))
                ->orderBy('name')->limit(4)->get();
            if (Team::ready()) {
                $results['teams'] = Team::whereLike('name', $like)->withCount('members')->orderBy('name')->limit(3)->get();
            }
        }

        return view('livewire.global-search', [
            'term' => $term,
            'results' => $results,
            'total' => collect($results)->sum(fn ($r) => $r->count()),
        ]);
    }
}
