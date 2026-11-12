<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesProject;
use App\Models\Challenge;
use App\Models\Idea;
use App\Models\IdeaTask;
use App\Models\IdeaUpdate;
use App\Services\IdeaActions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Pipeline')]
class Pipeline extends Component
{
    use ManagesProject;

    /** The ways to look at the pipeline: key => [label, icon]. */
    public const VIEWS = [
        'board' => ['Board', 'board'],
        'table' => ['Table', 'table'],
        'list' => ['List', 'list'],
        'projects' => ['Projects', 'file'],
        'calendar' => ['Calendar', 'calendar'],
        'timeline' => ['Timeline', 'timeline'],
    ];

    /** Table columns that can be sorted. */
    public const SORTS = ['title', 'status', 'author', 'challenge', 'pending', 'comments', 'created', 'updated'];

    #[Url]
    public string $view = 'board';

    #[Url]
    public bool $mine = false;

    #[Url]
    public string $challenge = 'all';

    #[Url]
    public string $sort = 'updated';

    #[Url]
    public string $dir = 'desc';

    /** Calendar month as Y-m; empty means this month. */
    #[Url]
    public string $month = '';

    /** Timeline window in months. */
    #[Url]
    public int $span = 6;

    #[On('realtime')]
    public function refresh(): void {}

    /** Move a project to another stage (drag and drop, or the "Move to" menu). */
    public function move(int $ideaId, string $stage, IdeaActions $actions): void
    {
        $idea = Idea::with('members')->visibleTo(auth()->user())->findOrFail($ideaId);
        if ($stage === '' || $idea->status === $stage) {
            return;
        }
        $actions->moveStage($idea, auth()->user(), $stage);
        $this->dispatch('toast', message: "Moved to {$stage}");
    }

    /** Click a table heading: sort by it, or flip the direction if it is already the sort. */
    public function sortBy(string $key): void
    {
        if (! in_array($key, self::SORTS, true)) {
            return;
        }
        if ($this->sort === $key) {
            $this->dir = $this->dir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $key;
            $this->dir = in_array($key, ['title', 'author', 'challenge'], true) ? 'asc' : 'desc';
        }
    }

    public function shiftMonth(int $by): void
    {
        $this->month = $this->monthStart()->addMonths($by)->format('Y-m');
    }

    public function thisMonth(): void
    {
        $this->month = '';
    }

