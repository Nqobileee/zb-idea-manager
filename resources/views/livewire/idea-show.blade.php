@php
    $k = $idea->stageIndex();
    $liked = $idea->likers->contains('id', $me->id);
    $saved = $idea->savers->contains('id', $me->id);
    $images = $idea->files->where('kind', 'image');
    $docs = $idea->files->where('kind', 'doc');
@endphp
<div class="mx-auto max-w-[700px]">
    <div class="mb-3 flex flex-wrap items-center gap-2.5">
        <x-stage :status="$idea->status" />
        <span class="font-mono text-xs text-muted">{{ $idea->code }}</span>
        @if ($idea->approved)<span class="chip"><x-icon name="check" :size="13" /> Approved by {{ $idea->approver?->name }}</span>@endif
        @if ($idea->source === 'whatsapp')<span class="chip chip-ghost">Posted from WhatsApp</span>@endif
        <a href="{{ route('projects.show', $idea) }}" wire:navigate class="btn btn-sm"><x-icon name="board" :size="15" /> Open project</a>
        @if ($idea->canBeManagedBy($me))
            <span class="ml-auto flex gap-2">
                <a href="{{ route('ideas.edit', $idea) }}" wire:navigate class="btn btn-sm"><x-icon name="edit" :size="15" /> Edit</a>
                <button wire:click="deleteIdea" wire:confirm="Delete this idea for good? Its comments and likes will be deleted too." class="btn btn-sm !text-heart"><x-icon name="x" :size="15" /> Delete</button>
            </span>
        @endif
    </div>
    <h1 class="font-display text-[clamp(23px,4vw,34px)] leading-[1.1] font-bold tracking-tight text-balance">{{ $idea->title }}</h1>
    <p class="mt-3.5 mb-6 max-w-[62ch] text-base leading-relaxed text-ink-2">{{ $idea->summary }}</p>

    <div class="flex items-center gap-3 border-y border-line py-3">
        <a href="{{ route('profile', $idea->author) }}" wire:navigate class="flex min-w-0 items-center gap-2.5">
            <x-avatar :user="$idea->author" :size="44" />
            <span class="min-w-0"><b class="block truncate">{{ $idea->author->name }}<x-verified :user="$idea->author" /></b><small class="block truncate text-xs text-muted">{{ $idea->author->title }} · {{ $idea->author->dept }}</small></span>
        </a>
        <span class="ml-auto flex-none text-[13px] text-muted">{{ $idea->created_at->format('j M Y') }}</span>
    </div>

    <ol class="my-6 grid grid-cols-5 gap-1.5" aria-label="Stage {{ $k + 1 }} of 5: {{ $idea->status }}">
        @foreach (\App\Models\Idea::STATUSES as $n => $s)
            <li class="text-center"><span @class(['mb-1.5 block h-1.5 rounded-full', 'bg-brand' => $n <= $k, 'bg-line' => $n > $k])></span><small @class(['text-[11px] font-semibold', 'text-brand' => $n === $k, 'text-muted' => $n !== $k])>{{ $s }}</small></li>
        @endforeach
    </ol>

    @if ($idea->challenge)
        <a href="{{ route('challenges.show', $idea->challenge) }}" wire:navigate class="mb-6 flex items-center gap-3 rounded-2xl bg-tint p-4 text-brand"><x-icon name="flag" :size="20" /><span><small class="block text-xs">Response to executive challenge</small><b>{{ $idea->challenge->title }}</b></span></a>
    @endif

    @if ($images->isNotEmpty())
        <div class="my-5 grid gap-3">@foreach ($images as $img)<img src="{{ $img->url }}" alt="{{ $img->name }}" class="w-full rounded-[14px] border border-line">@endforeach</div>
    @endif

    <div class="space-y-4 text-[15px] leading-[1.7] text-ink-2">@foreach ($idea->paragraphs() as $p)<p class="max-w-[65ch]">{{ $p }}</p>@endforeach</div>

    <h3 class="mt-8 mb-3 font-display text-base font-semibold">Supporting documents</h3>
    @forelse ($docs as $d)
        <div class="mb-2 flex items-center gap-3.5 rounded-[14px] border border-line p-3">
            <x-file-badge :file="$d" />
            <span class="min-w-0 grow"><b class="block truncate">{{ $d->name }}</b><small class="text-muted">{{ $d->size }}</small></span>
            @if ($d->url)<a class="btn btn-sm" href="{{ $d->url }}" download>Download</a>@else<x-icon name="file" :size="18" class="text-muted" />@endif
        </div>
    @empty
        <p class="hint">No documents attached yet.</p>
    @endforelse

    <div class="mt-7 border-y border-line py-1"><x-idea-actions :idea="$idea" :liked="$liked" :saved="$saved" /></div>

    @if ($me->is_admin)
        <section class="mt-6 rounded-[18px] border border-tint-2 bg-tint p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div><div class="eyebrow !mb-0">Executive review</div><p class="hint mt-1">{{ $idea->approved ? 'Approved '.$idea->approved_at?->diffForHumans() : 'Not approved yet.' }}</p></div>
                <button wire:click="messageAuthor" class="btn btn-sm"><x-icon name="chat" :size="15" /> Message {{ $idea->author->first_name }}</button>
            </div>
            <div class="mt-3.5 flex flex-wrap items-center gap-2.5">
                <label class="lbl !mb-0" for="st">Stage</label>
                <select id="st" wire:model="status" wire:change="changeStatus" class="inp !h-9 !w-auto bg-white text-[13px]">@foreach (\App\Models\Idea::STATUSES as $s)<option>{{ $s }}</option>@endforeach</select>
            </div>
            @unless ($idea->approved)
                <div class="mt-3.5 space-y-2.5">
                    <textarea wire:model="note" class="inp bg-white" rows="2" placeholder="Optional note for the author"></textarea>
                    <button wire:click="approve" wire:confirm="Approve this idea and email the author?" class="btn btn-primary btn-sm"><x-icon name="check" :size="15" /> Approve</button>
                </div>
            @endunless
        </section>
    @endif

    <h3 id="comments" class="mt-8 mb-3 font-display text-base font-semibold">Comments ({{ $idea->comments->count() }})</h3>
    <form wire:submit="addComment" class="flex items-start gap-3">
        <x-avatar :user="$me" :size="36" />
        <div class="grow">
            <label class="sr-only" for="cm">Comment</label>
            <textarea id="cm" wire:model="comment" class="inp" rows="2" placeholder="Add a comment or a question" required></textarea>
            @error('comment')<p class="err">{{ $message }}</p>@enderror
            <div class="mt-2 flex justify-end"><button class="btn btn-primary btn-sm">Comment</button></div>
        </div>
    </form>
    @foreach ($idea->comments as $c)
        <div class="flex gap-3 border-b border-line py-4" wire:key="c{{ $c->id }}">
            <x-avatar :user="$c->author" :size="36" />
            <div class="min-w-0"><div class="meta mb-1"><b class="font-semibold text-ink">{{ $c->author->name }}<x-verified :user="$c->author" /></b><span class="dot"></span><span>{{ $c->created_at->diffForHumans(null, true, true) }}</span></div><p class="max-w-[65ch] text-ink-2">{{ $c->body }}</p></div>
        </div>
    @endforeach
</div>
