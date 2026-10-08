<span>
    @if ($n > 0)
        <span @class(['count', 'absolute -top-1.5 left-[calc(50%+4px)] !ml-0 h-[17px] min-w-[17px] !text-[10px]' => $float])>{{ $n }}</span>
    @endif
</span>
