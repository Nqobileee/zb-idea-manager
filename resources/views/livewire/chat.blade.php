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
                <p class="hint p-6 text-center">No conversations yet. Start one with the + button.</p>
            @endforelse
        </div>

        {{-- Thread --}}
        <section @class(['min-w-0 flex-col', 'flex' => (bool) $conv, 'hidden md:flex' => ! $conv]) style="{{ $conv ? 'display:flex' : '' }}">
            @if ($conv)
                @php($other = $conv->other($me))
                <div class="flex items-center gap-2.5 border-b border-line px-3 py-2.5" style="padding-top:calc(.625rem + env(safe-area-inset-top,0px))">
                    <button wire:click="close" class="iconbtn md:hidden" aria-label="Back to chats"><x-icon name="back" :size="20" /></button>
                    <a href="{{ route('profile', $other) }}" wire:navigate class="flex min-w-0 items-center gap-2.5"><x-avatar :user="$other" :size="36" /><span class="min-w-0"><b class="block truncate">{{ $other->name }}<x-verified :user="$other" /></b><small class="block truncate text-xs text-muted">{{ $other->title }}</small></span></a>
                </div>
                <div class="flex min-h-0 grow flex-col gap-2 overflow-y-auto bg-surface p-4" x-data x-init="$el.scrollTop = $el.scrollHeight" x-effect="$nextTick(() => $el.scrollTop = $el.scrollHeight)" wire:key="msgs{{ $conv->id }}">
                    @foreach ($conv->messages as $m)
                        <div wire:key="m{{ $m->id }}" @class(['max-w-[78%] rounded-2xl px-3.5 py-2 text-[15px]', 'self-end bg-brand text-white' => $m->user_id === $me->id, 'self-start border border-line bg-white' => $m->user_id !== $me->id])>{{ $m->body }}<small @class(['mt-0.5 block text-[11px]', 'text-white/70' => $m->user_id === $me->id, 'text-muted' => $m->user_id !== $me->id])>{{ $m->created_at->diffForHumans(null, true, true) }}</small></div>
                    @endforeach
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
