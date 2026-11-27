@props([
    'results',
    'message' => '',
    'placeholder' => null,
    'showStock' => true,
    'term' => '',
    'missing' => '',
    'quickCreate' => false,
    'quickCreateStock' => true,
])

{{--
    Scan/search box. Used with the SearchesProducts trait.
    With quick-create on, a search that finds nothing offers to add the product in a pop-up.
--}}
@php
    $canQuickCreate = $quickCreate && auth()->user()?->can('products.manage');
    $typedTerm = trim($term);
@endphp
<div class="relative" x-data="{ showMissing: true }" x-on:click.outside="showMissing = false" x-on:scan-not-found.window="$refs.scanInput.select()">
    <input x-ref="scanInput" wire:model.live.debounce.250ms="productSearch" wire:keydown.enter.prevent="scan"
        type="search" autocomplete="off" autofocus data-scan-input x-on:focus="showMissing = true" x-on:input="showMissing = true"
        placeholder="{{ $placeholder ?? __('Scan barcode or type product name...') }}"
        class="form-input h-10 text-sm">

    @if ($message)
        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
            <p class="text-sm text-red-600">{{ $message }}</p>
            @if ($canQuickCreate && $missing !== '')
                <button type="button" wire:click="$dispatch('open-quick-product', { term: @js($missing), withStock: @js((bool) $quickCreateStock) })" class="btn-secondary btn-sm">
                    <x-icon name="plus" class="size-3.5" /> {{ __('Add as new product') }}
                </button>
            @endif
        </div>
    @endif

    @if ($results->isNotEmpty())
        <div class="absolute z-30 mt-1 max-h-96 w-full overflow-y-auto rounded-lg bg-layer shadow-lg ring-1 ring-card-line">
            @foreach ($results as $result)
                <div wire:key="search-{{ $result->id }}" class="border-b border-card-divider px-3 py-2 last:border-0">
                    <div class="flex items-center justify-between gap-2 text-sm">
                        <span class="flex min-w-0 items-center gap-1.5">
                            <span class="font-medium">{{ $result->localName() }}</span>
                            <x-product-info-button :product-id="$result->id" />
                        </span>
                        @if ($showStock)
                            <span class="shrink-0 text-xs text-muted-foreground-1">{{ __('Stock') }}: {{ $result->stockBreakdown((float) $result->branch_stock) }}</span>
                        @endif
                    </div>
                    @if ($result->compatible_models)
                        <div class="text-xs text-muted-foreground">{{ $result->compatible_models }}</div>
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
    @elseif ($canQuickCreate && mb_strlen($typedTerm) >= 2)
        <div x-show="showMissing" wire:loading.remove wire:target="productSearch" class="absolute z-30 mt-1 w-full rounded-lg bg-layer px-3 py-3 shadow-lg ring-1 ring-card-line">
            <p class="text-sm text-muted-foreground-1">{{ __('No product found for ":code".', ['code' => $typedTerm]) }}</p>
            <button type="button" wire:click="$dispatch('open-quick-product', { term: @js($typedTerm), withStock: @js((bool) $quickCreateStock) })" class="btn-primary btn-sm mt-2">
                <x-icon name="plus" class="size-3.5" /> {{ __('Add ":name" as new product', ['name' => $typedTerm]) }}
            </button>
        </div>
    @endif
</div>
