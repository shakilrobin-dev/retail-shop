@php $tree = app(\App\Services\CategoryTree::class); @endphp
<div>
    <x-flash />
    <x-page-header :title="__('Products')" :subtitle="trans_choice(':count product|:count products', $products->total(), ['count' => shop_digits($products->total())])">
        <a href="{{ route('labels.index') }}" class="btn-secondary" wire:navigate><x-icon name="tag" class="size-4" /> {{ __('Print labels') }}</a>
        <a href="{{ route('catalog.setup') }}" class="btn-secondary" wire:navigate><x-icon name="folder" class="size-4" /> {{ __('Categories') }}</a>
        <a href="{{ route('products.create') }}" class="btn-primary" wire:navigate><x-icon name="plus" class="size-4" /> {{ __('New product') }}</a>
    </x-page-header>

    <div class="card">
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 p-3">
            <div class="relative min-w-64 flex-1">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input wire:model.live.debounce.300ms="search" type="search" autofocus placeholder="{{ __('Search name, model, SKU or scan barcode') }}" class="form-input pl-9">
            </div>
            <x-category-select wire:model.live="categoryId" :options="$categoryOptions" :placeholder="__('All categories')" class="w-56" />
            <select wire:model.live="brandId" class="form-input w-40">
                <option value="">{{ __('All brands') }}</option>
                @foreach ($brands as $brand)
                    <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="stockFilter" class="form-input w-36">
                <option value="">{{ __('Any stock') }}</option>
                <option value="low">{{ __('Low stock') }}</option>
                <option value="out">{{ __('Out of stock') }}</option>
            </select>
            <label class="flex items-center gap-2 px-1 text-sm text-slate-600">
                <input wire:model.live="showInactive" type="checkbox" class="rounded border-slate-300"> {{ __('Show inactive') }}
            </label>
        </div>

        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Product') }}</th>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Barcode') }}</th>
                        <th class="text-right">{{ __('Price') }}</th>
                        @can('products.view-cost')<th class="text-right">{{ __('Cost') }}</th>@endcan
                        <th class="text-right">{{ __('Stock') }}</th>
                        <th class="w-24"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        @php
                            $stockQty = (float) $product->branch_stock;
                            $isLow = $product->reorder_level > 0 && $stockQty <= (float) $product->reorder_level;
                        @endphp
                        <tr wire:key="product-{{ $product->id }}" @class(['opacity-50' => ! $product->is_active])>
                            <td>
                                <a href="{{ route('products.edit', $product) }}" wire:navigate class="font-medium text-slate-900 hover:text-brand-700">{{ $product->localName() }}</a>
                                <div class="text-xs text-slate-500">
                                    <span class="font-mono">{{ $product->sku }}</span>
                                    @if ($product->brand) · {{ $product->brand->name }} @endif
                                    @if ($product->compatible_models) · {{ $product->compatible_models }} @endif
                                </div>
                            </td>
                            <td class="max-w-56 truncate text-xs text-slate-600" title="{{ $tree->path($product->category_id) }}">{{ $tree->path($product->category_id) }}</td>
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
                                <td class="text-right tabular-nums text-slate-500">{{ money($product->cost_price) }}</td>
                            @endcan
                            <td class="text-right">
                                <x-badge :tone="$stockQty <= 0 ? 'red' : ($isLow ? 'amber' : 'green')">{{ $product->stockBreakdown($stockQty) }}</x-badge>
                            </td>
                            <td class="whitespace-nowrap text-right">
                                <a href="{{ route('products.edit', $product) }}" class="btn-ghost btn-sm" wire:navigate title="{{ __('Edit') }}"><x-icon name="pencil" class="size-4" /></a>
                                <button type="button" wire:click="toggleActive({{ $product->id }})" class="btn-ghost btn-sm" title="{{ $product->is_active ? __('Deactivate') : __('Activate') }}">
                                    <x-icon :name="$product->is_active ? 'eye-off' : 'eye'" class="size-4" />
                                </button>
                            </td>
                        </tr>
                    @empty
                        <x-empty :colspan="7" icon="cube" :title="__('No products found.')" :text="__('Try another search, or add your first product.')">
                            <a href="{{ route('products.create') }}" class="btn-primary" wire:navigate><x-icon name="plus" class="size-4" /> {{ __('New product') }}</a>
                        </x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($products->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">{{ $products->links() }}</div>
        @endif
    </div>
</div>
