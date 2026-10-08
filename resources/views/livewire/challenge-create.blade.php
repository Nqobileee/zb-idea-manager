<div class="mx-auto max-w-[700px]">
    <div class="eyebrow">Executive</div>
    <h1 class="page-title mb-5">{{ $challengeId ? 'Edit challenge' : 'New challenge' }}</h1>
    <form wire:submit="save" class="space-y-4">
        <div><label class="lbl" for="t">Title</label><input id="t" wire:model="title" class="inp" required>@error('title')<p class="err">{{ $message }}</p>@enderror</div>
        <div><label class="lbl" for="b">Brief</label><textarea id="b" wire:model="brief" class="inp" rows="5" required></textarea>@error('brief')<p class="err">{{ $message }}</p>@enderror</div>
        <div><label class="lbl" for="k">Keywords <span class="font-normal text-muted">(comma separated, used by the ranking)</span></label><input id="k" wire:model="keywords" class="inp" placeholder="queue, cash, payday">@error('keywords')<p class="err">{{ $message }}</p>@enderror</div>
        <div><label class="lbl" for="d">Deadline</label><input id="d" type="date" wire:model="deadline" class="inp">@error('deadline')<p class="err">{{ $message }}</p>@enderror</div>
        <div class="flex justify-end gap-2"><a href="{{ route('challenges') }}" wire:navigate class="btn">Cancel</a><button class="btn btn-primary">{{ $challengeId ? 'Save changes' : 'Post to all staff' }}</button></div>
    </form>
</div>
