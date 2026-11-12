<div class="mx-auto max-w-[1080px]">
    <div class="mb-5 flex items-end justify-between gap-3">
        <div><div class="eyebrow">Together</div><h1 class="page-title">Teams</h1><p class="hint mt-1">Put a team behind an idea. Every team has its own group chat, and a team can answer a challenge as one.</p></div>
        @if ($ready)<button wire:click="$toggle('creating')" class="btn btn-primary btn-sm"><x-icon name="plus" :size="16" /> New team</button>@endif
    </div>

    @unless ($ready)
        <div class="sheet text-center text-muted"><b class="block text-ink">Teams are not switched on yet</b>Run <code>php artisan migrate</code> (or the Supabase template) to add them.</div>
    @else
        @if ($creating)
            <form wire:submit="create" class="sheet mb-5 space-y-3">
                <div><label class="lbl" for="tn">Team name</label><input id="tn" wire:model="name" class="inp" placeholder="e.g. Queue busters" required autofocus>@error('name')<p class="err">{{ $message }}</p>@enderror</div>
                <div><label class="lbl" for="ta">What is the team for? <span class="font-normal text-muted">(optional)</span></label><textarea id="ta" wire:model="about" class="inp" rows="2"></textarea>@error('about')<p class="err">{{ $message }}</p>@enderror</div>
                <div class="flex justify-end gap-2"><button type="button" wire:click="$set('creating', false)" class="btn">Cancel</button><button class="btn btn-primary">Create team</button></div>
            </form>
        @endif

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <div class="flex min-w-0 grow items-center gap-2 rounded-full border border-line px-4 md:max-w-md">
                <x-icon name="search" :size="17" class="text-muted" />
                <input wire:model.live.debounce.250ms="search" type="search" placeholder="Search teams, topics or people" class="h-11 w-full bg-transparent outline-none" aria-label="Search teams">
            </div>
            <div class="flex gap-1.5" role="group" aria-label="Show">
                @foreach (['all' => 'All teams', 'mine' => 'My teams', 'led' => 'I lead'] as $k => $l)
                    <button wire:click="$set('filter','{{ $k }}')" @class(['rounded-full px-3 py-1.5 text-[13px] font-semibold', 'bg-brand text-white' => $filter === $k, 'bg-surface text-muted hover:text-ink' => $filter !== $k]) aria-pressed="{{ $filter === $k ? 'true' : 'false' }}">{{ $l }}</button>
                @endforeach
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            @forelse ($teams as $t)
                <a wire:key="t{{ $t->id }}" href="{{ route('teams.show', $t) }}" wire:navigate class="block rounded-[20px] border border-line p-5 hover:border-tint-2 hover:bg-surface">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="min-w-0 truncate font-display text-[19px] leading-tight font-semibold tracking-tight">{{ $t->name }}</h2>
                        @if ($t->hasMember($me))<span class="chip flex-none">You are in</span>@endif
                    </div>
                    @if ($t->about)<p class="mt-1.5 line-clamp-2 text-sm text-muted">{{ $t->about }}</p>@endif
                    <div class="mt-3 flex items-center gap-2 text-xs text-muted">
                        <div class="flex -space-x-2">@foreach ($t->members->take(5) as $m)<span class="rounded-full ring-2 ring-white"><x-avatar :user="$m" :size="26" /></span>@endforeach</div>
                        <span>{{ $t->members_count }} {{ Str::plural('member', $t->members_count) }} · led by <b class="text-ink">{{ $t->leader->name }}</b></span>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2"><span class="chip chip-ghost">{{ $t->ideas_count }} {{ Str::plural('idea', $t->ideas_count) }}</span></div>
                </a>
            @empty
                <div class="py-12 text-center text-muted md:col-span-2">
                    @if (trim($search) !== '' || $filter !== 'all')
                        <b class="block text-ink">No teams match</b>Try another search.<div class="mt-4"><button wire:click="clearFilters" class="btn">Clear search</button></div>
                    @else
                        <b class="block text-ink">No teams yet</b>Start one and add the people you want to build with.
                    @endif
                </div>
            @endforelse
        </div>
    @endunless
</div>
