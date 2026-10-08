@php($max = max(1, $byStage->max()))
<div class="mx-auto max-w-[1080px]">
    <div class="eyebrow"><x-icon name="chart" :size="13" /> Executive</div>
    <h1 class="page-title mb-5">Insights</h1>
    <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
        @foreach ($kpis as $label => $n)
            <div class="rounded-[18px] border border-line p-4"><div class="font-display text-[34px] leading-tight font-bold tracking-tight tabular-nums">{{ $n }}</div><div class="hint">{{ $label }}</div></div>
        @endforeach
    </div>
    <div class="mt-5 grid gap-4 md:grid-cols-2">
        <section class="rounded-[18px] border border-line p-4">
            <h2 class="mb-3 font-display font-semibold">Ideas by stage</h2>
            @foreach ($byStage as $s => $n)
                <div class="mb-2.5 flex items-center gap-3 text-sm"><span class="w-20 text-muted">{{ $s }}</span><div class="h-2.5 grow overflow-hidden rounded bg-line"><div class="h-full rounded bg-brand" style="width:{{ $n / $max * 100 }}%"></div></div><b class="w-6 text-right tabular-nums">{{ $n }}</b></div>
            @endforeach
        </section>
        <section class="rounded-[18px] border border-line p-4">
            <h2 class="mb-3 font-display font-semibold">Ideas by department</h2>
            <ul>@foreach ($byDept as $d)<li class="flex justify-between border-b border-line py-2 text-sm last:border-0"><span>{{ $d->dept ?: 'Unassigned' }}</span><b class="tabular-nums">{{ $d->n }}</b></li>@endforeach</ul>
        </section>
        <section class="rounded-[18px] border border-line p-4 md:col-span-2">
            <h2 class="mb-3 font-display font-semibold">Responses per challenge</h2>
            <ul>@foreach ($challenges as $c)<li class="flex justify-between gap-3 border-b border-line py-2 text-sm last:border-0"><a href="{{ route('challenges.show', $c) }}" wire:navigate class="truncate hover:text-brand">{{ $c->title }}</a><b class="tabular-nums">{{ $c->ideas_count }}</b></li>@endforeach</ul>
        </section>
    </div>
</div>
