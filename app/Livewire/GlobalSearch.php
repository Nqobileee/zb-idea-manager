<?php

namespace App\Livewire;

use App\Models\Challenge;
use App\Models\Idea;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * The search box in the desktop top bar. Suggests pages, ideas, challenges, people and teams
 * from the first letter typed; with nothing typed it offers quick links to the pages.
 */
class GlobalSearch extends Component
{
    public string $q = '';

    /** [label, route, icon, extra words people might type] */
    private function pages(): array
    {
        $user = auth()->user();
        $pages = [
            ['Home', 'home', 'home', 'feed ideas for you latest'],
            ['Post an idea', 'ideas.create', 'plus', 'new submit create add'],
            [$user->is_admin ? 'My challenges' : 'Challenges', 'challenges', 'flag', 'challenge brief deadline'],
            ['Teams', 'teams', 'shield', 'team group'],
            ['Chat', 'chat', 'chat', 'messages inbox dm'],
            ['Activity', 'activity', 'bell', 'notifications alerts'],
            ['Members', 'members', 'users', 'people staff colleagues directory'],
            ['Pipeline', 'pipeline', 'board', 'projects board table list calendar timeline roadmap'],
            ['My profile', 'profile', 'user', 'account me settings'],
        ];
        if ($user->is_admin) {
            $pages[] = ['AI ranking', 'admin.ranking', 'spark', 'rank score executive'];
            $pages[] = ['Insights', 'admin.insights', 'chart', 'stats analytics reports executive'];
        }

        return $pages;
    }

    public function render()
    {
        $term = trim($this->q);
        $needle = Str::lower($term);
        $me = auth()->user();
        $results = ['pages' => collect(), 'ideas' => collect(), 'challenges' => collect(), 'people' => collect(), 'teams' => collect()];

        $results['pages'] = collect($this->pages())
            ->filter(fn ($p) => $needle === '' || Str::contains(Str::lower($p[0].' '.$p[3]), $needle))
            ->map(fn ($p) => ['label' => $p[0], 'url' => $p[1] === 'profile' ? route('profile', $me) : route($p[1]), 'icon' => $p[2]])
            ->take($needle === '' ? 20 : 3)->values();

        if ($term !== '') {
            $like = '%'.$term.'%';
            $results['ideas'] = Idea::visibleTo($me)->with('author')
                ->where(fn ($w) => $w->whereLike('title', $like)->orWhereLike('summary', $like)->orWhereLike('body', $like)
                    ->orWhereHas('author', fn ($a) => $a->whereLike('name', $like)))
                ->orderByRaw('case when lower(title) like ? then 0 else 1 end', [Str::lower($term).'%'])
                ->latest('updated_at')->limit(6)->get();
            $results['challenges'] = Challenge::where(fn ($w) => $w->whereLike('title', $like)->orWhereLike('brief', $like))
                ->latest()->limit(4)->get();
            $results['people'] = User::where(fn ($w) => $w->whereLike('name', $like)->orWhereLike('dept', $like)->orWhereLike('title', $like))
                ->orderByRaw('case when lower(name) like ? then 0 else 1 end', [Str::lower($term).'%'])
                ->orderBy('name')->limit(5)->get();
            if (Team::ready()) {
                $results['teams'] = Team::where(fn ($w) => $w->whereLike('name', $like)->orWhereLike('about', $like))
                    ->withCount('members')->orderBy('name')->limit(3)->get();
            }
        }

        return view('livewire.global-search', [
            'term' => $term,
            'results' => $results,
            'total' => collect($results)->sum(fn ($r) => $r->count()),
        ]);
    }
}
