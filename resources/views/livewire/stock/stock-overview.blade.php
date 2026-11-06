@php $tree = app(\App\Services\CategoryTree::class); @endphp
<div>
    <x-flash />
    <x-page-header :title="__('Stock')" :subtitle="__('Stock in this branch, counted in base units.')">
        <button type="button" wire:click="export" class="btn-secondary"><x-icon name="receipt" class="size-4" /> {{ __('Export') }}</button>
        <a href="{{ route('stock.movements') }}" class="btn-secondary" wire:navigate><x-icon name="history" class="size-4" /> {{ __('Stock history') }}</a>
        @can('stock.transfer')<a href="{{ route('stock.transfers.index') }}" class="btn-secondary" wire:navigate><x-icon name="transfer" class="size-4" /> {{ __('Transfers') }}</a>@endcan
        <a href="{{ route('stock.adjust') }}" class="btn-primary" wire:navigate><x-icon name="clipboard" class="size-4" /> {{ __('Adjust / count stock') }}</a>
    </x-page-header>

    @can('products.view-cost')
        <div class="mb-5 grid gap-4 sm:grid-cols-3">
            <x-stat :label="__('Stock value (at cost) in this branch')" :value="money($stockValue)" icon="banknotes" tone="brand" />
        </div>
    @endcan

    <div class="card overflow-hidden">
        <div class="flex flex-wrap items-center gap-2 border-b border-card-divider p-3">
            <div class="relative min-w-64 flex-1">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('Search or scan') }}" class="form-input pl-9">
            </div>
            <x-category-select wire:model.live="categoryId" :options="$categoryOptions" :placeholder="__('All categories')" class="w-56" />
            <select wire:model.live="filter" class="form-input w-40">
                <option value="">{{ __('All products') }}</option>
                <option value="low">{{ __('Low stock') }}</option>
                <option value="out">{{ __('Out of stock') }}</option>
            </select>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr>
                    <x-table.sort field="name" :sort="$sortField" :dir="$sortDirection">{{ __('Product') }}</x-table.sort>
                    <th>{{ __('Category') }}</th>
                    <x-table.sort field="stock" :sort="$sortField" :dir="$sortDirection" align="right">{{ __('On hand') }}</x-table.sort>
                    <x-table.sort field="alert" :sort="$sortField" :dir="$sortDirection" align="right">{{ __('Alert at') }}</x-table.sort>
                    @can('products.view-cost')<x-table.sort field="value" :sort="$sortField" :dir="$sortDirection" align="right">{{ __('Value') }}</x-table.sort>@endcan
                    <th></th>
                </tr></thead>
                <tbody>
                    @forelse ($products as $product)
                        @php $qty = (float) $product->branch_stock; $low = $product->reorder_level > 0 && $qty <= (float) $product->reorder_level; @endphp
                        <tr wire:key="stock-{{ $product->id }}">
                            <td><div class="font-medium text-foreground">{{ $product->localName() }}</div><div class="font-mono text-xs text-muted-foreground">{{ $product->sku }}</div></td>
                            <td class="max-w-56 truncate text-xs text-muted-foreground-2">{{ $tree->path($product->category_id) }}</td>
                            <td class="text-right"><x-badge :tone="$qty <= 0 ? 'red' : ($low ? 'amber' : 'green')">{{ $product->stockBreakdown($qty) }}</x-badge></td>
                            <td class="text-right text-muted-foreground-1">{{ shop_digits($product->reorder_level + 0) }}</td>
                            @can('products.view-cost')<td class="text-right tabular-nums">{{ money(max(0, $qty) * (float) $product->cost_price) }}</td>@endcan
                            <td class="text-right"><a href="{{ route('stock.movements', ['productId' => $product->id]) }}" class="btn-ghost btn-sm" wire:navigate><x-icon name="history" class="size-4" /> {{ __('History') }}</a></td>
                        </tr>
                    @empty
                        <x-empty :colspan="6" icon="archive" :title="__('No products found.')" />
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-table.footer :paginator="$products" />
    </div>
</div>
