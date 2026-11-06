<div class="space-y-4">
    <x-flash />
    <div class="flex flex-wrap items-end gap-2">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('Search or scan') }}" class="form-input max-w-xs">
        <select wire:model.live="categoryId" class="form-input w-auto">
            <option value="">{{ __('All categories') }}</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}">{{ $category->localName() }}</option>
            @endforeach
        </select>
        <select wire:model.live="filter" class="form-input w-auto">
            <option value="">{{ __('All products') }}</option>
            <option value="low">{{ __('Low stock') }}</option>
            <option value="out">{{ __('Out of stock') }}</option>
        </select>
        <div class="flex-1"></div>
        <a href="{{ route('stock.movements') }}" class="btn-secondary" wire:navigate>{{ __('Stock history') }}</a>
        <a href="{{ route('stock.transfers.index') }}" class="btn-secondary" wire:navigate>{{ __('Transfers') }}</a>
        <a href="{{ route('stock.adjust') }}" class="btn-primary" wire:navigate>{{ __('Adjust / count stock') }}</a>
    </div>

    @can('products.view-cost')
        <div class="card p-4"><span class="text-sm text-slate-500">{{ __('Stock value (at cost) in this branch') }}:</span> <span class="text-lg font-semibold">{{ money($stockValue) }}</span></div>
    @endcan

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Category') }}</th><th class="text-right">{{ __('Stock') }}</th><th class="text-right">{{ __('Alert at') }}</th>@can('products.view-cost')<th class="text-right">{{ __('Value') }}</th>@endcan<th></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($products as $product)
                    @php $qty = (float) $product->branch_stock; $low = $product->reorder_level > 0 && $qty <= (float) $product->reorder_level; @endphp
                    <tr wire:key="stock-{{ $product->id }}">
                        <td><div class="font-medium">{{ $product->localName() }}</div><div class="text-xs text-slate-400">{{ $product->sku }}</div></td>
                        <td class="text-slate-600">{{ $product->category?->localName() }}</td>
                        <td class="text-right"><span @class(['badge', 'bg-red-50 text-red-700' => $qty <= 0, 'bg-amber-50 text-amber-700' => $low && $qty > 0, 'bg-emerald-50 text-emerald-700' => ! $low && $qty > 0])>{{ $product->stockBreakdown($qty) }}</span></td>
                        <td class="text-right text-slate-500">{{ shop_digits($product->reorder_level + 0) }}</td>
                        @can('products.view-cost')<td class="text-right tabular-nums">{{ money(max(0, $qty) * (float) $product->cost_price) }}</td>@endcan
                        <td class="text-right"><a href="{{ route('stock.movements', ['productId' => $product->id]) }}" class="btn-secondary btn-sm" wire:navigate>{{ __('History') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-slate-500">{{ __('No products found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $products->links() }}
</div>
