<div class="mx-auto max-w-[700px]">
    <div class="mb-4 flex items-end justify-between gap-3">
        <div><div class="eyebrow">Notifications</div><h1 class="page-title">Activity</h1></div>
        @if ($unread)<button wire:click="markAllRead" class="btn btn-sm">Mark all read</button>@endif
    </div>
    @forelse ($items as $a)
        @php
            $url = $a->idea_id ? (in_array($a->type, ['stage', 'update', 'tag'], true) ? route('projects.show', $a->idea_id) : route('ideas.show', $a->idea_id)) : ($a->challenge_id ? route('challenges.show', $a->challenge_id) : '#');
            $text = match ($a->type) {
                'approval' => 'approved your idea',
                'comment' => 'commented on',
                'like' => 'liked',
                'stage' => 'moved to '.$a->note.':',
                'update' => 'posted an update on',
                'tag' => 'tagged you on the project',
                'challenge' => 'posted a new challenge:',
                default => 'updated',
            };
            $target = $a->idea?->title ?? $a->challenge?->title;
        @endphp
        <a href="{{ $url }}" wire:navigate wire:key="a{{ $a->id }}" @class(['flex gap-3 border-b border-line py-4', 'bg-tint/40' => in_array($a->id, $unread)])>
            <x-avatar :user="$a->actor ?? auth()->user()" :size="40" />
            <div class="min-w-0">
                <p class="text-[15px]"><b>{{ $a->actor?->name }}<x-verified :user="$a->actor" /></b> {{ $text }} <b>{{ $target }}</b></p>
                @if ($a->note && in_array($a->type, ['comment', 'approval']))<p class="mt-1 line-clamp-3 text-sm text-muted">{{ $a->note }}</p>@endif
                <small class="text-xs text-muted">{{ $a->created_at->diffForHumans() }}</small>
            </div>
        </a>
    @empty
        <div class="py-16 text-center text-muted"><b class="block text-ink">Nothing yet</b>Likes, comments and approvals will show up here.</div>
    @endforelse
</div>
