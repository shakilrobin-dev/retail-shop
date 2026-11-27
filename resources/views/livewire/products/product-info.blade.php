<div>
    <x-modal wire:model="open" :title="$product?->localName() ?? __('Product information')" :subtitle="$product ? collect([$product->sku, $product->brand?->name, $product->category?->localName()])->filter()->implode(' · ') : null">
        @if ($product)
            <div class="grid grid-cols-2 gap-3">
                <div @class(['rounded-lg border p-3', 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/40' => $branchStock <= 0, 'border-card-line bg-layer' => $branchStock > 0])>
                    <div class="text-xs text-muted-foreground-1">{{ __('Stock (this branch)') }}</div>
                    @if ($product->isMeasured())
                        <div @class(['mt-0.5 text-lg font-semibold tabular-nums', 'text-red-600' => $branchStock <= 0])>{{ shop_digits($product->measureForPieces($branchStock) + 0) }} {{ $product->measure_unit }}</div>
                        <div class="text-xs text-muted-foreground-1">{{ $product->stockBreakdown($branchStock) }}</div>
                    @else
                        <div @class(['mt-0.5 text-lg font-semibold tabular-nums', 'text-red-600' => $branchStock <= 0])>{{ $product->stockBreakdown($branchStock) }}</div>
                    @endif
                </div>
                <div class="rounded-lg border border-card-line bg-layer p-3">
                    <div class="text-xs text-muted-foreground-1">{{ __('Stock (all branches)') }}</div>
                    <div class="mt-0.5 text-lg font-semibold tabular-nums">{{ $product->isMeasured() ? shop_digits($product->measureForPieces($allBranchesStock) + 0).' '.$product->measure_unit : $product->stockBreakdown($allBranchesStock) }}</div>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto rounded-lg border border-card-line">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('Unit') }}</th>
                            @if ($canViewCost)<th class="text-right">{{ __('Buying price') }}</th>@endif
                            <th class="text-right">{{ __('Wholesale price') }}</th>
                            @if ($product->isMeasured())<th class="text-right">{{ $product->measure_unit }}</th>@endif
                            <th class="text-right">{{ __('Retail price') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($product->units as $unit)
                            <tr wire:key="info-unit-{{ $unit->id }}">
                                <td>
                                    {{ $unit->localName() }}
                                    @if ((float) $unit->quantity > 1)<span class="text-xs text-muted-foreground">({{ shop_digits($unit->quantity + 0) }})</span>@endif
                                </td>
                                @if ($canViewCost)
                                    <td class="text-right tabular-nums">{{ money((float) $product->cost_price * (float) $unit->quantity) }}</td>
                                @endif
                                <td class="text-right tabular-nums">{{ $unit->wholesale_price !== null ? money($unit->wholesale_price) : '—' }}</td>
                                @if ($product->isMeasured())<td class="text-right tabular-nums">{{ shop_digits($product->measureForPieces((float) $unit->quantity) + 0) }}</td>@endif
                                <td class="text-right font-medium tabular-nums">{{ money($unit->selling_price) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($product->isMeasured() && $product->measure_price !== null)
                <p class="mt-2 text-sm">{{ __('Selling rate') }}: <span class="font-semibold">{{ money($product->measure_price) }} / {{ $product->measure_unit }}</span></p>
            @endif
            @if ($product->min_selling_price !== null)
                <p class="mt-2 text-xs text-muted-foreground-1">{{ __('Minimum selling price') }}: {{ money($product->min_selling_price) }}</p>
            @endif
        @endif

        <x-slot:footer>
            @if ($product && $canManage)
                <a href="{{ route('products.edit', $product) }}" target="_blank" class="btn-secondary">{{ __('Edit product') }} ↗</a>
            @endif
            <button type="button" x-on:click="open = false" class="btn-primary">{{ __('Close') }}</button>
        </x-slot:footer>
    </x-modal>
</div>
