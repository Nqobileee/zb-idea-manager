@php
    $hint = [
        'Idea' => 'Proposed, not started',
        'Prototype' => 'Being built or sketched',
        'Demo' => 'Ready to show people',
        'Pilot' => 'Running with real users',
        'Launched' => 'Live across ZB',
    ];
    $dot = ['Idea' => 'bg-[#b9c6bf]', 'Prototype' => 'bg-[#d99a2b]', 'Demo' => 'bg-[#3d74c0]', 'Pilot' => 'bg-[#7a5bbd]', 'Launched' => 'bg-brand'];
@endphp
<div class="mx-auto max-w-[1500px]" x-data="{ dragging: null, over: null }">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <div class="eyebrow"><x-icon name="board" :size="13" /> From ideation to shipping</div>
            <h1 class="page-title">Pipeline</h1>
            <p class="hint mt-1">Where every project stands. Open one for its updates, pending work and comments.</p>
        </div>
        <div class="flex w-full min-w-0 items-center gap-2 md:w-auto">
            <select wire:model.live="challenge" class="inp !h-9 min-w-0 max-w-full grow !rounded-full !px-3 text-[13px] md:!w-auto md:grow-0" aria-label="Challenge">
                <option value="all">All challenges</option>
                @foreach ($challenges as $c)<option value="{{ $c->id }}">{{ $c->title }}</option>@endforeach
            </select>
            <label @class(['flex flex-none cursor-pointer items-center gap-1.5 rounded-full border px-3 py-1.5 text-[13px] font-semibold', 'border-brand bg-tint text-brand' => $mine, 'border-line text-muted' => ! $mine])>
                <input type="checkbox" class="sr-only" wire:model.live="mine"> Mine
            </label>
        </div>
    </div>

    {{-- the two views --}}
    <div class="mb-4 inline-flex rounded-full bg-surface p-1" role="tablist" aria-label="Pipeline view">
        @foreach (['board' => ['Board', 'board'], 'projects' => ['Projects', 'file']] as $key => [$label, $icon])
            <button role="tab" wire:click="$set('view','{{ $key }}')" aria-selected="{{ $view === $key ? 'true' : 'false' }}" @class(['inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 text-[13px] font-semibold', 'bg-white text-brand shadow-sm' => $view === $key, 'text-muted' => $view !== $key])><x-icon :name="$icon" :size="14" /> {{ $label }}</button>
        @endforeach
    </div>

    {{-- ===================== BOARD ===================== --}}
    @if ($view === 'board')
        <p class="hint mb-3">Drag a card to another column, or use <b>Move to</b>. Authors, tagged members and executives can move a project.</p>
        <div class="-mx-4 flex snap-x snap-mandatory gap-3 overflow-x-auto px-4 pb-4 md:mx-0 md:grid md:grid-cols-5 md:overflow-visible md:px-0 md:pb-0">
            @foreach ($columns as $stage => $stageIdeas)
                <section class="flex min-h-40 w-[78vw] max-w-[320px] flex-none snap-center flex-col rounded-2xl bg-surface p-2.5 transition-colors md:w-auto md:max-w-none"
                         :class="over === '{{ $stage }}' && dragging ? 'bg-tint ring-2 ring-brand' : ''"
                         x-on:dragover.prevent="over = '{{ $stage }}'" x-on:dragleave="if (over === '{{ $stage }}') over = null"
                         x-on:drop.prevent="if (dragging) { $wire.move(dragging, '{{ $stage }}'); } dragging = null; over = null"
                         aria-label="{{ $stage }}">
                    <header class="mb-1 flex items-center gap-2 px-1.5">
                        <span class="size-2.5 rounded-full {{ $dot[$stage] }}"></span>
                        <h2 class="font-display text-sm font-bold">{{ $stage }}</h2>
                        <span class="ml-auto rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-muted tabular-nums">{{ $stageIdeas->count() }}</span>
                    </header>
                    <p class="mb-2 px-1.5 text-[11.5px] text-muted">{{ $hint[$stage] }}</p>

                    <div class="flex flex-col gap-2">
                        @forelse ($stageIdeas as $idea)
                            @php($can = $idea->canContribute($me))
                            <article wire:key="b{{ $idea->id }}"
                                     @if ($can) draggable="true" x-on:dragstart="dragging = {{ $idea->id }}; $event.dataTransfer.effectAllowed = 'move'" x-on:dragend="dragging = null; over = null" @endif
                                     :class="dragging === {{ $idea->id }} ? 'opacity-40' : ''"
                                     class="rounded-xl border border-line bg-white p-3 shadow-sm {{ $can ? 'cursor-grab active:cursor-grabbing' : '' }}">
                                <a href="{{ route('projects.show', $idea) }}" wire:navigate draggable="false" class="block">
                                    <h3 class="font-display text-[14.5px] leading-snug font-semibold tracking-tight">{{ $idea->title }}</h3>
                                    <div class="meta mt-2"><x-avatar :user="$idea->author" :size="20" /><span class="truncate">{{ $idea->author->name }}</span><x-verified :user="$idea->author" /></div>
                                    @if ($idea->challenge)<div class="mt-2"><span class="chip !h-5 !text-[11px]"><x-icon name="flag" :size="11" /><span class="truncate">{{ $idea->challenge->title }}</span></span></div>@endif
                                    <div class="mt-2.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11.5px] text-muted">
                                        @if ($idea->open_tasks_count)<span class="inline-flex items-center gap-1 font-semibold text-[#8a5a00]"><x-icon name="check" :size="12" /> {{ $idea->open_tasks_count }} pending</span>@endif
                                        <span class="inline-flex items-center gap-1"><x-icon name="comment" :size="12" /> {{ $idea->comments_count }}</span>
                                        <span class="inline-flex items-center gap-1"><x-icon name="spark" :size="12" /> {{ $idea->updates_count }}</span>
                                        @if ($idea->members->isNotEmpty())
                                            <span class="flex -space-x-1.5">@foreach ($idea->members->take(3) as $m)<x-avatar :user="$m" :size="18" class="ring-2 ring-white" />@endforeach</span>
                                        @endif
                                        <span class="ml-auto">{{ $idea->updated_at->diffForHumans(null, true, true) }}</span>
                                    </div>
                                </a>
                                @if ($can)
                                    <select wire:change="move({{ $idea->id }}, $event.target.value)" class="mt-2.5 h-8 w-full rounded-lg border border-line bg-surface px-2 text-xs font-semibold text-muted" aria-label="Move {{ $idea->title }} to another stage">
                                        <option value="">Move to…</option>
                                        @foreach (\App\Models\Idea::STATUSES as $s)@if ($s !== $stage)<option value="{{ $s }}">{{ $s }}</option>@endif @endforeach
                                    </select>
                                @endif
                            </article>
                        @empty
                            <div class="rounded-xl border border-dashed border-tint-2 px-3 py-6 text-center text-xs text-muted">Nothing here yet</div>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
    @endif

    {{-- ===================== PROJECTS ===================== --}}
    @if ($view === 'projects')
        <div class="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
            @forelse ($ideas as $idea)
                @php($can = $idea->canContribute($me))
                @php($k = $idea->stageIndex())
                <article wire:key="pr{{ $idea->id }}" class="flex flex-col rounded-[20px] border border-line bg-white p-4 shadow-sm">
                    {{-- header --}}
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('projects.show', $idea) }}" wire:navigate class="block font-display text-[17px] leading-snug font-semibold tracking-tight hover:text-brand">{{ $idea->title }}</a>
                            <div class="meta mt-1.5"><x-avatar :user="$idea->author" :size="20" /><span class="truncate">{{ $idea->author->name }}</span><x-verified :user="$idea->author" />@if ($idea->challenge)<span class="dot"></span><span class="truncate">{{ $idea->challenge->title }}</span>@endif</div>
                        </div>
                        <x-stage :status="$idea->status" />
                    </div>
                    <ol class="mt-3 grid grid-cols-5 gap-1" aria-label="Stage {{ $k + 1 }} of 5">
                        @foreach (\App\Models\Idea::STATUSES as $n => $s)<li><span @class(['block h-1 rounded-full', 'bg-brand' => $n <= $k, 'bg-line' => $n > $k])></span></li>@endforeach
                    </ol>

                    {{-- pending tasks, flowing down --}}
                    <h3 class="mt-4 mb-1.5 flex items-center gap-2 text-[11px] font-semibold tracking-widest text-muted uppercase">Pending <span class="rounded-full bg-[#fbeed3] px-1.5 text-[11px] text-[#8a5a00] tabular-nums normal-case">{{ $idea->tasks->count() }}</span></h3>
                    <ul class="space-y-1">
                        @forelse ($idea->tasks as $t)
                            <li class="flex items-start gap-2.5 rounded-lg px-1 py-1" wire:key="pt{{ $t->id }}">
                                <button @if ($can) wire:click="toggleTask({{ $t->id }})" @else disabled @endif class="mt-0.5 grid size-5 flex-none place-items-center rounded-full border-2 border-line hover:border-brand" aria-label="Mark done"></button>
                                <span class="min-w-0 text-sm">{{ $t->title }}@if ($t->due_date)<span @class(['ml-1.5 text-xs', 'font-semibold text-heart' => $t->isOverdue(), 'text-muted' => ! $t->isOverdue()])>{{ $t->isOverdue() ? 'Overdue · ' : '' }}{{ $t->due_date->format('j M') }}</span>@endif</span>
                            </li>
                        @empty
                            <li class="px-1 py-1 text-sm text-muted">Nothing pending.</li>
                        @endforelse
                    </ul>
                    @if ($can)
                        <form wire:submit="addTaskTo({{ $idea->id }})" class="mt-1.5 flex gap-1.5">
                            <label class="sr-only" for="nt{{ $idea->id }}">Add a pending item</label>
                            <input id="nt{{ $idea->id }}" wire:model="newTask.{{ $idea->id }}" class="inp !h-8 !rounded-lg !px-2.5 text-[13px]" placeholder="Add pending item" maxlength="200">
                            <button class="btn btn-sm !h-8 flex-none">Add</button>
                        </form>
                    @endif

                    {{-- timeline --}}
                    <h3 class="mt-4 mb-2 text-[11px] font-semibold tracking-widest text-muted uppercase">Timeline</h3>
                    <ol class="relative space-y-2.5 border-l border-line pl-4">
                        @forelse ($idea->updates->take(4) as $u)
                            <li class="relative" wire:key="tl{{ $u->id }}">
                                <span class="absolute top-1.5 -left-[21px] size-2.5 rounded-full border-2 border-white {{ $u->kind === 'stage' ? 'bg-brand' : 'bg-[#b9c6bf]' }}"></span>
                                @if ($u->kind === 'stage')
                                    <p class="text-[13px]"><b>{{ $u->author->first_name }}</b> moved it from {{ $u->from_stage }} to <b>{{ $u->to_stage }}</b></p>
                                @else
                                    <p class="line-clamp-2 text-[13px]"><b>{{ $u->author->first_name }}:</b> {{ $u->body }}</p>
                                @endif
                                <span class="text-[11px] text-muted">{{ $u->created_at->diffForHumans() }}</span>
                            </li>
                        @empty
                            <li class="text-[13px] text-muted">No updates yet.</li>
                        @endforelse
                        <li class="relative text-[11px] text-muted"><span class="absolute top-1 -left-[21px] size-2.5 rounded-full border-2 border-white bg-line"></span>Posted {{ $idea->created_at->format('j M Y') }}</li>
                    </ol>
                    @if ($idea->updates->count() > 4)<a href="{{ route('projects.show', $idea) }}" wire:navigate class="mt-1.5 text-xs font-semibold text-brand hover:underline">See all {{ $idea->updates->count() }} updates</a>@endif

                    {{-- note --}}
                    <h3 class="mt-4 mb-1.5 text-[11px] font-semibold tracking-widest text-muted uppercase">Note</h3>
                    @if ($can)
                        <textarea wire:model="notes.{{ $idea->id }}" class="inp !min-h-16 text-[13px]" rows="2" placeholder="Current focus, blockers, how others can help"></textarea>
                        <div class="mt-1.5 flex justify-end"><button wire:click="saveNote({{ $idea->id }})" class="btn btn-sm">Save note</button></div>
                    @else
                        <p class="rounded-lg bg-surface p-2.5 text-[13px] whitespace-pre-line text-ink-2">{{ $idea->note ?: 'No note yet.' }}</p>
                    @endif

                    {{-- tagged members --}}
                    <h3 class="mt-4 mb-1.5 text-[11px] font-semibold tracking-widest text-muted uppercase">Team</h3>
                    <div class="flex flex-wrap items-center gap-1.5">
                        <a href="{{ route('profile', $idea->author) }}" wire:navigate class="flex items-center gap-1.5 rounded-full bg-surface py-0.5 pr-2.5 pl-0.5 text-xs font-semibold" title="Author"><x-avatar :user="$idea->author" :size="22" />{{ $idea->author->first_name }}</a>
                        @foreach ($idea->members as $m)
                            <span class="flex items-center gap-1.5 rounded-full bg-tint py-0.5 pr-1.5 pl-0.5 text-xs font-semibold text-brand" wire:key="mb{{ $idea->id }}-{{ $m->id }}">
                                <a href="{{ route('profile', $m) }}" wire:navigate class="flex items-center gap-1.5"><x-avatar :user="$m" :size="22" />{{ $m->first_name }}</a>
                                @if ($can)<button wire:click="untagMember({{ $idea->id }}, {{ $m->id }})" class="grid size-4 place-items-center rounded-full hover:bg-white" aria-label="Remove {{ $m->first_name }}"><x-icon name="x" :size="11" /></button>@endif
                            </span>
                        @endforeach
                        @if ($can)<button wire:click="openTagging({{ $idea->id }})" class="flex items-center gap-1 rounded-full border border-dashed border-tint-2 px-2.5 py-1 text-xs font-semibold text-brand hover:bg-tint"><x-icon name="plus" :size="12" /> Tag</button>@endif
                    </div>
                    @if ($taggingFor === $idea->id)
                        <div class="mt-2 rounded-xl border border-line bg-white p-2 shadow-md">
                            <input wire:model.live.debounce.200ms="tagSearch" class="inp !h-8 !rounded-lg text-[13px]" placeholder="Search members by name or department" autofocus>
                            @forelse ($taggable as $p)
                                <button wire:click="tagMember({{ $idea->id }}, {{ $p->id }})" class="mt-1 flex w-full items-center gap-2 rounded-lg p-1.5 text-left hover:bg-surface" wire:key="tg{{ $p->id }}"><x-avatar :user="$p" :size="26" /><span class="min-w-0"><b class="block truncate text-[13px]">{{ $p->name }}<x-verified :user="$p" /></b><small class="text-xs text-muted">{{ $p->dept ?: $p->title }}</small></span></button>
                            @empty
                                <p class="p-2 text-xs text-muted">No one else to tag.</p>
                            @endforelse
                        </div>
                    @endif

                    {{-- footer --}}
                    <div class="mt-4 flex items-center gap-2 border-t border-line pt-3">
                        <a href="{{ route('projects.show', $idea) }}" wire:navigate class="btn btn-sm btn-primary">Open project</a>
                        @if ($can)
                            <select wire:change="move({{ $idea->id }}, $event.target.value)" class="h-[34px] rounded-full border border-line bg-white px-2.5 text-xs font-semibold text-muted" aria-label="Move to another stage">
                                <option value="">Move to…</option>
                                @foreach (\App\Models\Idea::STATUSES as $s)@if ($s !== $idea->status)<option value="{{ $s }}">{{ $s }}</option>@endif @endforeach
                            </select>
                        @endif
                        <span class="ml-auto inline-flex items-center gap-1 text-xs text-muted"><x-icon name="comment" :size="13" /> {{ $idea->comments_count }}</span>
                    </div>
                </article>
            @empty
                <div class="py-16 text-center text-muted lg:col-span-2 2xl:col-span-3"><b class="block text-ink">No projects here</b>Post an idea and it shows up in the pipeline.</div>
            @endforelse
        </div>
    @endif
</div>
