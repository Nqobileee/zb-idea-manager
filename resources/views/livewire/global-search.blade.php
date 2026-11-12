<div class="relative w-full max-w-[650px]" x-data="{ open: false }"
     x-on:keydown.window.ctrl.k.prevent="$refs.q.focus(); open = true" x-on:keydown.window.meta.k.prevent="$refs.q.focus(); open = true"
     x-on:click.outside="open = false" x-on:keydown.escape="open = false; $refs.q.blur()">
    <label class="flex h-12 w-full items-center gap-3 rounded-xl border border-line bg-white px-4 focus-within:border-brand focus-within:ring-2 focus-within:ring-tint-2">
        <x-icon name="search" :size="19" class="flex-none text-muted" />
        <input x-ref="q" wire:model.live.debounce.120ms="q" type="text" autocomplete="off" placeholder="Search ideas, challenges, people, teams and pages"
               x-on:focus="open = true" x-on:input="open = true"
               x-on:keydown.enter.prevent="const a = $refs.list?.querySelector('a'); if (a) a.click()"
               x-on:keydown.down.prevent="$refs.list?.querySelector('a')?.focus()"
               class="h-full min-w-0 flex-1 bg-transparent text-[15.5px] outline-none placeholder:text-muted" aria-label="Search everything" role="combobox" x-bind:aria-expanded="open">
        @if ($q !== '')
            <button type="button" wire:click="$set('q', '')" x-on:click="$refs.q.focus()" class="grid size-7 flex-none place-items-center rounded-full text-muted hover:bg-surface hover:text-ink" aria-label="Clear search"><x-icon name="x" :size="15" /></button>
        @else
            <kbd class="flex-none rounded-md border border-line px-1.5 py-0.5 font-mono text-[11px] text-muted">Ctrl K</kbd>
        @endif
    </label>

    {{-- Suggestions: quick links while empty, then matches from the first letter --}}
    @if ($total > 0 || $term !== '')
        <div x-cloak x-show="open" x-transition.origin.top x-ref="list"
             x-on:keydown.down.prevent="let n = $focus.within($refs.list).getNext(); n ? n.focus() : null"
             x-on:keydown.up.prevent="let p = $focus.within($refs.list).getPrevious(); p ? p.focus() : $refs.q.focus()"
             class="absolute inset-x-0 top-[calc(100%+8px)] z-40 max-h-[70vh] overflow-y-auto rounded-2xl border border-line bg-white p-2 shadow-xl" role="listbox">
            @if ($total === 0)
                <p class="px-3 py-6 text-center text-sm text-muted">Nothing matches “{{ $term }}”.</p>
            @endif

            @php($row = 'flex items-center gap-3 rounded-xl px-2.5 py-2 outline-none hover:bg-surface focus:bg-surface')
            @php($head = 'px-2.5 pt-2 pb-1 text-xs font-semibold text-muted')

            <div wire:loading.flex wire:target="q" class="items-center gap-2 px-2.5 pt-1 pb-0.5 text-xs text-muted"><span class="size-3 animate-spin rounded-full border-2 border-line border-t-brand"></span>Searching…</div>

            @if ($results['pages']->isNotEmpty())
                <div class="{{ $head }}">{{ $term === '' ? 'Jump to' : 'Pages' }}</div>
                @foreach ($results['pages'] as $page)
                    <a href="{{ $page['url'] }}" wire:navigate wire:key="gpg{{ $loop->index }}-{{ $page['label'] }}" class="{{ $row }} !py-1.5" x-on:click="open = false">
                        <span class="grid size-[30px] flex-none place-items-center rounded-lg bg-surface text-ink-2"><x-icon :name="$page['icon']" :size="16" /></span>
                        <span class="min-w-0 flex-1 truncate text-[14px] font-medium">{{ $page['label'] }}</span>
                    </a>
                @endforeach
            @endif

            @if ($results['ideas']->isNotEmpty())
                <div class="{{ $head }}">Ideas</div>
                @foreach ($results['ideas'] as $idea)
                    <a href="{{ route('ideas.show', $idea) }}" wire:navigate wire:key="gi{{ $idea->id }}" class="{{ $row }}" x-on:click="open = false">
                        <x-avatar :user="$idea->author" :size="30" />
                        <span class="min-w-0 flex-1"><b class="block truncate text-[14px] font-semibold">{{ $idea->title }}</b><small class="block truncate text-xs text-muted">{{ $idea->author->name }}</small></span>
                        <x-stage :status="$idea->status" />
                    </a>
                @endforeach
            @endif

            @if ($results['challenges']->isNotEmpty())
                <div class="{{ $head }}">Challenges</div>
                @foreach ($results['challenges'] as $c)
                    <a href="{{ route('challenges.show', $c) }}" wire:navigate wire:key="gc{{ $c->id }}" class="{{ $row }}" x-on:click="open = false">
                        <span class="grid size-[30px] flex-none place-items-center rounded-full bg-tint text-brand"><x-icon name="flag" :size="15" /></span>
                        <span class="min-w-0 flex-1"><b class="block truncate text-[14px] font-semibold">{{ $c->title }}</b><small class="block truncate text-xs text-muted">{{ $c->deadline ? ($c->daysLeft() > 0 ? $c->daysLeft().' days left' : 'Closed') : 'Open' }}</small></span>
                    </a>
                @endforeach
            @endif

            @if ($results['people']->isNotEmpty())
                <div class="{{ $head }}">People</div>
                @foreach ($results['people'] as $p)
                    <a href="{{ route('profile', $p) }}" wire:navigate wire:key="gp{{ $p->id }}" class="{{ $row }}" x-on:click="open = false">
                        <x-avatar :user="$p" :size="30" />
                        <span class="min-w-0 flex-1"><b class="block truncate text-[14px] font-semibold">{{ $p->name }}<x-verified :user="$p" /></b><small class="block truncate text-xs text-muted">{{ collect([$p->title, $p->dept])->filter()->join(' · ') ?: $p->role_label }}</small></span>
                    </a>
                @endforeach
            @endif

            @if ($results['teams']->isNotEmpty())
                <div class="{{ $head }}">Teams</div>
                @foreach ($results['teams'] as $t)
                    <a href="{{ route('teams.show', $t->id) }}" wire:navigate wire:key="gt{{ $t->id }}" class="{{ $row }}" x-on:click="open = false">
                        <span class="grid size-[30px] flex-none place-items-center rounded-full bg-tint-2 text-brand"><x-icon name="shield" :size="15" /></span>
                        <span class="min-w-0 flex-1"><b class="block truncate text-[14px] font-semibold">{{ $t->name }}</b><small class="block truncate text-xs text-muted">{{ $t->members_count }} {{ \Illuminate\Support\Str::plural('member', $t->members_count) }}</small></span>
                    </a>
                @endforeach
            @endif
        </div>
    @endif
</div>
