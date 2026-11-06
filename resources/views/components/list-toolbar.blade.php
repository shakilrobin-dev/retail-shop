@props(['search' => 'search', 'placeholder' => null])

{{-- Search box on the left, filters and buttons on the right. --}}
<div class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-4 py-3">
    <div class="relative min-w-64 flex-1">
        <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
        <input wire:model.live.debounce.300ms="{{ $search }}" type="search" placeholder="{{ $placeholder ?? __('Search...') }}" class="form-input pl-9">
    </div>
    {{ $slot }}
</div>
