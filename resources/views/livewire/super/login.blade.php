<div class="mx-auto flex min-h-dvh w-full max-w-sm items-center px-6 py-10">
    <form wire:submit="signIn" class="w-full space-y-4">
        <div>
            <div class="eyebrow"><x-icon name="shield" :size="13" /> Restricted</div>
            <h1 class="font-display text-[28px] font-bold tracking-tight">Super admin</h1>
            <p class="hint">Sign in with the super admin details.</p>
        </div>
        <div>
            <label class="lbl" for="email">Email</label>
            <input id="email" type="email" wire:model="email" class="inp" autocomplete="username" autofocus required>
        </div>
        <div>
            <label class="lbl" for="password">Password</label>
            <input id="password" type="password" wire:model="password" class="inp" autocomplete="current-password" required>
        </div>
        @if ($error)<p class="err" role="alert">{{ $error }}</p>@endif
        <button class="btn btn-primary btn-lg w-full" wire:loading.attr="disabled">Sign in</button>
    </form>
</div>
