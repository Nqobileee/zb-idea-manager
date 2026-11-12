<div class="mx-auto max-w-[1100px]">
    <div class="hidden pb-4 md:block">
        <div class="eyebrow">Ideas</div><h1 class="page-title">What colleagues are building</h1>
    </div>

    {{-- Phones only: desktop searches from the top bar dropdown --}}
    @if ($showSearch || $search !== '')
        <div class="mb-3 flex items-center gap-2 rounded-full border border-line px-4 md:hidden">
            <x-icon name="search" :size="17" class="text-muted" />
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search ideas or people" class="h-11 w-full bg-transparent outline-none" aria-label="Search ideas or people" autofocus>
            <button wire:click="closeSearch" class="iconbtn !size-8 flex-none" aria-label="Close search"><x-icon name="x" :size="16" /></button>
        </div>
    @endif

    <div class="flex items-center gap-1 border-b border-line">
        @foreach (['foryou' => 'For you', 'latest' => 'Latest'] as $key => $label)
            <button wire:click="$set('tab','{{ $key }}')" @class(['relative px-4 py-3 text-sm font-semibold', 'text-brand after:absolute after:inset-x-3 after:bottom-[-1px] after:h-0.5 after:rounded after:bg-brand' => $tab === $key, 'text-muted' => $tab !== $key])>{{ $label }}</button>
        @endforeach
        {{-- Search the feed (desktop): the icon opens a small box in place, next to Filter --}}
        <div class="ml-auto hidden items-center md:flex" x-data="{ open: @js($search !== '') }">
            <div x-cloak x-show="open" x-transition.opacity class="flex h-[34px] w-60 items-center gap-2 rounded-full border border-line bg-white pr-1 pl-3 focus-within:border-brand focus-within:ring-2 focus-within:ring-tint-2">
                <x-icon name="search" :size="15" class="flex-none text-muted" />
                <input x-ref="s" wire:model.live.debounce.300ms="search" type="text" placeholder="Search ideas or people" aria-label="Search ideas or people"
                       x-on:keydown.escape="$wire.closeSearch(); open = false" class="h-full min-w-0 flex-1 bg-transparent text-[13px] outline-none placeholder:text-muted">
                <button type="button" wire:click="closeSearch" x-on:click="open = false" class="grid size-6 flex-none place-items-center rounded-full text-muted hover:bg-surface hover:text-ink" aria-label="Close search"><x-icon name="x" :size="13" /></button>
            </div>
            <button type="button" x-show="! open" x-on:click="open = true; $nextTick(() => $refs.s.focus())" class="btn btn-sm !w-[34px] !px-0" aria-label="Search ideas"><x-icon name="search" :size="16" /></button>
        </div>
        {{-- Filter dropdown. The phone top bar opens the same menu through the toggle-filter event. --}}
        <div class="relative ml-auto md:ml-2" x-data="{ open: $wire.entangle('showFilter') }" x-on:keydown.escape.window="open = false">
            <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-haspopup="true" class="btn btn-sm hidden md:inline-flex"><x-icon name="filter" :size="16" /> Filter @if (count($statuses) || $challenge !== 'all')<span class="count !ml-0">{{ count($statuses) + ($challenge !== 'all' ? 1 : 0) }}</span>@endif</button>
            <div x-cloak x-show="open" x-transition.origin.top.right x-on:click.outside="open = false"
                 class="absolute top-[calc(100%+8px)] right-0 z-30 w-[min(20rem,calc(100vw-2rem))] rounded-2xl border border-line bg-white p-2 shadow-xl" role="menu" aria-label="Filter ideas">
                <div class="px-2 pt-1 pb-1.5 text-xs font-semibold text-muted">Stage</div>
                @foreach (\App\Models\Idea::STATUSES as $s)
                    <label class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm hover:bg-surface">
                        <input type="checkbox" value="{{ $s }}" wire:model.live="statuses" class="size-4 accent-[#049016]">
                        <span @class(['tag', \App\Models\Idea::stageClasses($s)])>{{ $s }}</span>
                    </label>
                @endforeach
                <div class="mt-1 border-t border-line px-2 pt-2.5 pb-1.5 text-xs font-semibold text-muted" id="chf">Challenge</div>
                <div class="max-h-56 overflow-y-auto" role="radiogroup" aria-labelledby="chf">
                    @foreach (['all' => 'All challenges'] + $challenges->mapWithKeys(fn ($c) => [(string) $c->id => $c->title])->all() as $value => $label)
                        @php($on = (string) $challenge === (string) $value)
                        <label wire:key="chf{{ $value }}" @class(['flex cursor-pointer items-start gap-2.5 rounded-lg px-2 py-2 text-[13.5px] leading-snug hover:bg-surface', 'bg-tint font-semibold text-ink hover:bg-tint' => $on, 'text-ink-2' => ! $on])>
                            <input type="radio" name="feed-challenge" value="{{ $value }}" wire:model.live="challenge" class="sr-only">
                            <span @class(['mt-px grid size-4 flex-none place-items-center rounded-full', 'bg-brand text-white' => $on, 'border border-line' => ! $on])>@if ($on)<x-icon name="check" :size="11" />@endif</span>
                            @if ($value === 'all')
                                <span>{{ $label }}</span>
                            @else
                                <span class="flex min-w-0 items-start gap-1.5"><x-icon name="flag" :size="13" class="mt-0.5 flex-none text-brand" /><span>{{ $label }}</span></span>
                            @endif
                        </label>
                    @endforeach
                </div>
                @if (count($statuses) || $challenge !== 'all')
                    <button type="button" wire:click="clearFilters" class="mt-1 w-full rounded-lg px-2 py-1.5 text-left text-sm font-semibold text-brand hover:bg-surface">Clear filters</button>
                @endif
            </div>
        </div>
    </div>

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
