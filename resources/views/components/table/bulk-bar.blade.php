@props(['count'])

{{-- Shown above a list when rows are ticked. --}}
@if ($count > 0)
    <div class="flex flex-wrap items-center gap-2 border-b border-brand-200 bg-brand-50 px-4 py-2 text-sm">
        <span class="font-medium text-brand-800">{{ trans_choice(':count selected|:count selected', $count, ['count' => shop_digits($count)]) }}</span>
        <span class="h-4 w-px bg-brand-200"></span>
        {{ $slot }}
        <button type="button" wire:click="clearSelection" class="ml-auto text-xs font-medium text-brand-700 hover:underline">{{ __('Clear selection') }}</button>
    </div>
@endif
