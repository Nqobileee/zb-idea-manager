<div class="mx-auto max-w-[1080px]">
    <div class="mb-5 flex items-end justify-between gap-3">
        <div><div class="eyebrow">From executives</div><h1 class="page-title">Challenges</h1><p class="hint mt-1">Problems the business wants solved. Answer one with an idea.</p></div>
        @if (auth()->user()->is_admin)<a href="{{ route('challenges.create') }}" wire:navigate class="btn btn-primary btn-sm"><x-icon name="plus" :size="16" /> New challenge</a>@endif
    </div>
    <div class="grid gap-4 md:grid-cols-2">
        @forelse ($challenges as $c)
            <a href="{{ route('challenges.show', $c) }}" wire:navigate class="block rounded-[20px] border border-line p-5 hover:border-tint-2 hover:bg-surface">
                <div class="flex items-center gap-2 text-xs text-muted"><x-avatar :user="$c->owner" :size="22" /><b class="text-ink">{{ $c->owner->name }}<x-verified :user="$c->owner" /></b></div>
                <h2 class="mt-3 font-display text-[19px] leading-tight font-semibold tracking-tight">{{ $c->title }}</h2>
                <p class="mt-1.5 line-clamp-3 text-sm text-muted">{{ $c->brief }}</p>
                <div class="mt-4 flex flex-wrap gap-2"><span class="chip">{{ $c->ideas_count }} {{ Str::plural('idea', $c->ideas_count) }}</span>@if ($c->deadline)<span class="chip chip-ghost">{{ $c->daysLeft() }} days left · {{ $c->deadline->format('j M') }}</span>@endif</div>
            </a>
        @empty
            <p class="hint">No challenges yet.</p>
        @endforelse
    </div>
</div>
