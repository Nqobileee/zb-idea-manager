@props(['file'])
@php
    $bg = ['pdf' => 'bg-[#b23a2e]', 'xlsx' => 'bg-[#1f6f45]', 'pptx' => 'bg-[#c4581f]', 'docx' => 'bg-[#2b5797]'][$file->ext] ?? 'bg-[#56655d]';
@endphp
<span class="ext {{ $bg }}">{{ $file->ext === 'other' ? 'file' : $file->ext }}</span>
