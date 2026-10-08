<div class="mx-auto max-w-[700px]">
    <div class="h-32 rounded-[20px]" style="background:radial-gradient(rgba(255,255,255,.14) 1.2px,transparent 1.3px) 0 0/14px 14px,linear-gradient(120deg,var(--color-brand),var(--color-accent))"></div>
    <div class="-mt-11 flex items-end justify-between px-4">
        <span class="rounded-full border-4 border-white"><x-avatar :user="$user" :size="92" /></span>
        <div class="pb-2">
            @if ($mine)<a href="{{ route('profile.edit') }}" wire:navigate class="btn btn-sm"><x-icon name="edit" :size="15" /> Edit profile</a>
            @else<button wire:click="message" class="btn btn-primary btn-sm"><x-icon name="chat" :size="15" /> Message</button>@endif
        </div>
    </div>
    <h1 class="mt-3.5 flex flex-wrap items-center gap-1 font-display text-3xl font-bold tracking-tight">{{ $user->name }}<x-verified :user="$user" /></h1>
    <p class="text-muted">{{ $user->title }} · {{ $user->dept }}@if ($user->joined) · Joined {{ $user->joined }}@endif</p>
    @if ($user->bio)<p class="mt-3 max-w-[60ch] text-ink-2">{{ $user->bio }}</p>@endif

    <div class="mt-6 flex border-b border-line">
        @foreach ($tabs as $k => $l)
            <button wire:click="$set('tab','{{ $k }}')" @class(['px-4 py-3 text-sm font-semibold', 'border-b-2 border-brand text-brand' => $tab === $k, 'text-muted' => $tab !== $k])>{{ $l }}</button>
        @endforeach
    </div>
    @if ($tab === 'challenges')
        <div class="grid gap-4 py-5 md:grid-cols-2">
            @forelse ($challenges as $c)
                <a href="{{ route('challenges.show', $c) }}" wire:navigate wire:key="ch{{ $c->id }}" class="block rounded-[20px] border border-line p-5 hover:border-tint-2 hover:bg-surface">
                    <h2 class="font-display text-[19px] leading-tight font-semibold tracking-tight">{{ $c->title }}</h2>
                    <p class="mt-1.5 line-clamp-3 text-sm text-muted">{{ $c->brief }}</p>
                    <div class="mt-4 flex flex-wrap gap-2"><span class="chip">{{ $c->ideas_count }} {{ Str::plural('idea', $c->ideas_count) }}</span>@if ($c->deadline)<span class="chip chip-ghost">{{ $c->daysLeft() }} days left · {{ $c->deadline->format('j M') }}</span>@endif</div>
                </a>
            @empty
                <div class="py-8 text-center text-muted md:col-span-2"><p class="hint">{{ $mine ? 'You have not set a challenge yet.' : 'No challenges yet.' }}</p>@if ($mine)<a href="{{ route('challenges.create') }}" wire:navigate class="btn btn-primary mt-4"><x-icon name="plus" :size="16" /> New challenge</a>@endif</div>
            @endforelse
        </div>
    @else
        @forelse ($ideas as $idea)<x-idea-card :idea="$idea" :me="$me" />@empty<p class="hint py-8 text-center">Nothing here yet.</p>@endforelse
    @endif
</div>
