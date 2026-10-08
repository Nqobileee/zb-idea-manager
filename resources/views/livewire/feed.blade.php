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
