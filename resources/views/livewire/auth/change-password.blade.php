<div class="mx-auto flex min-h-dvh w-full max-w-sm items-center px-6 py-10">
    <form wire:submit="save" class="w-full space-y-4">
        <div>
            <h1 class="font-display text-[28px] font-bold tracking-tight">Choose a new password</h1>
            <p class="hint">{{ $forced ? 'You signed in with a temporary password. Please set your own to continue.' : 'Pick a password you will remember.' }}</p>
        </div>
        <div>
            <label class="lbl" for="password">New password</label>
            <input id="password" type="password" wire:model="password" class="inp" autocomplete="new-password" autofocus required>
            @error('password')<p class="err">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="lbl" for="password_confirmation">Repeat new password</label>
            <input id="password_confirmation" type="password" wire:model="password_confirmation" class="inp" autocomplete="new-password" required>
        </div>
        <p class="hint">At least 8 characters.</p>
        <button class="btn btn-primary btn-lg w-full" wire:loading.attr="disabled">Save and continue</button>
        <form method="POST" action="{{ route('logout') }}" class="text-center">@csrf<button type="submit" class="hint hover:text-ink">Sign out</button></form>
    </form>
</div>
