@php
    $k = $idea->stageIndex();
    $tabs = ['updates' => ['Updates', $idea->updates->count()], 'pending' => ['Pending', $open->count()], 'comments' => ['Comments', $idea->comments->count()]];
@endphp
<div class="mx-auto max-w-[760px]">
    <a href="{{ route('pipeline') }}" wire:navigate class="hint mb-3 inline-flex items-center gap-1 hover:text-ink"><x-icon name="back" :size="16" /> Pipeline</a>

    <div class="flex flex-wrap items-center gap-2.5">
        <x-stage :status="$idea->status" />
        <span class="font-mono text-xs text-muted">{{ $idea->code }}</span>
        @if ($idea->approved)<span class="chip"><x-icon name="check" :size="13" /> Approved</span>@endif
    </div>
    <h1 class="mt-2 font-display text-[clamp(23px,4vw,32px)] leading-[1.1] font-bold tracking-tight text-balance">{{ $idea->title }}</h1>
    <div class="meta mt-2.5 text-[13px]">
        <a href="{{ route('profile', $idea->author) }}" wire:navigate class="flex min-w-0 items-center gap-2"><x-avatar :user="$idea->author" :size="24" /><b class="truncate font-semibold text-ink">{{ $idea->author->name }}<x-verified :user="$idea->author" /></b></a>
        <span class="dot"></span><span>{{ $idea->created_at->format('j M Y') }}</span>
        @if ($idea->challenge)<span class="dot"></span><a href="{{ route('challenges.show', $idea->challenge) }}" wire:navigate class="truncate text-brand hover:underline">{{ $idea->challenge->title }}</a>@endif
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-2">
        <a href="{{ route('ideas.show', $idea) }}" wire:navigate class="btn btn-sm"><x-icon name="file" :size="15" /> View original post</a>
        @if ($can)
            <label class="sr-only" for="mv">Move to stage</label>
            <select id="mv" wire:change="moveTo($event.target.value)" class="inp !h-[34px] !w-auto !rounded-full !px-3 text-[13px] font-semibold">
                <option value="">Move to…</option>
                @foreach (\App\Models\Idea::STATUSES as $s)@if ($s !== $idea->status)<option value="{{ $s }}">{{ $s }}</option>@endif @endforeach
            </select>
        @endif
    </div>

    <ol class="my-5 grid grid-cols-5 gap-1.5" aria-label="Stage {{ $k + 1 }} of 5: {{ $idea->status }}">
        @foreach (\App\Models\Idea::STATUSES as $n => $s)
            <li class="text-center"><span @class(['mb-1.5 block h-1.5 rounded-full', 'bg-brand' => $n <= $k, 'bg-line' => $n > $k])></span><small @class(['text-[11px] font-semibold', 'text-brand' => $n === $k, 'text-muted' => $n !== $k])>{{ $s }}</small></li>
        @endforeach
    </ol>

    <p class="max-w-[62ch] text-[15px] leading-relaxed text-ink-2">{{ $idea->summary }}</p>

    {{-- team: the author plus the members tagged on the project --}}
    <div class="mt-5 flex flex-wrap items-center gap-1.5">
        <span class="mr-1 text-[11px] font-semibold tracking-widest text-muted uppercase">Team</span>
        <a href="{{ route('profile', $idea->author) }}" wire:navigate class="flex items-center gap-1.5 rounded-full bg-surface py-0.5 pr-2.5 pl-0.5 text-xs font-semibold" title="Author"><x-avatar :user="$idea->author" :size="24" />{{ $idea->author->name }}</a>
        @foreach ($idea->members as $m)
            <span class="flex items-center gap-1.5 rounded-full bg-tint py-0.5 pr-1.5 pl-0.5 text-xs font-semibold text-brand" wire:key="mb{{ $m->id }}">
                <a href="{{ route('profile', $m) }}" wire:navigate class="flex items-center gap-1.5"><x-avatar :user="$m" :size="24" />{{ $m->name }}</a>
                @if ($can)<button wire:click="untagMember({{ $idea->id }}, {{ $m->id }})" class="grid size-4 place-items-center rounded-full hover:bg-white" aria-label="Remove {{ $m->first_name }}"><x-icon name="x" :size="11" /></button>@endif
            </span>
        @endforeach
        @if ($can)<button wire:click="openTagging({{ $idea->id }})" class="flex items-center gap-1 rounded-full border border-dashed border-tint-2 px-2.5 py-1 text-xs font-semibold text-brand hover:bg-tint"><x-icon name="plus" :size="12" /> Tag a member</button>@endif
    </div>
    @if ($taggingFor === $idea->id)
        <div class="mt-2 max-w-sm rounded-xl border border-line bg-white p-2 shadow-md">
            <input wire:model.live.debounce.200ms="tagSearch" class="inp !h-9 !rounded-lg text-[13px]" placeholder="Search members by name or department" autofocus>
            @forelse ($taggable as $p)
                <button wire:click="tagMember({{ $idea->id }}, {{ $p->id }})" class="mt-1 flex w-full items-center gap-2 rounded-lg p-1.5 text-left hover:bg-surface" wire:key="tg{{ $p->id }}"><x-avatar :user="$p" :size="28" /><span class="min-w-0"><b class="block truncate text-[13px]">{{ $p->name }}<x-verified :user="$p" /></b><small class="text-xs text-muted">{{ $p->dept ?: $p->title }}</small></span></button>
            @empty
                <p class="p-2 text-xs text-muted">No one else to tag.</p>
            @endforelse
        </div>
    @endif

    {{-- note --}}
    <div class="mt-5 rounded-2xl bg-surface p-3.5">
        <div class="mb-1.5 text-[11px] font-semibold tracking-widest text-muted uppercase">Note</div>
        @if ($can)
            <textarea wire:model="notes.{{ $idea->id }}" class="inp bg-white" rows="2" placeholder="Current focus, blockers, how others can help"></textarea>
            <div class="mt-2 flex justify-end"><button wire:click="saveNote({{ $idea->id }})" class="btn btn-sm">Save note</button></div>
        @else
            <p class="text-[14px] whitespace-pre-line text-ink-2">{{ $idea->note ?: 'No note yet.' }}</p>
        @endif
    </div>

    <div class="mt-6 flex border-b border-line">
        @foreach ($tabs as $key => [$label, $count])
            <button wire:click="$set('tab','{{ $key }}')" @class(['flex items-center gap-1.5 px-4 py-3 text-sm font-semibold', 'border-b-2 border-brand text-brand' => $tab === $key, 'text-muted' => $tab !== $key])>
                {{ $label }}<span @class(['rounded-full px-1.5 text-[11px] tabular-nums', 'bg-tint text-brand' => $tab === $key, 'bg-surface' => $tab !== $key, '!bg-[#fbeed3] !text-[#8a5a00]' => $key === 'pending' && $count > 0])>{{ $count }}</span>
            </button>
        @endforeach
    </div>

    {{-- UPDATES --}}
    @if ($tab === 'updates')
        @if ($can)
            <form wire:submit="postUpdate" class="mt-4">
                <label class="sr-only" for="up">Post an update</label>
                <textarea id="up" wire:model="updateText" class="inp" rows="2" placeholder="What changed? Share progress, a decision or a blocker."></textarea>
                @error('updateText')<p class="err">{{ $message }}</p>@enderror
                <div class="mt-2 flex justify-end"><button class="btn btn-primary btn-sm">Post update</button></div>
            </form>
        @endif
        <ol class="mt-4">
            @forelse ($idea->updates as $u)
                <li class="relative flex gap-3 pb-5 pl-0.5" wire:key="u{{ $u->id }}">
                    <span class="absolute top-8 bottom-0 left-[17px] w-px bg-line"></span>
                    @if ($u->kind === 'stage')
                        <span class="grid size-9 flex-none place-items-center rounded-full bg-tint text-brand"><x-icon name="board" :size="16" /></span>
                        <div class="min-w-0 pt-1.5 text-sm"><b>{{ $u->author->name }}</b> moved this from <x-stage :status="$u->from_stage" class="align-middle" /> to <x-stage :status="$u->to_stage" class="align-middle" /><div class="text-xs text-muted">{{ $u->created_at->diffForHumans() }}</div></div>
                    @else
                        <x-avatar :user="$u->author" :size="36" />
                        <div class="min-w-0"><div class="meta"><b class="font-semibold text-ink">{{ $u->author->name }}<x-verified :user="$u->author" /></b><span class="dot"></span><span>{{ $u->created_at->diffForHumans() }}</span></div><p class="mt-1 max-w-[65ch] whitespace-pre-line text-[15px] text-ink-2">{{ $u->body }}</p></div>
                    @endif
                </li>
            @empty
                <li class="py-10 text-center text-muted"><b class="block text-ink">No updates yet</b>{{ $can ? 'Post the first one above.' : 'Updates from the team will show here.' }}</li>
            @endforelse
        </ol>
    @endif

    {{-- PENDING --}}
    @if ($tab === 'pending')
        @if ($can)
            <form wire:submit="addTask" class="mt-4 flex flex-wrap items-start gap-2">
                <div class="min-w-0 grow basis-52"><label class="sr-only" for="tt">Pending item</label><input id="tt" wire:model="taskTitle" class="inp" placeholder="Add something still to do" maxlength="200" required>@error('taskTitle')<p class="err">{{ $message }}</p>@enderror</div>
                <div><label class="sr-only" for="td">Due date</label><input id="td" type="date" wire:model="taskDue" class="inp !w-auto"></div>
                <button class="btn btn-primary">Add</button>
            </form>
        @endif
        <ul class="mt-4 space-y-2">
            @forelse ($open as $t)
                <li class="flex items-center gap-3 rounded-xl border border-line p-3" wire:key="t{{ $t->id }}">
                    <button @if ($can) wire:click="toggleTask({{ $t->id }})" @else disabled @endif class="grid size-6 flex-none place-items-center rounded-full border-2 border-line hover:border-brand" aria-label="Mark done"></button>
                    <span class="min-w-0 grow text-[15px]">{{ $t->title }}@if ($t->due_date)<span @class(['ml-2 text-xs', 'font-semibold text-heart' => $t->isOverdue(), 'text-muted' => ! $t->isOverdue()])>{{ $t->isOverdue() ? 'Overdue · ' : 'Due ' }}{{ $t->due_date->format('j M') }}</span>@endif</span>
                    @if ($can)<button wire:click="deleteTask({{ $t->id }})" wire:confirm="Remove this item?" class="iconbtn !size-8" aria-label="Remove"><x-icon name="x" :size="15" /></button>@endif
                </li>
            @empty
                <li class="py-8 text-center text-muted"><b class="block text-ink">Nothing pending</b>{{ $done->isNotEmpty() ? 'Everything on the list is done.' : 'No pending items yet.' }}</li>
            @endforelse
        </ul>
        @if ($done->isNotEmpty())
            <h3 class="mt-6 mb-2 text-xs font-semibold tracking-widest text-muted uppercase">Done ({{ $done->count() }})</h3>
            <ul class="space-y-2">
                @foreach ($done as $t)
                    <li class="flex items-center gap-3 rounded-xl bg-surface p-3" wire:key="t{{ $t->id }}">
                        <button @if ($can) wire:click="toggleTask({{ $t->id }})" @else disabled @endif class="grid size-6 flex-none place-items-center rounded-full bg-brand text-white" aria-label="Mark not done"><x-icon name="check" :size="14" /></button>
                        <span class="min-w-0 grow text-[15px] text-muted line-through">{{ $t->title }}</span>
                        @if ($can)<button wire:click="deleteTask({{ $t->id }})" wire:confirm="Remove this item?" class="iconbtn !size-8" aria-label="Remove"><x-icon name="x" :size="15" /></button>@endif
                    </li>
                @endforeach
            </ul>
        @endif
    @endif

    {{-- COMMENTS --}}
    @if ($tab === 'comments')
        <form wire:submit="addComment" class="mt-4 flex items-start gap-3">
            <x-avatar :user="$me" :size="36" />
            <div class="grow">
                <label class="sr-only" for="cm">Comment</label>
                <textarea id="cm" wire:model="comment" class="inp" rows="2" placeholder="Add a comment or a question" required></textarea>
                @error('comment')<p class="err">{{ $message }}</p>@enderror
                <div class="mt-2 flex justify-end"><button class="btn btn-primary btn-sm">Comment</button></div>
            </div>
        </form>
        @forelse ($idea->comments as $c)
            <div class="flex gap-3 border-b border-line py-4" wire:key="c{{ $c->id }}">
                <x-avatar :user="$c->author" :size="36" />
                <div class="min-w-0"><div class="meta mb-1"><b class="font-semibold text-ink">{{ $c->author->name }}<x-verified :user="$c->author" /></b><span class="dot"></span><span>{{ $c->created_at->diffForHumans(null, true, true) }}</span></div><p class="max-w-[65ch] text-ink-2">{{ $c->body }}</p></div>
            </div>
        @empty
            <p class="hint py-8 text-center">No comments yet.</p>
        @endforelse
    @endif
</div>