    private function monthStart(): CarbonImmutable
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month)) {
            return CarbonImmutable::createFromFormat('!Y-m', $this->month);
        }

        return CarbonImmutable::now()->startOfMonth();
    }

    public function render()
    {
        $me = auth()->user();
        if (! array_key_exists($this->view, self::VIEWS)) {
            $this->view = 'board';
        }
        if (! in_array($this->sort, self::SORTS, true)) {
            $this->sort = 'updated';
        }
        if (! in_array($this->dir, ['asc', 'desc'], true)) {
            $this->dir = 'desc';
        }
        if (! in_array($this->span, [3, 6, 12], true)) {
            $this->span = 6;
        }

        $q = Idea::query()
            ->visibleTo($me)
            ->with(['author', 'challenge', 'members'])
            ->withCount(['comments', 'tasks as open_tasks_count' => fn ($t) => $t->where('done', false), 'updates'])
            ->latest('updated_at');
        if ($this->view === 'projects') {
            $q->with(['tasks' => fn ($t) => $t->where('done', false), 'updates.author']);
        }
        if ($this->view === 'timeline') {
            $q->with(['updates' => fn ($u) => $u->where('kind', 'stage')]);
        }
        if ($this->mine) {
            $q->where(fn ($w) => $w->where('user_id', $me->id)->orWhereHas('members', fn ($m) => $m->where('users.id', $me->id))
                ->when(\App\Models\Team::ready(), fn ($t) => $t->orWhereIn('team_id', $me->teams()->select('teams.id'))));
        }
        if ($this->challenge !== 'all') {
            $q->where('challenge_id', (int) $this->challenge);
        }
        $ideas = $q->get();

        // keep the note boxes filled with the saved text the first time a project is shown
        foreach ($ideas as $i) {
            $this->notes[$i->id] ??= (string) $i->note;
        }

        $grouped = $ideas->groupBy('status');

        return view('livewire.pipeline', [
            'ideas' => $ideas,
            'sorted' => in_array($this->view, ['table', 'list'], true) ? $this->sorted($ideas) : $ideas,
            'columns' => collect(Idea::STATUSES)->mapWithKeys(fn ($s) => [$s => $grouped->get($s, collect())]),
            'challenges' => Challenge::orderBy('title')->get(),
            'taggable' => $this->taggable(),
            'calendar' => $this->view === 'calendar' ? $this->calendar($ideas) : null,
            'timeline' => $this->view === 'timeline' ? $this->timeline($ideas) : null,
            'me' => $me,
        ]);
    }

    private function sorted(Collection $ideas): Collection
    {
        $key = match ($this->sort) {
            'title' => fn ($i) => mb_strtolower($i->title),
            'status' => fn ($i) => $i->stageIndex(),
            'author' => fn ($i) => mb_strtolower($i->author->name),
            'challenge' => fn ($i) => mb_strtolower($i->challenge?->title ?? "\u{10FFFF}"),
            'pending' => fn ($i) => $i->open_tasks_count,
            'comments' => fn ($i) => $i->comments_count,
            'created' => fn ($i) => $i->created_at->getTimestamp(),
            default => fn ($i) => $i->updated_at->getTimestamp(),
        };

        return $ideas->sortBy($key, SORT_REGULAR, $this->dir === 'desc')->values();
    }

    /** Month grid: due tasks, stage moves, new ideas and challenge deadlines, grouped by day. */
    private function calendar(Collection $ideas): array
    {
        $start = $this->monthStart();
        $end = $start->endOfMonth();
        $ids = $ideas->pluck('id');
        $byId = $ideas->keyBy('id');
        $events = collect();

        IdeaTask::whereIn('idea_id', $ids)->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])->get()
            ->each(fn ($t) => $events->push([
                'date' => $t->due_date->format('Y-m-d'), 'kind' => $t->done ? 'done' : ($t->isOverdue() ? 'overdue' : 'task'),
                'label' => $t->title, 'idea' => $byId[$t->idea_id],
            ]));

        IdeaUpdate::whereIn('idea_id', $ids)->where('kind', 'stage')->whereBetween('created_at', [$start, $end])->get()
            ->each(fn ($u) => $events->push([
                'date' => $u->created_at->format('Y-m-d'), 'kind' => 'stage', 'stage' => $u->to_stage,
                'label' => 'Moved to '.$u->to_stage, 'idea' => $byId[$u->idea_id],
            ]));

        $ideas->filter(fn ($i) => $i->created_at->between($start, $end))
            ->each(fn ($i) => $events->push(['date' => $i->created_at->format('Y-m-d'), 'kind' => 'posted', 'label' => 'Posted', 'idea' => $i]));

        Challenge::whereBetween('deadline', [$start->toDateString(), $end->toDateString()])
            ->when($this->challenge !== 'all', fn ($c) => $c->whereKey((int) $this->challenge))->get()
            ->each(fn ($c) => $events->push(['date' => $c->deadline->format('Y-m-d'), 'kind' => 'deadline', 'label' => $c->title, 'challenge' => $c]));

        $order = ['overdue' => 0, 'deadline' => 1, 'task' => 2, 'stage' => 3, 'posted' => 4, 'done' => 5];

        return [
            'month' => $start,
            'gridStart' => $start->startOfWeek(CarbonImmutable::MONDAY),
            'gridEnd' => $end->endOfWeek(CarbonImmutable::SUNDAY),
            'days' => $events->sortBy(fn ($e) => $order[$e['kind']])->groupBy('date'),
            'count' => $events->count(),
        ];
    }

    /** Roadmap rows: how long each project spent in each stage, inside the chosen window. */
    private function timeline(Collection $ideas): array
    {
        $now = CarbonImmutable::now();
        $from = $now->subMonths($this->span - 1)->startOfMonth();
        $to = $now->endOfMonth();
        $total = $to->getTimestamp() - $from->getTimestamp();
        $pct = fn (CarbonImmutable $d) => max(0, min(100, ($d->getTimestamp() - $from->getTimestamp()) / $total * 100));

        $rows = $ideas->sortBy('created_at')->values()->map(function ($idea) use ($now, $from, $pct) {
            $moves = $idea->updates->sortBy('created_at')->values();
            $stage = $moves->first()?->from_stage ?? $idea->status;
            $at = CarbonImmutable::parse($idea->created_at);
            $segments = [];
            foreach ($moves as $m) {
                $segments[] = [$stage, $at, CarbonImmutable::parse($m->created_at)];
                [$stage, $at] = [$m->to_stage, CarbonImmutable::parse($m->created_at)];
            }
            $segments[] = [$stage, $at, $now];

            $bars = collect($segments)
                ->filter(fn ($s) => $s[2]->greaterThan($from))
                ->map(fn ($s) => [
                    'stage' => $s[0], 'left' => $pct($s[1]), 'width' => max(0.6, $pct($s[2]) - $pct($s[1])),
                    'title' => $s[0].': '.$s[1]->format('j M').' to '.($s[2]->equalTo($now) ? 'now' : $s[2]->format('j M')).' ('.max(1, (int) $s[1]->diffInDays($s[2])).' days)',
                ])->values();

            return ['idea' => $idea, 'bars' => $bars, 'clipped' => $idea->created_at->lessThan($from)];
        });

        $months = collect();
        for ($m = $from; $m->lessThanOrEqualTo($to); $m = $m->addMonth()) {
            $months->push(['label' => $m->format($this->span > 6 ? 'M' : 'M Y'), 'left' => $pct($m)]);
        }

        return ['rows' => $rows, 'months' => $months, 'today' => $pct($now), 'from' => $from];
    }
}
