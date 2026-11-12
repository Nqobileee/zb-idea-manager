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
<body class="min-h-dvh bg-white md:bg-surface">
<div x-data="{ collapsed: $persist(false).as('zb-nav-collapsed') }" :style="'--side:' + (collapsed ? '84px' : '300px')" style="--side:300px">

{{-- Desktop top bar --}}
<header class="sticky top-0 z-30 hidden h-[72px] items-center gap-6 bg-surface pr-6 md:flex">
    <a href="{{ route('home') }}" wire:navigate class="flex w-[var(--side)] flex-none items-center gap-4 pl-6" aria-label="Idea Manager home">
        <img src="{{ asset('images/zb-logo.png') }}" alt="ZB" class="size-9 flex-none">
        <span x-show="! collapsed" class="h-8 w-px flex-none bg-line"></span>
        <span x-show="! collapsed" class="truncate font-display text-[22px] font-semibold tracking-tight">Idea Manager</span>
    </a>
    <form method="GET" action="{{ route('home') }}" class="flex min-w-0 flex-1 justify-center" role="search"
          x-data x-on:keydown.window.ctrl.k.prevent="$refs.q.focus()" x-on:keydown.window.meta.k.prevent="$refs.q.focus()">
        <label class="flex h-12 w-full max-w-[650px] items-center gap-3 rounded-xl border border-line bg-white px-4 focus-within:border-brand focus-within:ring-2 focus-within:ring-tint-2">
            <x-icon name="search" :size="19" class="flex-none text-muted" />
            <input x-ref="q" name="q" type="search" value="{{ request('q') }}" placeholder="Search ideas, challenges and people" class="h-full min-w-0 flex-1 bg-transparent text-[15.5px] outline-none placeholder:text-muted" aria-label="Search ideas">
            <kbd class="flex-none rounded-md border border-line px-1.5 py-0.5 font-mono text-[11px] text-muted">Ctrl K</kbd>
        </label>
    </form>
    <div class="flex flex-none items-center gap-3">
        <a href="{{ route('ideas.create') }}" wire:navigate class="btn btn-primary"><x-icon name="plus" :size="16" /> Post an idea</a>
        <a href="{{ route('activity') }}" wire:navigate class="iconbtn relative size-11 [&_.count]:bg-alert" aria-label="Activity">
            <x-icon name="bell" :size="22" />
            <livewire:nav-badge kind="activity" :float="true" key="top-activity" />
        </a>
        <div class="relative" x-data="{ menu: false }">
            <button class="grid size-11 place-items-center rounded-full" aria-label="Your profile and menu" x-on:click="menu = ! menu"><x-avatar :user="$user" :size="42" /></button>
            <div x-cloak x-show="menu" x-transition.origin.top.right x-on:click.outside="menu = false" class="absolute top-[52px] right-0 z-40 w-64 rounded-2xl border border-line bg-white p-2 shadow-xl">
                <div class="flex items-center gap-2.5 border-b border-line px-2 pt-1 pb-3">
                    <x-avatar :user="$user" :size="36" />
                    <span class="min-w-0"><b class="block truncate">{{ $user->name }}</b><small class="text-xs text-muted">{{ $user->role_label }}</small></span>
                </div>
                <a href="{{ route('profile', $user) }}" wire:navigate class="nav mt-1.5"><x-icon name="user" :size="18" /> My profile</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="nav"><x-icon name="logout" :size="18" /> Sign out</button></form>
            </div>
        </div>
    </div>
</header>

<div class="md:grid md:grid-cols-[var(--side)_minmax(0,1fr)]">

    {{-- Desktop sidebar --}}
    <aside class="sticky top-[72px] hidden h-[calc(100dvh-72px)] flex-col gap-1 overflow-y-auto px-4 pt-6 pb-5 md:flex [&>*]:shrink-0 [&_.count]:bg-alert" :class="collapsed && 'items-center !px-3'">
        @foreach (collect($nav)->reject(fn ($n) => $n[0] === 'activity') as [$key, $label, $icon, $url, $match])
            <a href="{{ $url }}" wire:navigate @class(['side', 'side-on' => in_array($route, $match, true)]) :class="collapsed && 'side-mini'" title="{{ $label }}">
                <x-icon :name="$icon" :size="21" class="flex-none" /><span x-show="! collapsed" class="truncate">{{ $label }}</span>
                @if ($key === 'chat')<span x-show="! collapsed" class="ml-auto"><livewire:nav-badge kind="chat" key="side-chat" /></span>@endif
            </a>
        @endforeach
        @if ($user->is_admin)
            <div x-show="! collapsed" class="px-3.5 pt-6 pb-2 text-[14px] text-muted">Executive</div>
            <div x-show="collapsed" class="my-3 h-px w-8 bg-line"></div>
            @foreach ([['admin.ranking', 'AI ranking', 'spark', route('admin.ranking')], ['admin.insights', 'Insights', 'chart', route('admin.insights')]] as [$r, $l, $i, $u])
                <a href="{{ $u }}" wire:navigate @class(['side', 'side-on' => $route === $r]) :class="collapsed && 'side-mini'" title="{{ $l }}"><x-icon :name="$i" :size="21" class="flex-none" /><span x-show="! collapsed" class="truncate">{{ $l }}</span></a>
            @endforeach
        @endif
        <div class="mt-auto flex items-center gap-2 px-3 pt-6 text-[13.5px] text-muted" title="Verified ZB staff only">
            <x-icon name="shield" :size="17" class="flex-none" /><span x-show="! collapsed">Verified ZB staff only</span>
        </div>
    </aside>

    <div class="min-w-0 md:relative md:mr-4 md:min-h-[calc(100dvh-72px)] md:rounded-t-2xl md:border md:border-b-0 md:border-line md:bg-white">
        <button type="button" class="absolute top-4 -left-3.5 z-10 hidden size-7 place-items-center rounded-full border border-line bg-white text-muted shadow-sm hover:text-ink md:grid"
                x-on:click="collapsed = ! collapsed" x-bind:aria-label="collapsed ? 'Expand menu' : 'Collapse menu'" aria-label="Collapse menu">
            <x-icon name="back" :size="15" x-bind:class="collapsed && 'rotate-180'" />
        </button>
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
