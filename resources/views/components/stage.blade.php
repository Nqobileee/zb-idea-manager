@props(['status'])
@php
    $cls = [
        'Idea' => 'bg-surface text-muted',
        'Prototype' => 'bg-[#fbeed3] text-[#8a5a00]',
        'Demo' => 'bg-[#e0ebf8] text-[#2a5a99]',
        'Pilot' => 'bg-[#ece5f7] text-[#5b3f9e]',
        'Launched' => 'bg-brand text-white',
    ][$status] ?? 'bg-surface text-muted';
@endphp
<span {{ $attributes->class(['tag', $cls]) }}>{{ $status }}</span>
