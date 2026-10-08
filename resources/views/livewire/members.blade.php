<div class="mx-auto max-w-[1080px]">
    <div class="eyebrow">Verified ZB staff</div>
    <h1 class="page-title">Members</h1>
    <p class="hint mt-1 mb-4">Everyone here is a verified ZB employee. Open a profile to see their ideas.</p>
    <input wire:model.live.debounce.250ms="q" type="search" class="inp mb-5 max-w-md" placeholder="Search by name, role or department" aria-label="Search members">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($users as $u)
            <a href="{{ route('profile', $u) }}" wire:navigate wire:key="u{{ $u->id }}" class="flex items-center gap-3 rounded-2xl border border-line p-3.5 hover:border-tint-2 hover:bg-surface">
                <x-avatar :user="$u" :size="46" />
                <span class="min-w-0"><b class="block truncate">{{ $u->name }}<x-verified :user="$u" /></b><small class="block truncate text-xs text-muted">{{ $u->title }}</small><small class="text-xs text-muted">{{ $u->dept }} · {{ $u->ideas_count }} {{ Str::plural('idea', $u->ideas_count) }}</small></span>
            </a>
        @endforeach
    </div>
</div>
