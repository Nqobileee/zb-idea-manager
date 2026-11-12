<div class="mx-auto max-w-[700px]">
    <a href="{{ route('teams') }}" wire:navigate class="hint hidden hover:text-ink md:inline-block">&larr; All teams</a>
    <div class="eyebrow mt-2"><x-icon name="users" :size="13" /> Team</div>

    @if ($editing)
        <form wire:submit="save" class="sheet space-y-3">
            <div><label class="lbl" for="en">Team name</label><input id="en" wire:model="name" class="inp" required>@error('name')<p class="err">{{ $message }}</p>@enderror</div>
            <div><label class="lbl" for="ea">About</label><textarea id="ea" wire:model="about" class="inp" rows="2"></textarea>@error('about')<p class="err">{{ $message }}</p>@enderror</div>
            <div class="flex justify-end gap-2"><button type="button" wire:click="$set('editing', false)" class="btn">Cancel</button><button class="btn btn-primary">Save</button></div>
        </form>
    @else
        <h1 class="font-display text-[clamp(24px,4vw,34px)] leading-tight font-bold tracking-tight text-balance">{{ $team->name }}</h1>
        @if ($team->about)<p class="mt-3 text-base leading-relaxed text-ink-2">{{ $team->about }}</p>@endif
        <div class="mt-4 flex flex-wrap items-center gap-2">
            <span class="chip chip-ghost">Led by {{ $team->leader->name }}</span>
            <span class="chip chip-ghost">{{ $team->members->count() }} {{ Str::plural('member', $team->members->count()) }}</span>
        </div>
        <div class="mt-5 flex flex-wrap gap-2">
            @if ($team->hasMember($me))
                <button wire:click="openChat" class="btn btn-primary"><x-icon name="chat" :size="16" /> Team chat</button>
                <a href="{{ route('ideas.create', ['team' => $team->id]) }}" wire:navigate class="btn"><x-icon name="plus" :size="16" /> Post an idea as this team</a>
            @endif
            @if ($team->canBeManagedBy($me))
                <button wire:click="edit" class="btn"><x-icon name="edit" :size="15" /> Edit</button>
                <button wire:click="deleteTeam" wire:confirm="Delete this team and its group chat? The ideas it put forward are kept." class="btn !text-heart"><x-icon name="x" :size="15" /> Delete</button>
            @endif
            @if ($team->hasMember($me) && $team->user_id !== $me->id)
                <button wire:click="leave" wire:confirm="Leave this team? You will also leave its group chat." class="btn">Leave team</button>
            @endif
        </div>
    @endif

    <h2 class="mt-8 mb-2 font-display text-base font-semibold">Members ({{ $team->members->count() }})</h2>
    @if ($team->canBeManagedBy($me))
        <div class="relative mb-3">
            <input wire:model.live.debounce.250ms="find" type="search" class="inp" placeholder="Add someone by name or department" aria-label="Add a member">
            @if ($people->isNotEmpty())
                <div class="absolute inset-x-0 top-12 z-10 max-h-72 overflow-y-auto rounded-2xl border border-line bg-white p-1.5 shadow-xl">
                    @foreach ($people as $p)
                        <button wire:click="add({{ $p->id }})" wire:key="p{{ $p->id }}" class="flex w-full items-center gap-3 rounded-xl p-2 text-left hover:bg-surface"><x-avatar :user="$p" :size="34" /><span class="min-w-0"><b class="block truncate">{{ $p->name }}</b><small class="text-muted">{{ $p->title }}</small></span><span class="chip ml-auto">Add</span></button>
                    @endforeach
                </div>
            @elseif (trim($find) !== '')
                <p class="hint mt-1.5">No one else matches “{{ $find }}”.</p>
            @endif
        </div>
    @endif
    <div class="divide-y divide-line rounded-2xl border border-line">
        @foreach ($team->members as $m)
            <div wire:key="m{{ $m->id }}" class="flex items-center gap-3 p-3">
                <a href="{{ route('profile', $m) }}" wire:navigate class="flex min-w-0 grow items-center gap-3"><x-avatar :user="$m" :size="38" /><span class="min-w-0"><b class="block truncate">{{ $m->name }}<x-verified :user="$m" /></b><small class="text-muted">{{ $m->title }}</small></span></a>
                @if ($m->id === $team->user_id)<span class="chip flex-none">Leader</span>
                @elseif ($team->canBeManagedBy($me))<button wire:click="remove({{ $m->id }})" wire:confirm="Remove {{ $m->name }} from the team?" class="iconbtn flex-none" aria-label="Remove {{ $m->name }}"><x-icon name="x" :size="16" /></button>@endif
            </div>
        @endforeach
    </div>

    <h2 class="mt-8 font-display text-base font-semibold">Ideas ({{ $ideas->count() }})</h2>
    @forelse ($ideas as $idea)<x-idea-card :idea="$idea" :me="$me" />@empty<p class="hint mt-2">No ideas from this team yet.</p>@endforelse
</div>
