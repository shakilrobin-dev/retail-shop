@php $tree = app(\App\Services\CategoryTree::class); $pageIds = $products->pluck('id')->all(); @endphp
<div>
    <x-flash />
    <x-page-header :title="__('Products')" :subtitle="__('Everything you sell. Stock is shown for the current branch.')">
        <button type="button" wire:click="export" class="btn-secondary"><x-icon name="receipt" class="size-4" /> {{ __('Export') }}</button>
        <a href="{{ route('catalog.setup') }}" class="btn-secondary" wire:navigate><x-icon name="folder" class="size-4" /> {{ __('Categories') }}</a>
        <a href="{{ route('products.create') }}" class="btn-primary" wire:navigate><x-icon name="plus" class="size-4" /> {{ __('New product') }}</a>
    </x-page-header>

    <div class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @can('products.view-cost')<x-kpi :label="__('Stock value (cost)')" :value="money($summary['value'])" icon="banknotes" tone="indigo" :hint="__('Current branch')" />@endcan
        <x-kpi :label="__('Units on hand')" :value="shop_digits(number_format($summary['units']))" icon="archive" tone="sky" :hint="__(':count products', ['count' => shop_digits($tabs[''][1])])" />
        @can('products.view-cost')<x-kpi :label="__('Average margin')" :value="shop_digits($summary['margin']).'%'" icon="trending-up" tone="emerald" :hint="__('Selling price vs cost')" />@endcan
        <x-kpi :label="__('Sold in 30 days')" :value="shop_digits($summary['sold'])" icon="cart" tone="violet" :hint="__('different products')" />
    </div>

    <div class="card overflow-hidden">
        <x-filter-tabs :tabs="$tabs" :active="$stockFilter" model="stockFilter" />
        <div class="border-t border-card-line"></div>

        <x-list-toolbar :placeholder="__('Search name, model, SKU or scan barcode')">
            <x-category-select wire:model.live="categoryId" :options="$categoryOptions" :placeholder="__('All categories')" class="w-56" />
            <select wire:model.live="brandId" class="form-input w-40">
                <option value="">{{ __('All brands') }}</option>
                @foreach ($brands as $brand)
                    <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                @endforeach
            </select>
            @if ($search !== '' || $categoryId || $brandId)
                <button type="button" wire:click="clearFilters" class="btn-ghost btn-sm">{{ __('Clear filters') }}</button>
            @endif
        </x-list-toolbar>

        <x-table.bulk-bar :count="count($selected)">
            <button type="button" wire:click="bulkPrintLabels" class="btn-secondary btn-sm"><x-icon name="tag" class="size-3.5" /> {{ __('Print labels') }}</button>
            <button type="button" wire:click="export" class="btn-secondary btn-sm"><x-icon name="receipt" class="size-3.5" /> {{ __('Export selected') }}</button>
            @if ($stockFilter === 'inactive')
                <button type="button" wire:click="bulkSetActive(true)" class="btn-secondary btn-sm"><x-icon name="eye" class="size-3.5" /> {{ __('Restore') }}</button>
            @else
                <button type="button" wire:click="bulkSetActive(false)" wire:confirm="{{ __('Archive the selected products? They will be hidden from POS.') }}" class="btn-secondary btn-sm"><x-icon name="eye-off" class="size-3.5" /> {{ __('Archive') }}</button>
            @endif
            <div class="flex items-center gap-1">
                <x-category-select wire:model="bulkCategoryId" :options="$categoryOptions" :placeholder="__('Move to category...')" class="w-52 py-1 text-xs" />
                <button type="button" wire:click="bulkMoveCategory" class="btn-secondary btn-sm">{{ __('Move') }}</button>
            </div>
            @error('bulkCategoryId') <span class="form-error mt-0">{{ $message }}</span> @enderror
        </x-table.bulk-bar>

        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <x-table.check-all :ids="$pageIds" :selected="$selected" />
                        <x-table.sort field="name" :sort="$sortField" :dir="$sortDirection">{{ __('Product') }}</x-table.sort>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Barcode') }}</th>
                        <x-table.sort field="price" :sort="$sortField" :dir="$sortDirection" align="right">{{ __('Price') }}</x-table.sort>
                        @can('products.view-cost')<x-table.sort field="cost" :sort="$sortField" :dir="$sortDirection" align="right">{{ __('Cost') }}</x-table.sort>@endcan
                        <x-table.sort field="stock" :sort="$sortField" :dir="$sortDirection" align="right">{{ __('On hand') }}</x-table.sort>
                        <th class="w-12"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        @php
                            $stockQty = (float) $product->branch_stock;
                            $isLow = $product->reorder_level > 0 && $stockQty <= (float) $product->reorder_level;
                            $barcodes = $product->units->flatMap->barcodes;
                        @endphp
                        <tr wire:key="product-{{ $product->id }}" x-on:click="Livewire.navigate('{{ route('products.edit', $product) }}')"
                            @class(['cursor-pointer', 'bg-primary-50/60' => in_array((string) $product->id, $selected, true)])>
                            <x-table.check :value="$product->id" />
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-surface text-muted-foreground"><x-icon name="cube" class="size-4" /></span>
                                    <div class="min-w-0">
                                        <div class="truncate font-medium text-foreground">{{ $product->localName() }}</div>
                                        <div class="truncate text-xs text-muted-foreground-1">
                                            <span class="font-mono">{{ $product->sku }}</span>
                                            @if ($product->brand) · {{ $product->brand->name }} @endif
                                            @if ($product->compatible_models) · {{ $product->compatible_models }} @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="max-w-56">
                                <span class="block truncate text-xs text-muted-foreground-2" title="{{ $tree->path($product->category_id) }}">{{ $tree->path($product->category_id) }}</span>
                            </td>
                            <td class="font-mono text-xs text-muted-foreground-2">
                                {{ $barcodes->first()?->code ?? '—' }}
                                @if ($barcodes->count() > 1)<span class="ml-1 rounded bg-surface px-1 font-sans text-[10px] text-muted-foreground-1">+{{ $barcodes->count() - 1 }}</span>@endif
                            </td>
                            <td class="text-right tabular-nums">
                                <div class="font-medium text-foreground">{{ money($product->base_price) }}</div>
                                @if ($product->units->count() > 1)
                                    @php $pack = $product->units->where('is_base', false)->first(); @endphp
                                    <div class="text-xs text-muted-foreground">{{ money($pack->selling_price) }} / {{ $pack->localName() }}</div>
                                @endif
                            </td>
                            @can('products.view-cost')
                                <td class="text-right tabular-nums text-muted-foreground-1">{{ money($product->cost_price) }}</td>
                            @endcan
                            <td class="text-right">
                                <x-badge :tone="$stockQty <= 0 ? 'red' : ($isLow ? 'amber' : 'green')">{{ $product->stockBreakdown($stockQty) }}</x-badge>
                            </td>
                            <td class="text-right" x-on:click.stop>
                                <x-dropdown trigger="⋯" width="min-w-48">
                                    <x-dropdown-item icon="pencil" :href="route('products.edit', $product)" wire:navigate>{{ __('Edit') }}</x-dropdown-item>
                                    <x-dropdown-item icon="tag" :href="route('labels.index', ['product' => $product->id])" wire:navigate>{{ __('Print labels') }}</x-dropdown-item>
                                    <x-dropdown-item icon="history" :href="route('stock.movements', ['productId' => $product->id])" wire:navigate>{{ __('Stock history') }}</x-dropdown-item>
                                    <x-dropdown-item :icon="$product->is_active ? 'eye-off' : 'eye'" wire:click="toggleActive({{ $product->id }})">{{ $product->is_active ? __('Archive') : __('Restore') }}</x-dropdown-item>
                                </x-dropdown>
                            </td>
                        </tr>
                    @empty
                        <x-empty :colspan="8" icon="cube" :title="__('No products found.')" :text="__('Try another search, or add your first product.')">
                            <a href="{{ route('products.create') }}" class="btn-primary" wire:navigate><x-icon name="plus" class="size-4" /> {{ __('New product') }}</a>
                        </x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-table.footer :paginator="$products" />
    </div>
</div>
