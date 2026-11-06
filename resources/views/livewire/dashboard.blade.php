@php
    $cards = [
        [__('Today\'s sales'), money($todaySalesTotal), __(':count invoices', ['count' => shop_digits($todayInvoiceCount)]), 'cart', 'bg-indigo-50 text-indigo-600'],
        [__('Today\'s repair income'), money($todayRepairIncome), null, 'wrench', 'bg-emerald-50 text-emerald-600'],
        [__('Repairs in progress'), shop_digits($pendingRepairCount), null, 'wrench', 'bg-amber-50 text-amber-600'],
        [__('Ready for delivery'), shop_digits($readyRepairCount), null, 'check', 'bg-sky-50 text-sky-600'],
        [__('Low stock items'), shop_digits($lowStockCount), null, 'warning', 'bg-red-50 text-red-600'],
        [__('Active products'), shop_digits($productCount), null, 'cube', 'bg-slate-100 text-slate-600'],
    ];
@endphp

<div class="space-y-6">
    <div>
        <h2 class="text-xl font-semibold">{{ __('Welcome, :name', ['name' => auth()->user()->name]) }}</h2>
        <p class="text-sm text-slate-500">{{ __('Summary for today') }} · {{ shop_digits(now()->format('d M Y')) }}</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($cards as [$label, $value, $hint, $icon, $iconClass])
            <div class="flex items-start gap-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="rounded-lg p-2.5 {{ $iconClass }}">
                    <x-icon :name="$icon" class="size-6" />
                </div>
                <div class="min-w-0">
                    <div class="text-sm text-slate-500">{{ $label }}</div>
                    <div class="mt-1 text-2xl font-semibold tabular-nums">{{ $value }}</div>
                    @if ($hint)
                        <div class="mt-0.5 text-xs text-slate-400">{{ $hint }}</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="flex flex-wrap gap-2">
        @can('pos.sell')<a href="{{ route('pos') }}" class="btn-primary py-3" wire:navigate>{{ __('POS / Sale') }}</a>@endcan
        @can('repairs.manage')<a href="{{ route('repairs.create') }}" class="btn-secondary py-3" wire:navigate>+ {{ __('New repair job') }}</a>@endcan
        @can('purchases.manage')<a href="{{ route('purchases.create') }}" class="btn-secondary py-3" wire:navigate>+ {{ __('New purchase') }}</a>@endcan
        @can('products.manage')<a href="{{ route('products.create') }}" class="btn-secondary py-3" wire:navigate>+ {{ __('New product') }}</a>@endcan
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="card overflow-x-auto">
            <h3 class="px-4 pt-4 font-semibold">{{ __('Recent sales') }}</h3>
            <table class="data-table">
                <tbody class="divide-y divide-slate-100">
                    @forelse ($recentSales as $sale)
                        <tr wire:key="dsale-{{ $sale->id }}">
                            <td class="text-xs text-slate-500">{{ shop_digits($sale->sold_at->format('h:i A')) }}</td>
                            <td class="font-mono text-xs">@can('pos.sell')<a href="{{ route('sales.show', $sale) }}" class="text-indigo-600 hover:underline" wire:navigate>{{ $sale->invoice_no }}</a>@endcan</td>
                            <td>{{ $sale->customer?->name ?? __('Walk-in') }}</td>
                            <td class="text-right tabular-nums">{{ money($sale->total) }}</td>
                        </tr>
                    @empty
                        <tr><td class="py-6 text-center text-slate-500">{{ __('No sales in this period.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card overflow-x-auto">
            <h3 class="px-4 pt-4 font-semibold">{{ __('Low stock items') }}</h3>
            <table class="data-table">
                <tbody class="divide-y divide-slate-100">
                    @forelse ($lowStock as $stock)
                        <tr wire:key="dlow-{{ $stock->id }}">
                            <td>{{ $stock->product->localName() }}</td>
                            <td class="text-right"><span class="badge bg-amber-50 text-amber-700">{{ shop_digits($stock->quantity + 0) }} / {{ shop_digits($stock->product->reorder_level + 0) }}</span></td>
                        </tr>
                    @empty
                        <tr><td class="py-6 text-center text-slate-500">{{ __('No products found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
