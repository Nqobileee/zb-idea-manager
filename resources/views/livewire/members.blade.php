<div class="mx-auto max-w-[1080px]">
    <div class="eyebrow">Verified ZB staff</div>
    <h1 class="page-title">Members</h1>
    <p class="hint mt-1 mb-4">Everyone here is a verified ZB employee. Open a profile to see their ideas.</p>
    @if ($requests->isNotEmpty())
        <section class="mb-5 rounded-2xl border border-line bg-surface p-4" aria-label="Executive access requests">
            <h2 class="font-display text-base font-bold">Executive access requests</h2>
            <ul class="mt-2 divide-y divide-line">
                @foreach ($requests as $req)
                    <li class="flex flex-wrap items-center gap-3 py-2.5" wire:key="rq{{ $req->id }}">
                        <x-avatar :user="$req" :size="36" />
                        <span class="min-w-0 grow"><b class="block truncate text-sm">{{ $req->name }}</b><small class="block truncate text-xs text-muted">{{ $req->email }}</small></span>
                        <button wire:click="declineExecutive({{ $req->id }})" class="btn btn-sm">Decline</button>
                        <button wire:click="approveExecutive({{ $req->id }})" class="btn btn-sm btn-primary">Approve</button>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
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
