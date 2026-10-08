<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#049016">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <title>{{ ($title ?? 'Ideas') }} · ZB Idea Manager</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&family=Instrument+Sans:wght@400..700&family=JetBrains+Mono:wght@400;600&display=swap">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
@php
    $bare = $bare ?? false;          // full screen pages (an open chat) hide the bars
    $user = auth()->user();
    $route = request()->route()?->getName();
    $nav = [
        ['home', 'Home', 'home', route('home'), ['home', 'ideas.show']],
        ['challenges', $user->is_admin ? 'My challenges' : 'Challenges', 'flag', route('challenges'), ['challenges', 'challenges.show', 'challenges.create', 'challenges.edit']],
        ['chat', 'Chat', 'chat', route('chat'), ['chat']],
        ['activity', 'Activity', 'bell', route('activity'), ['activity']],
        ['members', 'Members', 'users', route('members'), ['members', 'profile', 'profile.edit']],
        ['pipeline', 'Pipeline', 'board', route('pipeline'), ['pipeline', 'projects.show']],
    ];
@endphp
<body class="min-h-dvh bg-white">
<div class="md:grid md:grid-cols-[252px_minmax(0,1fr)]">

    {{-- Desktop sidebar --}}
    <aside class="sticky top-0 hidden h-dvh flex-col gap-0.5 [&>*]:shrink-0 overflow-y-auto border-r border-line bg-white px-3.5 pt-5.5 pb-4 md:flex">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5 px-2.5 pb-5 font-display text-[17px] font-bold tracking-tight">
            <img src="{{ asset('images/zb-logo.png') }}" alt="ZB" class="size-9 flex-none"> <span>Idea Manager</span>
        </a>
        @foreach ($nav as [$key, $label, $icon, $url, $match])
            <a href="{{ $url }}" wire:navigate @class(['nav', 'nav-on' => in_array($route, $match, true)])>
                <x-icon :name="$icon" :size="20" />{{ $label }}
                @if ($key === 'activity' || $key === 'chat')<livewire:nav-badge :kind="$key" :key="'side-'.$key" />@endif
            </a>
        @endforeach
        <a href="{{ route('ideas.create') }}" wire:navigate class="btn btn-primary my-3"><x-icon name="plus" :size="16" /> Post an idea</a>
        @if ($user->is_admin)
            <div class="px-3 pt-5 pb-1.5 text-[11px] font-semibold tracking-widest text-muted uppercase">Executive</div>
            @foreach ([['admin.ranking', 'AI ranking', 'spark', route('admin.ranking')], ['admin.insights', 'Insights', 'chart', route('admin.insights')]] as [$r, $l, $i, $u])
                <a href="{{ $u }}" wire:navigate @class(['nav', 'nav-on' => $route === $r])><x-icon :name="$i" :size="20" />{{ $l }}</a>
            @endforeach
        @endif
        <div class="mt-auto border-t border-line pt-3.5">
            <a href="{{ route('profile', $user) }}" wire:navigate class="flex items-center gap-2.5 rounded-xl p-2 hover:bg-surface">
                <x-avatar :user="$user" :size="36" />
                <span class="min-w-0"><b class="block truncate">{{ $user->name }}</b><small class="text-xs text-muted">{{ $user->role_label }}</small></span>
            </a>
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="nav"><x-icon name="logout" :size="18" /> Sign out</button>
            </form>
        </div>
    </aside>

    <div class="min-w-0">
        {{-- Phone top bar --}}
        @unless ($bare)
            <header class="glass fixed inset-x-0 top-0 z-30 flex h-14 items-center justify-between border-0 border-b border-line px-3 md:hidden" style="padding-top:env(safe-area-inset-top,0px);height:calc(56px + env(safe-area-inset-top,0px))">
                @if ($route === 'home')
                    <button class="iconbtn" aria-label="Filter ideas" x-data x-on:click="Livewire.dispatch('toggle-filter')"><x-icon name="filter" :size="22" /></button>
                @else
                    <button class="iconbtn" aria-label="Back" x-data x-on:click="history.length > 1 ? history.back() : window.location = '{{ route('home') }}'"><x-icon name="back" :size="22" /></button>
                @endif
                <div class="max-w-[52%] truncate font-display text-[17px] font-bold">{{ $title ?? 'Ideas' }}</div>
                <div class="flex items-center gap-0.5" x-data="{ menu: false }">
                    <button class="iconbtn" aria-label="Search" x-on:click="Livewire.dispatch('toggle-search')"><x-icon name="search" :size="21" /></button>
                    <button class="iconbtn" aria-label="Your profile and menu" x-on:click="menu = !menu"><x-avatar :user="$user" :size="30" /></button>
                    <div x-cloak x-show="menu" x-on:click.outside="menu = false" class="absolute top-14 right-3 w-56 rounded-2xl border border-line bg-white p-2 shadow-xl">
                        <a href="{{ route('profile', $user) }}" class="nav"><x-icon name="user" :size="18" /> My profile</a>
                        <a href="{{ route('challenges') }}" class="nav"><x-icon name="flag" :size="18" /> {{ $user->is_admin ? 'My challenges' : 'Challenges' }}</a>
                        @if ($user->is_admin)
                            <a href="{{ route('admin.ranking') }}" class="nav"><x-icon name="spark" :size="18" /> AI ranking</a>
                            <a href="{{ route('admin.insights') }}" class="nav"><x-icon name="chart" :size="18" /> Insights</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="nav"><x-icon name="logout" :size="18" /> Sign out</button></form>
                    </div>
                </div>
            </header>
        @endunless

        <main @class(['min-w-0 md:px-8 md:pt-8 md:pb-24', 'px-4 pt-[calc(68px+env(safe-area-inset-top,0px))]' => ! $bare, 'pb-32' => ! $bare && ! ($noDock ?? false), 'pb-24' => ! $bare && ($noDock ?? false), 'p-0' => $bare])>
            {{ $slot }}
        </main>
    </div>
