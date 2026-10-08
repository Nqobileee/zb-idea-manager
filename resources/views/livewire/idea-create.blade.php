<div class="mx-auto max-w-[700px]">
    <div class="eyebrow">{{ $ideaId ? 'Edit' : 'New idea' }}</div>
    <h1 class="page-title mb-5">{{ $ideaId ? 'Edit your idea' : 'Post an idea' }}</h1>
    <form wire:submit="save" class="space-y-4">
        <div>
            <label class="lbl" for="t">Title</label>
            <input id="t" wire:model="title" class="inp" maxlength="120" placeholder="Pre-booked cash pickup slots" required>
            @error('title')<p class="err">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="lbl" for="s">Summary <span class="font-normal text-muted">(shown on the feed)</span></label>
            <textarea id="s" wire:model="summary" class="inp" rows="2" maxlength="400" placeholder="Two sentences that tell people what it is and why it matters." required></textarea>
            @error('summary')<p class="err">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="lbl" for="b">Details</label>
            <textarea id="b" wire:model="body" class="inp" rows="7" placeholder="What is the problem, how does your idea fix it, what have you tried so far?"></textarea>
            @error('body')<p class="err">{{ $message }}</p>@enderror
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="lbl" for="st">Stage</label>
                <select id="st" wire:model="status" class="inp">@foreach (\App\Models\Idea::STATUSES as $s)<option>{{ $s }}</option>@endforeach</select>
            </div>
            <div>
                <label class="lbl" for="ch">Challenge</label>
                <select id="ch" wire:model="challenge" class="inp"><option value="">None</option>@foreach ($challenges as $c)<option value="{{ $c->id }}">{{ $c->title }}</option>@endforeach</select>
            </div>
        </div>

        <div>
            <span class="lbl">Photos or screenshots</span>
            <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-dashed border-tint-2 bg-surface p-3.5 text-muted hover:border-brand">
                <x-icon name="camera" :size="18" /> Add images <input type="file" class="sr-only" wire:model="images" accept="image/*" multiple>
            </label>
            @if ($existing->where('kind', 'image')->isNotEmpty())
                <div class="mt-2 flex flex-wrap gap-2">@foreach ($existing->where('kind', 'image') as $f)<div class="relative" wire:key="ex{{ $f->id }}"><img src="{{ $f->url }}" class="size-20 rounded-lg object-cover" alt=""><button type="button" wire:click="removeExisting({{ $f->id }})" wire:confirm="Remove this image?" class="absolute -top-1.5 -right-1.5 grid size-5 place-items-center rounded-full bg-ink text-white" aria-label="Remove image"><x-icon name="x" :size="12" /></button></div>@endforeach</div>
            @endif
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($images as $i => $img)
                    <div class="relative"><img src="{{ $img->temporaryUrl() }}" class="size-20 rounded-lg object-cover" alt=""><button type="button" wire:click="removeImage({{ $i }})" class="absolute -top-1.5 -right-1.5 grid size-5 place-items-center rounded-full bg-ink text-white" aria-label="Remove image"><x-icon name="x" :size="12" /></button></div>
                @endforeach
            </div>
            @error('images.*')<p class="err">{{ $message }}</p>@enderror
        </div>

        <div>
            <span class="lbl">Supporting documents</span>
            <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-dashed border-tint-2 bg-surface p-3.5 text-muted hover:border-brand">
                <x-icon name="clip" :size="18" /> Attach PDFs, spreadsheets or slides <input type="file" class="sr-only" wire:model="docs" multiple>
            </label>
            @foreach ($existing->where('kind', 'doc') as $f)
                <div class="mt-2 flex items-center gap-2 rounded-xl border border-line p-2.5 text-sm" wire:key="ex{{ $f->id }}"><x-icon name="file" :size="16" /><span class="min-w-0 grow truncate">{{ $f->name }}</span><button type="button" wire:click="removeExisting({{ $f->id }})" wire:confirm="Remove this document?" class="iconbtn !size-7" aria-label="Remove"><x-icon name="x" :size="14" /></button></div>
            @endforeach
            @foreach ($docs as $i => $d)
                <div class="mt-2 flex items-center gap-2 rounded-xl border border-line p-2.5 text-sm"><x-icon name="file" :size="16" /><span class="min-w-0 grow truncate">{{ $d->getClientOriginalName() }}</span><button type="button" wire:click="removeDoc({{ $i }})" class="iconbtn !size-7" aria-label="Remove"><x-icon name="x" :size="14" /></button></div>
            @endforeach
            @error('docs.*')<p class="err">{{ $message }}</p>@enderror
            <div wire:loading wire:target="docs,images" class="hint mt-1">Uploading...</div>
        </div>

        <div class="flex justify-end gap-2 pt-2"><a href="{{ $ideaId ? route('ideas.show', $ideaId) : route('home') }}" wire:navigate class="btn">Cancel</a><button class="btn btn-primary" wire:loading.attr="disabled">{{ $ideaId ? 'Save changes' : 'Post idea' }}</button></div>
    </form>
</div>
