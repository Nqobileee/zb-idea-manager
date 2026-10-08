@props(['status'])
<span {{ $attributes->class(['tag', \App\Models\Idea::stageClasses($status)]) }}>{{ $status }}</span>
