<div class="mx-auto max-w-[560px]">
    <h1 class="page-title mb-5">Edit profile</h1>
    <form wire:submit="save" class="space-y-4">
        <div class="flex items-center gap-4">
            @if ($photo)<img src="{{ $photo->temporaryUrl() }}" class="size-16 rounded-full object-cover" alt="">@else<x-avatar :user="auth()->user()" :size="64" />@endif
            <label class="btn btn-sm cursor-pointer"><x-icon name="camera" :size="15" /> Change photo<input type="file" wire:model="photo" accept="image/*" class="sr-only"></label>
        </div>
        @error('photo')<p class="err">{{ $message }}</p>@enderror
        <div><label class="lbl" for="n">Name</label><input id="n" wire:model="name" class="inp" required>@error('name')<p class="err">{{ $message }}</p>@enderror</div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="lbl" for="t">Role</label><input id="t" wire:model="title" class="inp"></div>
            <div><label class="lbl" for="d">Department</label><input id="d" wire:model="dept" class="inp"></div>
        </div>
        <div><label class="lbl" for="b">Short bio</label><textarea id="b" wire:model="bio" class="inp" rows="3"></textarea></div>
        <label class="flex items-start gap-2.5 rounded-xl border border-line p-3.5 text-sm"><input type="checkbox" wire:model="whatsappOptIn" class="mt-0.5 accent-brand"><span><b>WhatsApp notifications</b><br><span class="text-muted">{{ auth()->user()->phone ? 'Linked to +'.auth()->user()->phone.'. Send "unlink" to the bot to remove it.' : 'Not linked. Message the ZB Ideas WhatsApp number and say "hi" to link your account.' }}</span></span></label>
        <div class="flex justify-end gap-2"><a href="{{ route('profile', auth()->user()) }}" wire:navigate class="btn">Cancel</a><button class="btn btn-primary">Save</button></div>
    </form>
</div>
