{{-- One item on the pipeline calendar: a due pending item, a stage move, a new idea or a challenge deadline. --}}
@props(['e', 'wide' => false])
@php
    $kind = $e['kind'];
    $idea = $e['idea'] ?? null;
    [$cls, $icon] = match ($kind) {
        'task' => ['bg-[#fbeed3] text-[#8a5a00]', 'check'],
        'overdue' => ['bg-[#fde3e1] text-heart', 'check'],
        'done' => ['bg-surface text-muted line-through', 'check'],
        'stage' => [\App\Models\Idea::stageClasses($e['stage']), 'board'],
        'posted' => ['bg-tint text-brand', 'plus'],
        default => ['bg-brand text-white', 'flag'],
    };
    $text = match ($kind) {
        'stage' => $idea->title.' → '.$e['stage'],
        'posted' => 'New: '.$idea->title,
        'deadline' => 'Deadline: '.$e['label'],
        default => $e['label'],
    };
    $tip = match ($kind) {
        'task', 'overdue', 'done' => ($kind === 'overdue' ? 'Overdue: ' : ($kind === 'done' ? 'Done: ' : 'Due: ')).$e['label'].' ('.$idea->title.')',
        'deadline' => 'Challenge deadline: '.$e['label'],
        default => $text,
    };
    $url = $kind === 'deadline' ? route('challenges.show', $e['challenge']) : route('projects.show', $idea);
@endphp
<a href="{{ $url }}" wire:navigate title="{{ $tip }}"
   @class(['flex min-w-0 items-center gap-1.5 rounded-md px-1.5 py-0.5 text-[11.5px] leading-5 font-semibold hover:brightness-95', $cls, 'py-1.5 text-[13px]' => $wide])>
    <x-icon :name="$icon" :size="$wide ? 14 : 11" class="flex-none" />
    <span @class(['min-w-0', 'truncate' => ! $wide])>{{ $text }}</span>
    @if ($wide && in_array($kind, ['task', 'overdue', 'done'], true))<span class="ml-auto truncate pl-2 text-[11.5px] font-normal opacity-80">{{ $idea->title }}</span>@endif
</a>
