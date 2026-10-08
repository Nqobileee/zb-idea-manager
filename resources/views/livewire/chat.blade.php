<div @class(['mx-auto', 'max-w-[1080px]' => true])>
    {{-- Desktop heading, and phone heading when no thread is open --}}
    <div class="mb-4 hidden items-center justify-between md:flex"><h1 class="page-title">Chat</h1><button wire:click="$toggle('picking')" class="btn btn-sm"><x-icon name="plus" :size="16" /> New chat</button></div>

    @if ($picking)
        <div class="sheet mb-4">
            <input wire:model.live.debounce.250ms="find" class="inp mb-2" placeholder="Search members" aria-label="Search members" autofocus>
            <div class="max-h-72 overflow-y-auto">
                @foreach ($people as $p)
                    <button wire:click="start({{ $p->id }})" class="flex w-full items-center gap-3 rounded-xl p-2 text-left hover:bg-surface"><x-avatar :user="$p" :size="38" /><span class="min-w-0"><b class="block truncate">{{ $p->name }}<x-verified :user="$p" /></b><small class="text-muted">{{ $p->title }}</small></span></button>
                @endforeach
            </div>
        </div>
    @endif

    <div @class(['grid overflow-hidden md:h-[calc(100dvh-160px)] md:min-h-[420px] md:grid-cols-[300px_minmax(0,1fr)] md:rounded-[20px] md:border md:border-line', 'h-dvh' => (bool) $conv, 'h-[calc(100dvh-190px)]' => ! $conv])>
        {{-- Conversation list: hidden on phones while a thread is open --}}
        <div @class(['overflow-y-auto md:border-r md:border-line', 'hidden md:block' => (bool) $conv])>
            {{-- The all-staff group chat is always pinned on top --}}
            <button wire:click="open({{ $group->id }})" wire:key="cv{{ $group->id }}" @class(['flex w-full items-center gap-3 border-b border-line px-4 py-3.5 text-left hover:bg-surface', 'bg-tint' => $conv?->id === $group->id])>
                <span class="grid size-[42px] flex-none place-items-center rounded-full bg-brand text-white"><x-icon name="users" :size="20" /></span>
                <span class="min-w-0 grow">
                    <span class="flex items-baseline justify-between gap-2"><b class="truncate">{{ $group->name }}</b>@if ($group->last)<small class="flex-none text-xs text-muted">{{ $group->last->created_at->diffForHumans(null, true, true) }}</small>@endif</span>
                    <span class="flex items-center gap-2"><span class="block truncate text-sm text-muted">@if ($group->last){{ $group->last->user_id === $me->id ? 'You' : $group->last->sender->first_name }}: {{ $group->last->body }}@else{{ $members }} members. Say hello to everyone.@endif</span>@if ($group->unread)<span class="count !ml-auto">{{ $group->unread }}</span>@endif</span>
                </span>
            </button>
            @forelse ($list as $c)
                @php($other = $c->other($me))
                <button wire:click="open({{ $c->id }})" wire:key="cv{{ $c->id }}" @class(['flex w-full items-center gap-3 px-4 py-3.5 text-left hover:bg-surface', 'bg-tint' => $conv?->id === $c->id])>
                    <x-avatar :user="$other" :size="42" />
                    <span class="min-w-0 grow">
                        <span class="flex items-baseline justify-between gap-2"><b class="truncate">{{ $other->name }}<x-verified :user="$other" /></b><small class="flex-none text-xs text-muted">{{ $c->last->created_at->diffForHumans(null, true, true) }}</small></span>
                        <span class="flex items-center gap-2"><span class="block truncate text-sm text-muted">{{ $c->last->user_id === $me->id ? 'You: ' : '' }}{{ $c->last->body }}</span>@if ($c->unread)<span class="count !ml-auto">{{ $c->unread }}</span>@endif</span>
                    </span>
                </button>
            @empty
                <p class="hint p-6 text-center">No private chats yet. Start one with the + button.</p>
            @endforelse
        </div>

        {{-- Thread --}}
        <section @class(['min-w-0 flex-col', 'flex' => (bool) $conv, 'hidden md:flex' => ! $conv]) style="{{ $conv ? 'display:flex' : '' }}">
            @if ($conv)
                <div class="flex items-center gap-2.5 border-b border-line px-3 py-2.5" style="padding-top:calc(.625rem + env(safe-area-inset-top,0px))">
                    <button wire:click="close" class="iconbtn md:hidden" aria-label="Back to chats"><x-icon name="back" :size="20" /></button>
                    @if ($conv->is_group)
                        <a href="{{ route('members') }}" wire:navigate class="flex min-w-0 items-center gap-2.5"><span class="grid size-9 flex-none place-items-center rounded-full bg-brand text-white"><x-icon name="users" :size="18" /></span><span class="min-w-0"><b class="block truncate">{{ $conv->name }}</b><small class="block truncate text-xs text-muted">{{ $members }} members · everyone at ZB</small></span></a>
                    @else
                        @php($other = $conv->other($me))
                        <a href="{{ route('profile', $other) }}" wire:navigate class="flex min-w-0 items-center gap-2.5"><x-avatar :user="$other" :size="36" /><span class="min-w-0"><b class="block truncate">{{ $other->name }}<x-verified :user="$other" /></b><small class="block truncate text-xs text-muted">{{ $other->title }}</small></span></a>
                    @endif
                </div>
                <div class="flex min-h-0 grow flex-col gap-2 overflow-y-auto bg-surface p-4" x-data x-init="$el.scrollTop = $el.scrollHeight" x-effect="$nextTick(() => $el.scrollTop = $el.scrollHeight)" wire:key="msgs{{ $conv->id }}">
                    @forelse ($conv->messages as $m)
                        @php($mine = $m->user_id === $me->id)
                        <div wire:key="m{{ $m->id }}" @class(['max-w-[78%] rounded-2xl px-3.5 py-2 text-[15px]', 'self-end bg-brand text-white' => $mine, 'self-start border border-line bg-white' => ! $mine])>
                            {{-- In the group, name the sender once per run of their messages; the name opens a private chat with them --}}
                            @if ($conv->is_group && ! $mine && ($loop->first || $conv->messages[$loop->index - 1]->user_id !== $m->user_id))
                                <button type="button" wire:click="start({{ $m->user_id }})" class="block text-xs font-semibold text-brand hover:underline" title="Chat privately with {{ $m->sender->first_name }}">{{ $m->sender->name }}<x-verified :user="$m->sender" /></button>
                            @endif
                            {{ $m->body }}
                            <small @class(['mt-0.5 block text-[11px]', 'text-white/70' => $mine, 'text-muted' => ! $mine])>{{ $m->created_at->diffForHumans(null, true, true) }}@if ($mine)<span class="ml-1 inline-flex align-[-3px] {{ $m->delivered_at ? 'rounded-full bg-white px-1 text-brand' : '' }}" title="{{ $m->delivered_at ? 'Delivered' : 'Sent' }}"><x-icon :name="$m->delivered_at ? 'checks' : 'check'" :size="15" /><span class="sr-only">{{ $m->delivered_at ? 'Delivered' : 'Sent' }}</span></span>@endif</small>
                        </div>
                    @empty
                        <p class="hint m-auto text-center">No messages yet. Be the first to say hello.</p>
                    @endforelse
                </div>
                <form wire:submit="send" class="flex items-center gap-2 border-t border-line p-3" style="padding-bottom:calc(.75rem + env(safe-area-inset-bottom,0px))">
                    <label class="sr-only" for="msg">Message</label>
                    <input id="msg" wire:model="body" class="inp !rounded-full" placeholder="Write a message" autocomplete="off" required>
                    <button class="btn btn-primary !size-11 !p-0" aria-label="Send"><x-icon name="send" :size="17" /></button>
                </form>
            @else
                <div class="m-auto text-center text-muted"><b class="block text-ink">Pick a conversation</b>Or start a new chat with any member.</div>
            @endif
        </section>
    </div>

    @unless ($conv)
        <button wire:click="$toggle('picking')" class="fixed right-4 grid size-14 place-items-center rounded-[18px] bg-brand text-white shadow-[0_8px_20px_rgba(4,144,22,.35)] md:hidden" style="bottom:calc(20px + env(safe-area-inset-bottom,0px))" aria-label="New chat"><x-icon name="plus" :size="24" /></button>
    @endunless
</div>
