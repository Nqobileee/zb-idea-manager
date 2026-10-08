<div class="mx-auto max-w-[700px]">
    <div class="hidden items-end justify-between pb-4 md:flex">
        <div><div class="eyebrow">Ideas</div><h1 class="page-title">What colleagues are building</h1></div>
        <button wire:click="toggleSearch" class="iconbtn" aria-label="Search"><x-icon name="search" :size="21" /></button>
    </div>

    @if ($showSearch || $search !== '')
        <div class="mb-3 flex items-center gap-2 rounded-full border border-line px-4">
            <x-icon name="search" :size="17" class="text-muted" />
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search ideas" class="h-11 w-full bg-transparent outline-none" aria-label="Search ideas" autofocus>
        </div>
    @endif

    <div class="flex items-center gap-1 border-b border-line">
        @foreach (['foryou' => 'For you', 'latest' => 'Latest'] as $key => $label)
            <button wire:click="$set('tab','{{ $key }}')" @class(['relative px-4 py-3 text-sm font-semibold', 'text-brand after:absolute after:inset-x-3 after:bottom-[-1px] after:h-0.5 after:rounded after:bg-brand' => $tab === $key, 'text-muted' => $tab !== $key])>{{ $label }}</button>
        @endforeach
        <button wire:click="toggleFilter" class="btn btn-sm ml-auto hidden md:inline-flex"><x-icon name="filter" :size="16" /> Filter @if (count($statuses) || $challenge !== 'all')<span class="count !ml-0">{{ count($statuses) + ($challenge !== 'all' ? 1 : 0) }}</span>@endif</button>
    </div>

    @if ($showFilter)
        <div class="my-2 space-y-2 rounded-2xl border border-line p-2.5">
            <div class="-mx-0.5 flex gap-1.5 overflow-x-auto px-0.5 pb-0.5 [scrollbar-width:none]" role="group" aria-label="Stage">
                @foreach (\App\Models\Idea::STATUSES as $s)
                    <label @class(['flex-none cursor-pointer rounded-full px-2.5 py-1 text-xs font-bold ring-offset-1', \App\Models\Idea::stageClasses($s), 'ring-2 ring-brand' => in_array($s, $statuses), 'opacity-60 hover:opacity-100' => ! in_array($s, $statuses)])>
                        <input type="checkbox" class="sr-only" value="{{ $s }}" wire:model.live="statuses">{{ $s }}</label>
                @endforeach
            </div>
            <div class="flex items-center gap-2">
                <label class="sr-only" for="chf">Challenge</label>
                <select id="chf" wire:model.live="challenge" class="inp !h-9 min-w-0 grow !rounded-full !px-3 text-[13px]">
                    <option value="all">All challenges</option>
                    @foreach ($challenges as $c)<option value="{{ $c->id }}">{{ $c->title }}</option>@endforeach
                </select>
                <button wire:click="clearFilters" class="btn btn-sm flex-none">Clear</button>
                <button wire:click="toggleFilter" class="btn btn-sm btn-primary flex-none">Done</button>
            </div>
        </div>
    @endif

    @if ($openChallenges->isNotEmpty())
        <section class="-mx-4 mt-4 border-b border-line pb-4 md:mx-0" aria-label="Open challenges">
            <div class="mb-2 flex items-center justify-between px-4 md:px-0">
                <h2 class="flex items-center gap-1.5 text-[11px] font-semibold tracking-widest text-brand uppercase"><x-icon name="flag" :size="13" /> Open challenges</h2>
                <a href="{{ route('challenges') }}" wire:navigate class="text-xs font-semibold text-brand hover:underline">See all</a>
            </div>
            <div class="flex snap-x snap-mandatory gap-3 overflow-x-auto px-4 pb-1 md:px-0 [scrollbar-width:none]">
                @foreach ($openChallenges as $c)
                    <article wire:key="oc{{ $c->id }}" class="flex w-[84%] max-w-[420px] flex-none snap-start flex-col rounded-2xl border border-tint-2 bg-gradient-to-br from-tint to-white p-3.5 {{ $openChallenges->count() === 1 ? 'md:w-full md:max-w-none' : '' }}">
                        <a href="{{ route('challenges.show', $c) }}" wire:navigate class="block">
                            <div class="meta"><x-avatar :user="$c->owner" :size="20" /><b class="truncate font-semibold text-ink">{{ $c->owner->name }}<x-verified :user="$c->owner" /></b><span class="dot"></span><span class="flex-none">{{ $c->created_at->diffForHumans(null, true, true) }}</span></div>
                            <h3 class="mt-2 font-display text-[16px] leading-snug font-semibold tracking-tight">{{ $c->title }}</h3>
                            <p class="mt-1 line-clamp-2 text-[13px] text-muted">{{ $c->brief }}</p>
                        </a>
                        <div class="mt-3 flex items-center gap-2">
                            <a href="{{ route('ideas.create', ['challenge' => $c->id]) }}" wire:navigate class="btn btn-primary btn-sm">Answer</a>
                            <span class="chip chip-ghost">{{ $c->ideas_count }} {{ Str::plural('idea', $c->ideas_count) }}</span>
                            @if ($c->deadline)<span class="ml-auto text-xs text-muted">{{ $c->daysLeft() }}d left</span>@endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @forelse ($ideas as $idea)
        <x-idea-card :idea="$idea" :me="$me" />
    @empty
        @if ($search === '' && ! $statuses && $challenge === 'all')
            <div class="py-16 text-center text-muted"><b class="block text-ink">No ideas yet</b>Be the first to share one.<div class="mt-4"><a href="{{ route('ideas.create') }}" wire:navigate class="btn btn-primary"><x-icon name="plus" :size="16" /> Post an idea</a></div></div>
        @else
            <div class="py-16 text-center text-muted"><b class="block text-ink">No ideas match</b>Try another search or clear the filters.</div>
        @endif
    @endforelse

    @if ($total > $ideas->count())
        <div class="py-6 text-center"><button wire:click="more" class="btn">Show more</button></div>
    @endif
</div>
