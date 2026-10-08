@props(['idea', 'liked' => false, 'saved' => false])
<div class="mt-2 -ml-2.5 flex items-center">
    <button wire:click="like({{ $idea->id }})" @class(['act', '!text-heart' => $liked]) aria-pressed="{{ $liked ? 'true' : 'false' }}" aria-label="Like"><x-icon name="heart" :size="19" :fill="$liked" /><span>{{ $idea->likers_count ?? $idea->likers->count() }}</span></button>
    <a href="{{ route('ideas.show', $idea) }}#comments" wire:navigate class="act" aria-label="Comments"><x-icon name="comment" :size="19" /><span>{{ $idea->comments_count ?? $idea->comments->count() }}</span></a>
    <button wire:click="share({{ $idea->id }})" class="act" aria-label="Share"
            x-data x-on:share-idea.window="if ($event.detail.url && navigator.share) navigator.share({ title: $event.detail.title, url: $event.detail.url }).catch(() => {}); else navigator.clipboard?.writeText($event.detail.url)">
        <x-icon name="share" :size="19" /><span>{{ $idea->shares }}</span></button>
    <button wire:click="save({{ $idea->id }})" @class(['act ml-auto', '!text-brand' => $saved]) aria-pressed="{{ $saved ? 'true' : 'false' }}"><x-icon name="save" :size="19" :fill="$saved" /><span>{{ $saved ? 'Saved' : 'Save' }}</span></button>
</div>
