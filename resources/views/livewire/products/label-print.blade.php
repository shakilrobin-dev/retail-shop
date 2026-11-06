<div class="mx-auto max-w-5xl space-y-5">
    <x-page-header class="mb-0" :title="__('Print barcode labels')" :subtitle="__('Scan or search products, set how many labels, then print.')" :back="route('products.index')" :backLabel="__('Products')" />
    <div class="card p-5">
        <label class="form-label" for="label-search">{{ __('Scan barcode or search product') }}</label>
        <input wire:model.live.debounce.300ms="search" wire:keydown.enter.prevent="addFromSearch" id="label-search" type="search" autofocus class="form-input">

        @if ($results->isNotEmpty())
            <div class="mt-2 divide-y divide-slate-100 rounded-lg border border-slate-200">
                @foreach ($results as $result)
                    <div wire:key="result-{{ $result->id }}" class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm">
                        <span class="font-medium">{{ $result->localName() }}</span>
                        <span class="flex flex-wrap gap-1">
                            @foreach ($result->units as $unit)
                                @forelse ($unit->barcodes as $barcode)
                                    <button type="button" wire:click="addBarcode({{ $barcode->id }})" class="btn-secondary btn-sm">
                                        + {{ $unit->localName() }} · <span class="font-mono">{{ $barcode->code }}</span>
                                    </button>
                                @empty
                                    <button type="button" wire:click="generateFor({{ $unit->id }})" class="btn-secondary btn-sm text-amber-700">
                                        {{ __('Generate barcode for :unit', ['unit' => $unit->localName()]) }}
                                    </button>
                                @endforelse
                            @endforeach
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Barcode') }}</th><th>{{ __('Price') }}</th><th class="w-32">{{ __('Labels') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($rows as $index => $row)
                    <tr wire:key="row-{{ $row['barcode_id'] }}">
                        <td>{{ $row['product'] }} @if ($row['unit'])<span class="text-xs text-slate-500">({{ $row['unit'] }})</span>@endif</td>
                        <td class="font-mono text-xs">{{ $row['code'] }}</td>
                        <td>{{ $row['price'] }}</td>
                        <td><input wire:model.live="rows.{{ $index }}.quantity" type="number" min="1" max="500" class="form-input"></td>
                        <td class="text-right"><button type="button" wire:click="removeRow({{ $index }})" class="btn-secondary btn-sm">✕</button></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-slate-500">{{ __('Scan or search products to add them here.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-500">
            {{ __('Label output') }}:
            <strong>{{ $labelOutput === 'thermal' ? __('Thermal label printer').' ('.$labelSize.' mm)' : __('A4 sticker sheet').' ('.$labelLayout.')' }}</strong>
            · {{ __('Change in Settings') }}
        </p>
        @if ($rows)
            <a href="{{ $this->printUrl() }}" target="_blank" class="btn-primary">{{ __('Print labels') }}</a>
        @endif
    </div>
</div>
