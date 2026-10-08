<div class="mx-auto max-w-[1080px]">
    <div class="mb-5 flex items-end justify-between gap-3">
        <div><div class="eyebrow">{{ $me->is_admin ? 'Executive' : 'From executives' }}</div><h1 class="page-title">{{ $me->is_admin && $tab === 'mine' ? 'My challenges' : 'Challenges' }}</h1><p class="hint mt-1">{{ $me->is_admin ? 'The challenges you set. Edit, delete or open one to see the ideas it received.' : 'Problems the business wants solved. Answer one with an idea.' }}</p></div>
        @if (auth()->user()->is_admin)<a href="{{ route('challenges.create') }}" wire:navigate class="btn btn-primary btn-sm"><x-icon name="plus" :size="16" /> New challenge</a>@endif
    </div>
    @if ($me->is_admin)
        <div class="mb-4 flex border-b border-line">
            @foreach (['mine' => 'My challenges', 'all' => 'All challenges'] as $k => $l)
                <button wire:click="$set('tab','{{ $k }}')" @class(['px-4 py-3 text-sm font-semibold', 'border-b-2 border-brand text-brand' => $tab === $k, 'text-muted' => $tab !== $k])>{{ $l }}</button>
            @endforeach
        </div>
    @endif
    <div class="grid gap-4 md:grid-cols-2">
        @forelse ($challenges as $c)
            <a href="{{ route('challenges.show', $c) }}" wire:navigate class="block rounded-[20px] border border-line p-5 hover:border-tint-2 hover:bg-surface">
                <div class="flex items-center gap-2 text-xs text-muted"><x-avatar :user="$c->owner" :size="22" /><b class="text-ink">{{ $c->owner->name }}<x-verified :user="$c->owner" /></b></div>
                <h2 class="mt-3 font-display text-[19px] leading-tight font-semibold tracking-tight">{{ $c->title }}</h2>
                <p class="mt-1.5 line-clamp-3 text-sm text-muted">{{ $c->brief }}</p>
                <div class="mt-4 flex flex-wrap gap-2"><span class="chip">{{ $c->ideas_count }} {{ Str::plural('idea', $c->ideas_count) }}</span>@if ($c->deadline)<span class="chip chip-ghost">{{ $c->daysLeft() }} days left · {{ $c->deadline->format('j M') }}</span>@endif</div>
            </a>
        @empty
            <div class="py-12 text-center text-muted md:col-span-2"><b class="block text-ink">{{ $me->is_admin && $tab === 'mine' ? 'You have not set a challenge yet' : 'No challenges yet' }}</b>@if ($me->is_admin)<a href="{{ route('challenges.create') }}" wire:navigate class="btn btn-primary mt-4"><x-icon name="plus" :size="16" /> New challenge</a>@endif</div>
        @endforelse
    </div>
</div>
