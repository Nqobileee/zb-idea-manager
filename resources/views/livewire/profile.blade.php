<div class="mx-auto max-w-[700px]">
    <div class="h-32 rounded-[20px]" style="background:radial-gradient(rgba(255,255,255,.14) 1.2px,transparent 1.3px) 0 0/14px 14px,linear-gradient(120deg,var(--color-brand),#2f6f5e)"></div>
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
        @foreach (['ideas' => $mine ? 'My ideas' : 'Ideas'] + ($mine ? ['saved' => 'Saved'] : []) as $k => $l)
            <button wire:click="$set('tab','{{ $k }}')" @class(['px-4 py-3 text-sm font-semibold', 'border-b-2 border-brand text-brand' => $tab === $k, 'text-muted' => $tab !== $k])>{{ $l }}</button>
        @endforeach
    </div>
    @forelse ($ideas as $idea)<x-idea-card :idea="$idea" :me="$me" />@empty<p class="hint py-8 text-center">Nothing here yet.</p>@endforelse
</div>
