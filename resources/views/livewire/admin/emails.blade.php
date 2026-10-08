<div class="mx-auto max-w-[900px]">
    <div class="eyebrow"><x-icon name="mail" :size="13" /> Executive</div>
    <h1 class="page-title">Email log</h1>
    <p class="hint mt-1 mb-4">Everything the platform has emailed: sign-in codes, approvals and digests. Set MAIL_MAILER to send them for real.</p>
    @forelse ($emails as $e)
        <div class="mb-2 rounded-2xl border border-line" wire:key="e{{ $e->id }}">
            <button wire:click="toggle({{ $e->id }})" class="flex w-full items-center gap-3 p-3.5 text-left">
                <span class="chip">{{ $e->type }}</span>
                <span class="min-w-0 grow"><b class="block truncate">{{ $e->subject }}</b><small class="text-muted">to {{ $e->to }}</small></span>
                <small class="flex-none text-muted">{{ $e->created_at->diffForHumans(null, true, true) }}</small>
            </button>
            @if ($open === $e->id)<pre class="border-t border-line p-4 font-sans text-sm whitespace-pre-wrap text-ink-2">{{ $e->body }}</pre>@endif
        </div>
    @empty
        <p class="hint py-10 text-center">No emails yet.</p>
    @endforelse
</div>
