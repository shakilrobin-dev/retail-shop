<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-2">
        <div class="relative min-w-60 flex-1">
            <input wire:model.live.debounce.300ms="search" type="search" autofocus
                placeholder="{{ __('Search name, model, SKU or scan barcode') }}" class="form-input">
        </div>
        <select wire:model.live="categoryId" class="form-input w-auto">
            <option value="">{{ __('All categories') }}</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}">{{ $category->parent ? $category->parent->localName().' › ' : '' }}{{ $category->localName() }}</option>
            @endforeach
        </select>
        <select wire:model.live="brandId" class="form-input w-auto">
            <option value="">{{ __('All brands') }}</option>
            @foreach ($brands as $brand)
                <option value="{{ $brand->id }}">{{ $brand->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="stockFilter" class="form-input w-auto">
            <option value="">{{ __('Any stock') }}</option>
            <option value="low">{{ __('Low stock') }}</option>
            <option value="out">{{ __('Out of stock') }}</option>
        </select>
        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input wire:model.live="showInactive" type="checkbox" class="rounded border-slate-300"> {{ __('Show inactive') }}
        </label>
    </div>

    <div class="flex flex-wrap gap-2">
        <a href="{{ route('products.create') }}" class="btn-primary" wire:navigate>+ {{ __('New product') }}</a>
        <a href="{{ route('catalog.setup') }}" class="btn-secondary" wire:navigate>{{ __('Categories & brands') }}</a>
        <a href="{{ route('labels.index') }}" class="btn-secondary" wire:navigate>{{ __('Print barcode labels') }}</a>
    </div>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('Product') }}</th>
                    <th>{{ __('Category') }}</th>
                    <th>{{ __('Barcode') }}</th>
                    <th class="text-right">{{ __('Price') }}</th>
                    @can('products.view-cost')
                        <th class="text-right">{{ __('Cost') }}</th>
                    @endcan
                    <th class="text-right">{{ __('Stock') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($products as $product)
                    @php
                        $stockQty = (float) $product->branch_stock;
                        $isLow = $product->reorder_level > 0 && $stockQty <= (float) $product->reorder_level;
                        $baseUnit = $product->units->firstWhere('is_base', true);
                    @endphp
                    <tr wire:key="product-{{ $product->id }}" @class(['opacity-50' => ! $product->is_active])>
                        <td>
                            <div class="font-medium">{{ $product->localName() }}</div>
                            <div class="text-xs text-slate-500">
                                {{ $product->sku }}
                                @if ($product->brand) · {{ $product->brand->name }} @endif
                                @if ($product->compatible_models) · {{ $product->compatible_models }} @endif
                            </div>
                        </td>
                        <td class="text-slate-600">{{ $product->category?->localName() }}</td>
                        <td class="font-mono text-xs text-slate-600">
                            @foreach ($product->units as $unit)
                                @foreach ($unit->barcodes as $barcode)
                                    <div>{{ $barcode->code }} @if (! $unit->is_base)<span class="text-slate-400">({{ $unit->localName() }})</span>@endif</div>
                                @endforeach
                            @endforeach
                        </td>
                        <td class="text-right tabular-nums">
                            @foreach ($product->units as $unit)
                                <div>{{ money($unit->selling_price) }} <span class="text-xs text-slate-400">/ {{ $unit->localName() }}</span></div>
                            @endforeach
                        </td>
                        @can('products.view-cost')
                            <td class="text-right tabular-nums text-slate-600">{{ money($product->cost_price) }}</td>
                        @endcan
                        <td class="text-right">
                            <span @class(['badge', 'bg-red-50 text-red-700' => $stockQty <= 0, 'bg-amber-50 text-amber-700' => $isLow && $stockQty > 0, 'bg-emerald-50 text-emerald-700' => ! $isLow && $stockQty > 0])>
                                {{ $product->stockBreakdown($stockQty) }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap text-right">
                            <a href="{{ route('products.edit', $product) }}" class="btn-secondary btn-sm" wire:navigate>{{ __('Edit') }}</a>
                            <button type="button" wire:click="toggleActive({{ $product->id }})" class="btn-secondary btn-sm">
                                {{ $product->is_active ? __('Deactivate') : __('Activate') }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-10 text-center text-slate-500">{{ __('No products found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $products->links() }}
</div>
