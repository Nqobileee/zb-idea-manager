<div class="mx-auto max-w-[1080px]">
    <div class="eyebrow"><x-icon name="user" :size="13" /> Executive</div>
    <h1 class="page-title">All users</h1>
    <p class="hint mt-1 mb-4">Every verified employee on the platform. Executives get the blue tick and the review tools.</p>
    <input wire:model.live.debounce.250ms="q" type="search" class="inp mb-4 max-w-md" placeholder="Search users" aria-label="Search users">
    <div class="overflow-x-auto rounded-[18px] border border-line">
        <table class="w-full min-w-[720px] border-collapse text-sm">
            <thead><tr class="bg-surface text-left text-[11px] tracking-widest text-muted uppercase"><th class="p-3.5">Name</th><th class="p-3.5">Department</th><th class="p-3.5">Email</th><th class="p-3.5">Ideas</th><th class="p-3.5">WhatsApp</th><th class="p-3.5">Role</th></tr></thead>
            <tbody>
            @foreach ($users as $u)
                <tr class="border-t border-line" wire:key="u{{ $u->id }}">
                    <td class="p-3.5"><a href="{{ route('profile', $u) }}" wire:navigate class="flex items-center gap-2.5"><x-avatar :user="$u" :size="30" /><b>{{ $u->name }}<x-verified :user="$u" /></b></a></td>
                    <td class="p-3.5">{{ $u->dept }}</td><td class="p-3.5 text-muted">{{ $u->email }}</td><td class="p-3.5 tabular-nums">{{ $u->ideas_count }}</td>
                    <td class="p-3.5">{!! $u->phone ? '<span class="chip">Linked</span>' : '<span class="text-muted">No</span>' !!}</td>
                    <td class="p-3.5">@if ($u->id !== auth()->id())<button wire:click="toggleAdmin({{ $u->id }})" wire:confirm="Change the role of {{ $u->name }}?" class="btn btn-sm">{{ $u->is_admin ? 'Executive' : 'Employee' }}</button>@else<span class="text-muted">You</span>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
