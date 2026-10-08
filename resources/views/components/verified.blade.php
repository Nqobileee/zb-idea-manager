@props(['user'])
@if ($user->is_admin)
    <svg class="ml-1 inline size-[1.05em] min-h-3.5 min-w-3.5 flex-none align-[-.15em]" viewBox="0 0 24 24" role="img" aria-label="Verified executive"><title>Verified executive</title><path d="M12 1.8l2.6 1.9 3.2-.1 1 3 2.6 1.9-1 3 1 3-2.6 1.9-1 3-3.2-.1L12 22.2l-2.6-1.9-3.2.1-1-3L2.6 15.5l1-3-1-3 2.6-1.9 1-3 3.2.1z" fill="#1d9bf0"/><path d="M7.8 12.3l3 3 5.6-6.1" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
@endif
