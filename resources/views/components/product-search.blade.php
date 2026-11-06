@props(['results', 'message' => '', 'placeholder' => null, 'showStock' => true])

{{-- Scan/search box. Used with the SearchesProducts trait. --}}
<div class="relative" x-data x-on:scan-not-found.window="$refs.scanInput.select()">
    <input x-ref="scanInput" wire:model.live.debounce.250ms="productSearch" wire:keydown.enter.prevent="scan"
        type="search" autocomplete="off" autofocus data-scan-input
        placeholder="{{ $placeholder ?? __('Scan barcode or type product name...') }}"
        class="form-input py-3 text-base">

    @if ($message)
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @endif

    @if ($results->isNotEmpty())
        <div class="absolute z-30 mt-1 max-h-96 w-full overflow-y-auto rounded-lg bg-white shadow-lg ring-1 ring-slate-200">
            @foreach ($results as $result)
                <div wire:key="search-{{ $result->id }}" class="border-b border-slate-100 px-3 py-2 last:border-0">
                    <div class="flex items-center justify-between gap-2 text-sm">
                        <span class="font-medium">{{ $result->localName() }}</span>
                        @if ($showStock)
                            <span class="text-xs text-slate-500">{{ __('Stock') }}: {{ $result->stockBreakdown((float) $result->branch_stock) }}</span>
                        @endif
                    </div>
                    @if ($result->compatible_models)
                        <div class="text-xs text-slate-400">{{ $result->compatible_models }}</div>
                    @endif
                    <div class="mt-1 flex flex-wrap gap-1">
                        @foreach ($result->units as $unit)
                            <button type="button" wire:click="pickFromSearch({{ $unit->id }})" class="btn-secondary btn-sm">
                                + {{ $unit->localName() }} · {{ money($unit->selling_price) }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
