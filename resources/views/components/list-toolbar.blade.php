@props(['search' => 'search', 'placeholder' => null])

{{-- Search box on the left, filters and buttons on the right. --}}
<div class="flex flex-wrap items-center gap-2 border-b border-card-divider px-3 py-2">
    <div class="relative min-w-64 max-w-md flex-1">
        <x-icon name="search" class="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
        <input wire:model.live.debounce.300ms="{{ $search }}" type="search" placeholder="{{ $placeholder ?? __('Search...') }}" class="form-input pl-8">
    </div>
    <div class="flex flex-1 flex-wrap items-center justify-end gap-2">{{ $slot }}</div>
</div>
