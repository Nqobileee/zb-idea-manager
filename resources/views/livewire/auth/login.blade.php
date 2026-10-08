<div class="grid min-h-dvh md:grid-cols-[1.1fr_1fr]">
    <section class="relative flex flex-col justify-between gap-10 overflow-hidden bg-brand px-6 py-8 text-white md:p-14"
             style="background-image:radial-gradient(rgba(255,255,255,.1) 1.2px,transparent 1.3px);background-size:14px 14px">
        <div class="flex items-center gap-2.5 font-display text-lg font-bold">
            <span class="grid size-9 place-items-center rounded-[10px] bg-white font-display text-[13px] font-extrabold text-brand">ZB</span> Idea Manager
        </div>
        <div>
            <h1 class="font-display text-[34px] leading-[1.05] font-bold tracking-tight md:text-5xl">Good ideas should reach the people who decide.</h1>
            <p class="mt-4 max-w-md text-[17px] text-white/80">Post your idea, answer challenges set by executives, and follow it from first sketch to launch.</p>
            <div class="mt-6 flex flex-wrap gap-2 md:items-end">
                @foreach (\App\Models\Idea::STATUSES as $n => $s)
                    <span @class(['rounded-full border px-3.5 py-1.5 text-[13px] font-semibold md:translate-y-[var(--lift)]', 'border-white/30' => ! $loop->last, 'border-white bg-white text-brand' => $loop->last]) style="--lift:calc({{ $n }} * -9px)">{{ $s }}</span>
                @endforeach
            </div>
        </div>
        <p class="flex items-center gap-1.5 text-[13px] text-white/75"><x-icon name="shield" :size="14" /> For verified ZB Group employees only.</p>
    </section>

    <section class="flex items-center px-6 py-10 md:px-14">
        <div class="mx-auto w-full max-w-sm">
            @if ($step === 'email')
                <form wire:submit="sendCode" class="space-y-4">
                    <div>
                        <h2 class="font-display text-[30px] font-bold tracking-tight">Sign in</h2>
                        <p class="hint">Use your email address.</p>
                    </div>
                    <div>
                        <label class="lbl" for="email">Email address</label>
                        <input id="email" type="email" wire:model="email" class="inp" placeholder="you@example.com" autocomplete="email" autofocus required>
                    </div>
                    @if ($roleChoice)
                        <fieldset>
                            <legend class="lbl">Sign in as</legend>
                            <div class="grid grid-cols-2 gap-2">
                                @foreach (['employee' => 'Employee', 'admin' => 'Executive admin'] as $v => $l)
                                    <label @class(['cursor-pointer rounded-xl border px-3 py-2.5 text-center text-sm font-semibold', 'border-brand bg-tint text-brand' => $role === $v, 'border-line' => $role !== $v])><input type="radio" class="sr-only" value="{{ $v }}" wire:model.live="role">{{ $l }}</label>
                                @endforeach
                            </div>
                            <p class="hint mt-1.5">Temporary: anyone can choose either role while the app is being set up.</p>
                        </fieldset>
                    @endif
                    @if ($error)<p class="err" role="alert">{{ $error }}</p>@endif
                    <button class="btn btn-primary btn-lg w-full" wire:loading.attr="disabled">Email me a code</button>
                    <p class="hint flex gap-1.5"><x-icon name="shield" :size="14" class="mt-0.5 flex-none" /> We email you a 6-digit code. No password needed.</p>
                </form>
            @else
                <form wire:submit="verify" class="space-y-4">
                    <button type="button" wire:click="back" class="hint flex items-center gap-1 hover:text-ink"><x-icon name="back" :size="16" /> Change email</button>
                    <div>
                        <h2 class="font-display text-[30px] font-bold tracking-tight">Check your email</h2>
                        <p class="hint">We sent a 6-digit code to <b class="text-ink">{{ $email }}</b>.</p>
                    </div>
                    <div>
                        <label class="lbl" for="code">Verification code</label>
                        <input id="code" wire:model="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="000000" autofocus class="inp h-[60px] text-center font-mono text-[26px] tracking-[.5em]">
                    </div>
                    @if ($error)<p class="err" role="alert">{{ $error }}</p>@endif
                    @if ($demo)<p class="hint">Demo mode: any 6 digits work.</p>@elseif (app()->environment('local'))<p class="hint">Local: the code is in storage/logs/laravel.log and in the Email log.</p>@endif
                    <button class="btn btn-primary btn-lg w-full" wire:loading.attr="disabled">Verify and continue</button>
                </form>
            @endif
        </div>
    </section>
</div>
