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
        @if ($created)
            <section class="mb-5 rounded-2xl border border-brand bg-tint p-4" role="status">
                <h2 class="font-display text-base font-bold">{{ $created['name'] }} was added ({{ $created['role'] }})</h2>
                <p class="mt-1 text-sm">Give them these sign-in details. The password is shown <b>only now</b> and they must change it the first time they sign in.</p>
                <dl class="mt-3 grid gap-1 text-sm sm:grid-cols-[110px_1fr]">
                    <dt class="text-muted">Sign in at</dt><dd class="break-all font-mono">{{ $created['url'] }}</dd>
                    <dt class="text-muted">Email or phone</dt><dd class="font-mono">{{ $created['login'] }}</dd>
                    <dt class="text-muted">Password</dt><dd class="font-mono font-bold">{{ $created['password'] }}</dd>
                </dl>
                <button wire:click="dismissCreated" class="btn btn-sm mt-3">Done</button>
            </section>
        @endif
        <details class="mb-5 rounded-2xl border border-line" @if ($errors->any()) open @endif>
            <summary class="cursor-pointer px-4 py-3 font-display text-base font-bold">Add a member</summary>
            <form wire:submit="addMember" class="grid gap-3 border-t border-line p-4 sm:grid-cols-2">
                <div>
                    <label class="lbl" for="nn">Full name</label>
                    <input id="nn" wire:model="newName" class="inp" required>
                    @error('newName')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="lbl" for="nr">Role</label>
                    <select id="nr" wire:model="newRole" class="inp"><option value="general">General member</option><option value="admin">Executive admin</option></select>
                </div>
                <div>
                    <label class="lbl" for="ne">Email <span class="font-normal text-muted">(optional if a phone is given)</span></label>
                    <input id="ne" type="email" wire:model="newEmail" class="inp" autocomplete="off">
                    @error('newEmail')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="lbl" for="np">WhatsApp / phone <span class="font-normal text-muted">(optional if an email is given)</span></label>
                    <input id="np" wire:model="newPhone" class="inp" placeholder="077 123 4567" autocomplete="off">
                    @error('newPhone')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="lbl" for="npw">Password <span class="font-normal text-muted">(leave empty to generate one)</span></label>
                    <input id="npw" type="text" wire:model="newPassword" class="inp" autocomplete="off" placeholder="At least 8 characters">
                    @error('newPassword')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2"><button class="btn btn-primary" wire:loading.attr="disabled">Add member</button></div>
            </form>
        </details>
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
                                <div class="flex flex-wrap justify-end gap-2">
                                    <button wire:click="editContact({{ $m->id }})" class="btn btn-sm">Edit contact</button>
                                    <button wire:click="toggleAdmin({{ $m->id }})" wire:confirm="{{ $m->is_admin ? 'Remove admin rights from '.$m->name.'?' : 'Make '.$m->name.' an Executive admin?' }}" class="btn btn-sm">{{ $m->is_admin ? 'Remove admin' : 'Make admin' }}</button>
                                    <button wire:click="removeMember({{ $m->id }})" wire:confirm="Remove {{ $m->name }}? Their ideas, comments and likes are deleted too. This cannot be undone." class="btn btn-sm !text-heart">Remove</button>
                                </div>
                            </td>
                        </tr>
                        @if ($editingId === $m->id)
                            <tr wire:key="me{{ $m->id }}" class="bg-surface">
                                <td colspan="5" class="px-4 py-3">
                                    <form wire:submit="saveContact" class="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                                        <div>
                                            <label class="lbl" for="ee{{ $m->id }}">Email</label>
                                            <input id="ee{{ $m->id }}" type="email" wire:model="editEmail" class="inp" autocomplete="off" placeholder="none">
                                            @error('editEmail')<p class="err">{{ $message }}</p>@enderror
                                        </div>
                                        <div>
                                            <label class="lbl" for="ep{{ $m->id }}">WhatsApp / phone</label>
                                            <input id="ep{{ $m->id }}" wire:model="editPhone" class="inp" autocomplete="off" placeholder="077 123 4567">
                                            @error('editPhone')<p class="err">{{ $message }}</p>@enderror
                                        </div>
                                        <div class="flex gap-2"><button class="btn btn-primary btn-sm" wire:loading.attr="disabled">Save</button><button type="button" wire:click="cancelEdit" class="btn btn-sm">Cancel</button></div>
                                    </form>
                                    <p class="hint mt-2">Link or change the email and phone number for {{ $m->name }}. Clear a field to remove it, but keep at least one.</p>
                                </td>
                            </tr>
                        @endif
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
