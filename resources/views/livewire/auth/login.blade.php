<div class="grid min-h-dvh md:grid-cols-[1.1fr_1fr]">
    <section class="relative flex flex-col justify-between gap-10 overflow-hidden bg-brand px-6 py-8 text-white md:p-14"
             style="background-image:radial-gradient(rgba(255,255,255,.1) 1.2px,transparent 1.3px);background-size:14px 14px">
        <div class="flex items-center gap-2.5 font-display text-lg font-bold">
            <span class="grid size-11 place-items-center rounded-xl bg-white p-1.5"><img src="{{ asset('images/zb-logo.png') }}" alt="ZB" class="size-full"></span> Idea Manager
        </div>
        <div>
            <h1 class="font-display text-[34px] leading-[1.05] font-bold tracking-tight md:text-5xl">Good ideas should reach the people who decide.</h1>
            <p class="mt-4 max-w-md text-[17px] text-white/80">Post your idea, answer challenges set by executives, and follow it from first sketch to launch.</p>
            <div class="mt-6 flex flex-wrap gap-2">
                @foreach (\App\Models\Idea::STATUSES as $n => $s)
                    <span @class(['rounded-full border px-3.5 py-1.5 text-[13px] font-semibold', 'border-white/30' => ! $loop->last, 'border-white bg-white text-brand' => $loop->last]) style="--lift:calc({{ $n }} * -9px)">{{ $s }}</span>
                @endforeach
            </div>
        </div>
        <p class="flex items-center gap-1.5 text-[13px] text-white/75"><x-icon name="shield" :size="14" /> For verified members only.</p>
    </section>

    <section class="flex items-center px-6 py-10 md:px-14">
        <div class="mx-auto w-full max-w-sm">
            <form wire:submit="signIn" class="space-y-4">
                <div>
                    <h2 class="font-display text-[30px] font-bold tracking-tight">Sign in</h2>
                    <p class="hint">Use the email and password you made with the Smile Factory chatbot.</p>
                </div>
                <div>
                    <label class="lbl" for="email">Email address</label>
                    <input id="email" type="email" wire:model="email" class="inp" placeholder="you@example.com" autocomplete="email" autofocus required>
                    @error('email')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="lbl" for="password">Password</label>
                    <input id="password" type="password" wire:model="password" class="inp" autocomplete="current-password" required>
                    @error('password')<p class="err">{{ $message }}</p>@enderror
                </div>
                @if ($error ?? $linkError)<p class="err" role="alert">{{ $error ?? $linkError }}</p>@endif
                <button class="btn btn-primary btn-lg w-full" wire:loading.attr="disabled">Sign in</button>
            </form>

            <div class="mt-8 space-y-3 rounded-2xl bg-surface p-4">
                <h3 class="font-display text-base font-bold">New here?</h3>
                <ol class="space-y-2.5 text-sm">
                    <li class="flex gap-3"><span class="grid size-6 flex-none place-items-center rounded-full bg-tint text-xs font-bold text-brand">1</span><span>Message the Smile Factory chatbot on WhatsApp (<b>+263 77 736 6886</b>) and register.</span></li>
                    <li class="flex gap-3"><span class="grid size-6 flex-none place-items-center rounded-full bg-tint text-xs font-bold text-brand">2</span><span>Send <b>web</b> to the chatbot.</span></li>
                    <li class="flex gap-3"><span class="grid size-6 flex-none place-items-center rounded-full bg-tint text-xs font-bold text-brand">3</span><span>Tap the link it sends back. It works once, for 10 minutes.</span></li>
                </ol>
                <p class="text-sm">Chatbot number: <a href="{{ $waUrl }}" class="font-semibold text-brand hover:underline">+263 77 736 6886</a></p>
                <a href="{{ $waUrl }}" class="btn w-full" target="_blank" rel="noopener"><x-icon name="comment" :size="16" /> Open WhatsApp</a>
            </div>
        </div>
    </section>
</div>
