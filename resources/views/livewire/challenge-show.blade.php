<div class="mx-auto max-w-[700px]">
    <div class="eyebrow"><x-icon name="flag" :size="13" /> Executive challenge</div>
    <h1 class="font-display text-[clamp(24px,4vw,34px)] leading-tight font-bold tracking-tight text-balance">{{ $challenge->title }}</h1>
    <p class="mt-3 text-base leading-relaxed text-ink-2">{{ $challenge->brief }}</p>
    <div class="mt-4 flex flex-wrap items-center gap-2">
        <span class="chip chip-ghost">Set by {{ $challenge->owner->name }}</span>
        @if ($challenge->deadline)<span class="chip chip-ghost">Deadline {{ $challenge->deadline->format('j M Y') }} · {{ $challenge->daysLeft() }} days left</span>@endif
        @foreach ($challenge->keywords as $k)<span class="chip">{{ $k }}</span>@endforeach
    </div>
    <div class="mt-5 flex flex-wrap gap-2">
        <a href="{{ route('ideas.create', ['challenge' => $challenge->id]) }}" wire:navigate class="btn btn-primary"><x-icon name="plus" :size="16" /> Answer this challenge</a>
        @if ($me->is_admin)
            <a href="{{ route('challenges.edit', $challenge) }}" wire:navigate class="btn"><x-icon name="edit" :size="15" /> Edit</a>
            <button wire:click="deleteChallenge" wire:confirm="Delete this challenge? The ideas that answered it are kept." class="btn !text-heart"><x-icon name="x" :size="15" /> Delete</button>
        @endif
    </div>
    <h2 class="mt-8 font-display text-base font-semibold">Ideas ({{ $ideas->count() }})</h2>
    @forelse ($ideas as $idea)<x-idea-card :idea="$idea" :me="$me" />@empty<p class="hint mt-2">No ideas yet. Be the first.</p>@endforelse
</div>
