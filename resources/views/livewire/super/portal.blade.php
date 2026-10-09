<div class="mx-auto max-w-[1180px] px-4 py-6 md:px-8">
    <header class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="eyebrow"><x-icon name="shield" :size="13" /> Super admin</div>
            <h1 class="page-title">Control room</h1>
        </div>
        <form method="POST" action="{{ route('super.logout') }}">@csrf<button class="btn btn-sm">Sign out</button></form>
    </header>

    <nav class="mb-5 flex gap-1 overflow-x-auto rounded-full bg-surface p-1" role="tablist" aria-label="Sections">
        @foreach (['overview' => 'Overview', 'members' => 'Members', 'ideas' => 'Ideas', 'challenges' => 'Challenges'] as $key => $label)
            <button role="tab" wire:click="setTab('{{ $key }}')" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" @class(['flex-none rounded-full px-4 py-1.5 text-[13px] font-semibold', 'bg-white text-brand shadow-sm' => $tab === $key, 'text-muted' => $tab !== $key])>{{ $label }}</button>
        @endforeach
    </nav>

    @if ($notice)<p class="mb-4 rounded-xl bg-tint px-4 py-2.5 text-sm font-semibold text-brand" role="status">{{ $notice }}</p>@endif

    @if ($tab === 'overview')
        <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
            @foreach ($stats as $label => $n)
                <div class="rounded-2xl border border-line p-4"><b class="block font-display text-2xl tabular-nums">{{ $n }}</b><span class="text-xs text-muted">{{ $label }}</span></div>
            @endforeach
        </div>
        <div class="mt-6 grid gap-6 md:grid-cols-2">
            <section>
                <h2 class="mb-2 font-display text-base font-bold">Ideas by stage</h2>
                <ul class="divide-y divide-line rounded-2xl border border-line">@foreach ($byStage as $s => $n)<li class="flex justify-between px-4 py-2.5 text-sm"><span>{{ $s }}</span><b class="tabular-nums">{{ $n }}</b></li>@endforeach</ul>
            </section>
            <section>
                <h2 class="mb-2 font-display text-base font-bold">Newest ideas</h2>
                <ul class="divide-y divide-line rounded-2xl border border-line">
                    @forelse ($recent as $i)<li class="px-4 py-2.5 text-sm"><b class="block truncate">{{ $i->title }}</b><span class="text-xs text-muted">{{ $i->author->name }} · {{ $i->status }} · {{ $i->is_public ? 'Public' : 'Private' }}</span></li>@empty<li class="px-4 py-3 text-sm text-muted">No ideas yet.</li>@endforelse
                </ul>
            </section>
        </div>
    @elseif ($tab === 'members')
        <input wire:model.live.debounce.250ms="search" type="search" class="inp mb-4 max-w-md" placeholder="Search by name, email or number" aria-label="Search members">
        <div class="overflow-x-auto rounded-2xl border border-line">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead class="bg-surface text-xs tracking-wide text-muted uppercase"><tr><th class="px-4 py-2.5">Member</th><th class="px-4 py-2.5">Role</th><th class="px-4 py-2.5">WhatsApp</th><th class="px-4 py-2.5">Ideas</th><th class="px-4 py-2.5 text-right">Actions</th></tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($members as $m)
                        <tr wire:key="m{{ $m->id }}">
                            <td class="px-4 py-2.5"><b class="block">{{ $m->name }}</b><span class="text-xs text-muted">{{ $m->email }}</span></td>
                            <td class="px-4 py-2.5"><span @class(['chip', 'chip-ghost' => ! $m->is_admin])>{{ $m->role_label }}</span></td>
                            <td class="px-4 py-2.5 text-xs text-muted">{{ $m->phone ?: 'Not linked' }}</td>
                            <td class="px-4 py-2.5 tabular-nums">{{ $m->ideas_count }}</td>
                            <td class="px-4 py-2.5">
                                <div class="flex justify-end gap-2">
                                    <button wire:click="toggleAdmin({{ $m->id }})" wire:confirm="{{ $m->is_admin ? 'Remove admin rights from '.$m->name.'?' : 'Make '.$m->name.' an Executive admin?' }}" class="btn btn-sm">{{ $m->is_admin ? 'Remove admin' : 'Make admin' }}</button>
                                    <button wire:click="removeMember({{ $m->id }})" wire:confirm="Remove {{ $m->name }}? Their ideas, comments and likes are deleted too. This cannot be undone." class="btn btn-sm !text-heart">Remove</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-muted">No members found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @elseif ($tab === 'ideas')
        <input wire:model.live.debounce.250ms="search" type="search" class="inp mb-4 max-w-md" placeholder="Search ideas" aria-label="Search ideas">
        <div class="overflow-x-auto rounded-2xl border border-line">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead class="bg-surface text-xs tracking-wide text-muted uppercase"><tr><th class="px-4 py-2.5">Idea</th><th class="px-4 py-2.5">Stage</th><th class="px-4 py-2.5">Who can see</th><th class="px-4 py-2.5">Likes</th><th class="px-4 py-2.5 text-right">Actions</th></tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($ideas as $i)
                        <tr wire:key="i{{ $i->id }}">
                            <td class="px-4 py-2.5"><b class="block">{{ $i->title }}</b><span class="text-xs text-muted">{{ $i->author->name }} · {{ $i->code }}</span></td>
                            <td class="px-4 py-2.5">{{ $i->status }}@if ($i->approved) <span class="chip ml-1">Approved</span>@endif</td>
                            <td class="px-4 py-2.5">{{ $i->is_public ? 'Public' : 'Private' }}</td>
                            <td class="px-4 py-2.5 tabular-nums">{{ $i->likers_count }}</td>
                            <td class="px-4 py-2.5 text-right"><button wire:click="removeIdea({{ $i->id }})" wire:confirm="Remove this idea and its comments? This cannot be undone." class="btn btn-sm !text-heart">Remove</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-muted">No ideas found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <div class="overflow-x-auto rounded-2xl border border-line">
            <table class="w-full min-w-[560px] text-left text-sm">
                <thead class="bg-surface text-xs tracking-wide text-muted uppercase"><tr><th class="px-4 py-2.5">Challenge</th><th class="px-4 py-2.5">Set by</th><th class="px-4 py-2.5">Closes</th><th class="px-4 py-2.5">Ideas</th></tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($challenges as $c)
                        <tr wire:key="c{{ $c->id }}"><td class="px-4 py-2.5 font-semibold">{{ $c->title }}</td><td class="px-4 py-2.5">{{ $c->owner?->name }}</td><td class="px-4 py-2.5">{{ $c->deadline?->format('j M Y') ?? 'No deadline' }}</td><td class="px-4 py-2.5 tabular-nums">{{ $c->ideas_count }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-muted">No challenges yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
