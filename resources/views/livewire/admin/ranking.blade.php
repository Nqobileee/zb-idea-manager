@php
    $bar = fn ($label, $v) => '<div><small class="mb-1 flex justify-between text-[11.5px] text-muted tabular-nums"><span>'.e($label).'</span><b class="text-ink">'.$v.'</b></small><div class="h-1.5 overflow-hidden rounded bg-line"><div class="h-full rounded bg-brand" style="width:'.$v.'%"></div></div></div>';
    $row = function ($x, $n, $first) use ($bar) { return compact('x', 'n', 'first'); };
@endphp
<div class="mx-auto max-w-[1080px]">
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div class="max-w-xl">
            <div class="eyebrow"><x-icon name="spark" :size="13" /> ZB Idea Ranker</div>
            <h1 class="page-title">AI ranking</h1>
            <p class="hint mt-1">Every idea is scored on how well it fits what you asked for, how colleagues responded, and the quality of the idea itself. The top five come first.</p>
        </div>
        <button wire:click="digest" class="btn btn-primary btn-sm"><x-icon name="mail" :size="15" /> Email me the top 5</button>
    </div>

    <div class="grid gap-4 rounded-[20px] border border-line p-4 md:grid-cols-[minmax(0,280px)_minmax(0,1fr)] md:items-end md:gap-5">
        <div><label class="lbl" for="rk">Rank against</label><select id="rk" wire:model.live="challenge" class="inp"><option value="all">All ideas</option>@foreach ($challenges as $c)<option value="{{ $c->id }}">{{ $c->title }}</option>@endforeach</select></div>
        <div class="grid gap-4 md:grid-cols-3">
            @foreach (['rel' => 'Fits what we asked', 'eng' => 'Likes and comments', 'q' => 'Idea quality'] as $k => $l)
                <div><label class="lbl flex justify-between" for="w-{{ $k }}"><span>{{ $l }}</span><output class="tabular-nums text-brand">{{ $$k }}</output></label><input id="w-{{ $k }}" type="range" min="0" max="100" step="5" wire:model.live.debounce.150ms="{{ $k }}" class="w-full accent-brand"></div>
            @endforeach
        </div>
    </div>
    <p class="hint my-3 flex items-center gap-1.5"><x-icon name="shield" :size="14" /> A weighted formula stands in for a trained model, so you can see how ranking behaves.</p>

    @forelse ($list as $i => $x)
        @if ($i === 5)<h3 class="mt-8 mb-3 font-display text-base font-semibold">Also ranked</h3>@endif
        @php($idea = $x['idea'])
        <div wire:key="rk{{ $idea->id }}" @class(['mb-2.5 grid grid-cols-[36px_minmax(0,1fr)] items-center gap-4 rounded-[20px] border p-4 md:grid-cols-[52px_minmax(0,1fr)_auto]', 'border-tint-2 bg-gradient-to-r from-tint to-white to-55%' => $i < 5, 'border-line' => $i >= 5])>
            <div @class(['text-center font-display font-extrabold tabular-nums', 'text-4xl text-brand' => $i < 5, 'text-[22px] text-muted' => $i >= 5])>{{ $i + 1 }}</div>
            <div class="min-w-0">
                <div class="meta"><x-avatar :user="$idea->author" :size="24" /><b class="truncate font-semibold text-ink">{{ $idea->author->name }}</b><span class="dot"></span><span class="truncate">{{ $idea->author->dept }}</span><x-stage :status="$idea->status" /></div>
                <a href="{{ route('ideas.show', $idea) }}" wire:navigate class="my-1.5 mb-2.5 block font-display text-lg leading-tight font-semibold tracking-tight hover:text-brand">{{ $idea->title }}</a>
                <div class="grid gap-3 md:grid-cols-3">{!! $bar('Fits what we asked', $x['rel']) !!}{!! $bar('Likes and comments', $x['eng']) !!}{!! $bar('Idea quality', $x['q']) !!}</div>
                @if ($x['outside'])<div class="mt-2.5"><span class="chip chip-ghost">Matched from outside this challenge</span></div>@endif
            </div>
            <div class="col-span-2 flex items-center justify-between gap-2.5 md:col-span-1 md:grid md:justify-items-end">
                <div class="font-display text-[30px] leading-none font-bold tracking-tight tabular-nums">{{ $x['total'] }}<small class="ml-0.5 text-[13px] font-medium text-muted">/100</small></div>
                @if ($idea->approved)<span class="chip"><x-icon name="check" :size="13" /> Approved</span>
                @else<button wire:click="approve({{ $idea->id }})" wire:confirm="Approve this idea and email the author?" class="btn btn-primary btn-sm"><x-icon name="check" :size="15" /> Approve</button>@endif
            </div>
        </div>
    @empty
        <div class="py-16 text-center text-muted"><b class="block text-ink">No ideas to rank yet</b></div>
    @endforelse
</div>