</div>

{{-- Phone dock with the floating post button on the right --}}
@unless ($bare || ($noDock ?? false))
    <nav class="glass fixed inset-x-3 z-40 grid h-[66px] grid-cols-5 items-center rounded-3xl shadow-[0_12px_34px_rgba(4,144,22,.18)] md:hidden" style="bottom:calc(12px + env(safe-area-inset-bottom,0px))" aria-label="Main">
        @foreach (collect($nav)->reject(fn ($n) => $n[0] === 'challenges') as [$key, $label, $icon, $url, $match])
            <a href="{{ $url }}" wire:navigate @class(['relative flex flex-col items-center gap-0.5 text-[10.5px] font-semibold', 'text-brand' => in_array($route, $match, true), 'text-muted' => ! in_array($route, $match, true)])>
                <x-icon :name="$icon" :size="22" :fill="in_array($route, $match, true) && ! in_array($icon, ['users', 'chat'])" />{{ $label }}
                @if ($key === 'activity' || $key === 'chat')<livewire:nav-badge :kind="$key" :float="true" :key="'dock-'.$key" />@endif
            </a>
        @endforeach
        <a href="{{ route('ideas.create') }}" wire:navigate aria-label="Post a new idea" class="absolute right-1 bottom-[calc(100%+14px)] grid size-14 place-items-center rounded-[18px] bg-brand text-white shadow-[0_8px_20px_rgba(4,144,22,.35)]"><x-icon name="plus" :size="24" /></a>
    </nav>
@endunless

<div id="toast-root" class="pointer-events-none fixed inset-x-0 bottom-28 z-50 flex justify-center px-4" x-data="{ msg: '', show: false, t: null }"
     x-on:toast.window="msg = $event.detail.message ?? $event.detail; show = true; clearTimeout(t); t = setTimeout(() => show = false, 3200)">
    <div x-cloak x-show="show" x-transition class="flex max-w-sm items-center gap-2.5 rounded-2xl bg-ink px-4 py-3 text-sm font-medium text-white shadow-xl"><x-icon name="check" :size="17" /><span x-text="msg"></span></div>
</div>

@livewireScripts
<script>
    // Realtime: Reverb pushes new messages and notifications; Livewire components listen for 'realtime' and re-render.
    document.addEventListener('livewire:init', () => {
        if (! window.Echo) return;
        const id = {{ $user->id }};
        window.Echo.private('users.' + id)
            .listen('.ActivityCreated', () => Livewire.dispatch('realtime'))
            .listen('.MessageSent', () => Livewire.dispatch('realtime'));
    });
</script>
</body>
</html>
