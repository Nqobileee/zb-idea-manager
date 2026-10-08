@props(['idea', 'me'])
@php
    $liked = $idea->likers->contains('id', $me->id);
    $saved = $idea->savers->contains('id', $me->id);
    $image = $idea->files->firstWhere('kind', 'image');
    $extra = max(0, $idea->images_count - 1);
@endphp
<article class="border-b border-line py-5.5" wire:key="idea-{{ $idea->id }}">
    <a href="{{ route('ideas.show', $idea) }}" wire:navigate class="block">
        <div class="flex items-center justify-between gap-2">
            <div class="meta">
                <x-avatar :user="$idea->author" :size="26" />
                <b class="truncate font-semibold whitespace-nowrap text-ink">{{ $idea->author->name }}<x-verified :user="$idea->author" /></b>
                <span class="dot"></span><span class="truncate">{{ $idea->author->dept }}</span>
                <span class="dot"></span><span class="flex-none">{{ $idea->created_at->diffForHumans(null, true, true) }}</span>
            </div>
            <x-stage :status="$idea->status" />
        </div>
        <h2 class="mt-2.5 mb-1 font-display text-[17px] leading-[1.3] font-semibold tracking-tight text-balance">{{ $idea->title }}</h2>
        <p class="line-clamp-2 text-sm leading-normal text-muted">{{ $idea->summary }}</p>
        @if ($image)
            <div class="relative mt-3 overflow-hidden rounded-[14px] border border-line bg-tint">
                <img src="{{ $image->url }}" alt="Image for {{ $idea->title }}" loading="lazy" class="block max-h-[340px] w-full object-cover">
                @if ($extra)<span class="absolute right-2.5 bottom-2.5 rounded-full bg-black/65 px-2.5 py-0.5 text-xs font-semibold text-white">+{{ $extra }}</span>@endif
            </div>
        @endif
        <div class="mt-3 flex flex-wrap gap-2 empty:hidden">
            @if ($idea->challenge)<span class="chip"><x-icon name="flag" :size="13" /><span class="truncate">{{ $idea->challenge->title }}</span></span>@endif
            @if ($idea->docs_count)<span class="chip chip-ghost"><x-icon name="clip" :size="13" /> {{ $idea->docs_count }} {{ Str::plural('document', $idea->docs_count) }}</span>@endif
            @if ($idea->approved)<span class="chip"><x-icon name="check" :size="13" /> Approved</span>@endif
        </div>
    </a>
    <x-idea-actions :idea="$idea" :liked="$liked" :saved="$saved" />
</article>
