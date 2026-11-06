@php
    $tabLabels = [
        'summary' => __('Profit & loss'),
        'daily' => __('Daily sales'),
        'products' => __('Top products'),
        'categories' => __('By category'),
        'payments' => __('Payment methods'),
        'staff' => __('By staff'),
        'dead-stock' => __('Dead stock'),
    ];
@endphp
<div class="space-y-4">
    <x-page-header class="mb-0" :title="__('Reports')" :subtitle="__('Profit, sales and stock reports. Export any report to Excel.')" />
    <nav class="no-print flex gap-5 overflow-x-auto border-b border-slate-200">
        @foreach ($tabLabels as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')" class="{{ $activeTab === $key ? 'tab-link-active' : 'tab-link' }}">{{ $label }}</button>
        @endforeach
    </nav>

    <div class="no-print card flex flex-wrap items-end gap-2 p-3">
        @if ($activeTab === 'dead-stock')
            <div>
                <label class="form-label">{{ __('Not sold in (days)') }}</label>
                <input wire:model.live.debounce.500ms="deadDays" type="number" min="1" class="form-input w-28">
            </div>
        @else
            <input wire:model.live="from" type="date" class="form-input w-auto">
            <input wire:model.live="to" type="date" class="form-input w-auto">
            @foreach (['today' => __('Today'), 'yesterday' => __('Yesterday'), 'week' => __('This week'), 'month' => __('This month'), 'last-month' => __('Last month'), 'year' => __('This year')] as $key => $label)
                <button type="button" wire:click="setPeriod('{{ $key }}')" class="chip">{{ $label }}</button>
            @endforeach
        @endif
        @if ($canSeeAll)
            <select wire:model.live="scope" class="form-input w-auto">
                <option value="current">{{ __('This branch') }}</option>
                <option value="all">{{ __('All branches') }}</option>
            </select>
        @endif
        <div class="flex-1"></div>
        <a href="{{ $exportUrl }}" class="btn-secondary btn-sm">{{ __('Export to Excel (CSV)') }}</a>
        <button type="button" onclick="window.print()" class="btn-secondary btn-sm">{{ __('Print') }}</button>
    </div>

    @if ($activeTab === 'summary')
        <div class="grid gap-4 md:grid-cols-3">
            <div class="card p-5 text-sm md:col-span-2">
                <h3 class="mb-3 font-semibold">{{ __('Sales') }}</h3>
                <dl class="space-y-1.5">
                    <div class="flex justify-between"><dt>{{ __('Invoices') }}</dt><dd>{{ shop_digits($summary['invoice_count']) }} · {{ __('average') }} {{ money($summary['average_sale']) }}</dd></div>
                    <div class="flex justify-between"><dt>{{ __('Total sales (with VAT)') }}</dt><dd class="tabular-nums">{{ money($summary['gross_sales']) }}</dd></div>
                    <div class="flex justify-between text-slate-500"><dt>{{ __('Discounts given') }}</dt><dd class="tabular-nums">{{ money($summary['discounts']) }}</dd></div>
                    @if ($summary['vat'] > 0)<div class="flex justify-between"><dt>{{ __('VAT collected') }}</dt><dd class="tabular-nums">− {{ money($summary['vat']) }}</dd></div>@endif
                    <div class="flex justify-between"><dt>{{ __('Returns / refunds') }}</dt><dd class="tabular-nums">− {{ money($summary['returns']) }}</dd></div>
                    <div class="flex justify-between font-medium"><dt>{{ __('Net sales') }}</dt><dd class="tabular-nums">{{ money($summary['net_sales']) }}</dd></div>
                    <div class="flex justify-between"><dt>{{ __('Cost of goods sold') }}</dt><dd class="tabular-nums">− {{ money($summary['cost_of_goods']) }}</dd></div>
                    <div class="flex justify-between border-t border-slate-200 pt-1.5 font-semibold"><dt>{{ __('Sales profit') }}</dt><dd class="tabular-nums">{{ money($summary['sales_profit']) }}</dd></div>
                </dl>
                <h3 class="mb-3 mt-5 font-semibold">{{ __('Repairs') }}</h3>
                <dl class="space-y-1.5">
                    <div class="flex justify-between"><dt>{{ __('Delivered jobs') }}</dt><dd>{{ shop_digits($summary['repair_count']) }}</dd></div>
                    <div class="flex justify-between"><dt>{{ __('Repair income') }}</dt><dd class="tabular-nums">{{ money($summary['repair_income']) }}</dd></div>
                    <div class="flex justify-between"><dt>{{ __('Parts cost') }}</dt><dd class="tabular-nums">− {{ money($summary['repair_parts_cost']) }}</dd></div>
                    <div class="flex justify-between border-t border-slate-200 pt-1.5 font-semibold"><dt>{{ __('Repair profit') }}</dt><dd class="tabular-nums">{{ money($summary['repair_profit']) }}</dd></div>
                </dl>
            </div>
            <div class="space-y-4">
                <div class="card p-5"><div class="text-sm text-slate-500">{{ __('Sales profit') }} + {{ __('Repair profit') }}</div><div class="text-xl font-semibold">{{ money($summary['sales_profit'] + $summary['repair_profit']) }}</div></div>
                <div class="card p-5"><div class="text-sm text-slate-500">{{ __('Expenses') }}</div><div class="text-xl font-semibold text-red-700">− {{ money($summary['expenses']) }}</div></div>
                <div @class(['card p-5 ring-2', 'ring-emerald-300' => $summary['net_profit'] >= 0, 'ring-red-300' => $summary['net_profit'] < 0])>
                    <div class="text-sm text-slate-500">{{ __('Net profit') }}</div>
                    <div @class(['text-3xl font-bold', 'text-emerald-700' => $summary['net_profit'] >= 0, 'text-red-700' => $summary['net_profit'] < 0])>{{ money($summary['net_profit']) }}</div>
                </div>
            </div>
        </div>
    @else
        <div class="card overflow-x-auto">
            <table class="data-table">
                @switch($activeTab)
                    @case('daily')
                        <thead><tr><th>{{ __('Date') }}</th><th class="text-right">{{ __('Invoices') }}</th><th class="text-right">{{ __('Sales') }}</th><th class="text-right">{{ __('Profit') }}</th></tr></thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr><td>{{ shop_digits(\Illuminate\Support\Carbon::parse($row->day)->format('D, d M Y')) }}</td><td class="text-right">{{ shop_digits($row->invoices) }}</td><td class="text-right tabular-nums">{{ money($row->sales) }}</td><td class="text-right tabular-nums">{{ money($row->profit) }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="py-8 text-center text-slate-500">{{ __('No sales in this period.') }}</td></tr>
                            @endforelse
                        </tbody>
                        @if ($rows->isNotEmpty())
                            <tfoot><tr class="font-semibold"><td>{{ __('Total') }}</td><td class="text-right">{{ shop_digits($rows->sum('invoices')) }}</td><td class="text-right tabular-nums">{{ money($rows->sum('sales')) }}</td><td class="text-right tabular-nums">{{ money($rows->sum('profit')) }}</td></tr></tfoot>
                        @endif
                        @break
                    @case('products')
                        <thead><tr><th>#</th><th>{{ __('Product') }}</th><th class="text-right">{{ __('Qty sold (pcs)') }}</th><th class="text-right">{{ __('Sales') }}</th><th class="text-right">{{ __('Profit') }}</th></tr></thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr><td class="text-slate-400">{{ shop_digits($loop->iteration) }}</td><td>{{ $row->name }}</td><td class="text-right">{{ shop_digits($row->quantity + 0) }}</td><td class="text-right tabular-nums">{{ money($row->revenue) }}</td><td class="text-right tabular-nums">{{ money($row->profit) }}</td></tr>
                            @empty
                                <tr><td colspan="5" class="py-8 text-center text-slate-500">{{ __('No sales in this period.') }}</td></tr>
                            @endforelse
                        </tbody>
                        @break
                    @case('categories')
                        <thead><tr><th>
                            <span>{{ __('Category') }}</span>
                            <label class="ml-3 inline-flex items-center gap-1.5 normal-case tracking-normal"><input wire:model.live="mainCategories" type="checkbox" class="rounded border-slate-300"> {{ __('Main categories only') }}</label>
                        </th><th class="text-right">{{ __('Qty sold (pcs)') }}</th><th class="text-right">{{ __('Sales') }}</th><th class="text-right">{{ __('Profit') }}</th></tr></thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr><td>{{ $row['name'] }}</td><td class="text-right">{{ shop_digits($row['quantity'] + 0) }}</td><td class="text-right tabular-nums">{{ money($row['revenue']) }}</td><td class="text-right tabular-nums">{{ money($row['profit']) }}</td></tr>
                            @empty
                                <x-empty :colspan="4" icon="chart" :title="__('No sales in this period.')" />
                            @endforelse
                        </tbody>
                        @break
                    @case('payments')
                        <thead><tr><th>{{ __('Payment method') }}</th><th class="text-right">{{ __('Money in') }}</th><th class="text-right">{{ __('Money out') }}</th><th class="text-right">{{ __('Net') }}</th></tr></thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr><td>{{ app()->getLocale() === 'bn' && $row->name_bn ? $row->name_bn : $row->name }}</td><td class="text-right tabular-nums">{{ money($row->money_in) }}</td><td class="text-right tabular-nums">{{ money($row->money_out) }}</td><td class="text-right font-medium tabular-nums">{{ money($row->money_in - $row->money_out) }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="py-8 text-center text-slate-500">{{ __('No payments in this period.') }}</td></tr>
                            @endforelse
                        </tbody>
                        @break
                    @case('staff')
                        <thead><tr><th>{{ __('Staff') }}</th><th class="text-right">{{ __('Invoices') }}</th><th class="text-right">{{ __('Sales') }}</th><th class="text-right">{{ __('Discounts given') }}</th></tr></thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr><td>{{ $row->name ?? '—' }}</td><td class="text-right">{{ shop_digits($row->invoices) }}</td><td class="text-right tabular-nums">{{ money($row->sales) }}</td><td class="text-right tabular-nums">{{ money($row->discounts) }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="py-8 text-center text-slate-500">{{ __('No sales in this period.') }}</td></tr>
                            @endforelse
                        </tbody>
                        @break
                    @case('dead-stock')
                        <thead><tr><th>{{ __('Product') }}</th><th class="text-right">{{ __('In stock') }}</th><th class="text-right">{{ __('Money stuck (cost)') }}</th></tr></thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr><td>{{ $row->name }} <span class="text-xs text-slate-400">{{ $row->sku }}</span></td><td class="text-right">{{ shop_digits($row->quantity + 0) }}</td><td class="text-right tabular-nums">{{ money($row->value) }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="py-8 text-center text-slate-500">{{ __('No dead stock. Good!') }}</td></tr>
                            @endforelse
                        </tbody>
                        @if ($rows->isNotEmpty())
                            <tfoot><tr class="font-semibold"><td colspan="2">{{ __('Total') }}</td><td class="text-right tabular-nums">{{ money($rows->sum('value')) }}</td></tr></tfoot>
                        @endif
                        @break
                @endswitch
            </table>
        </div>
    @endif
</div>
