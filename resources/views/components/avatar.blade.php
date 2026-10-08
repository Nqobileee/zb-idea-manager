@props(['user', 'size' => 32])
<span {{ $attributes->class('inline-grid flex-none place-items-center overflow-hidden rounded-full font-bold text-white') }} style="width:{{ $size }}px;height:{{ $size }}px;background:{{ $user->color }};font-size:{{ round($size * .36) }}px">
    @if ($user->avatar_url)
        <img src="{{ $user->avatar_url }}" alt="" class="size-full object-cover">
    @else
        {{ $user->initials }}
    @endif
</span>
