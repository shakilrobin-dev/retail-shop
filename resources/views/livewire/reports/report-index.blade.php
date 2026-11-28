@php
    $groups = [
        __('Financial') => ['summary' => __('Profit & loss'), 'payments' => __('Collections by payment'), 'expenses' => __('Expense report'), 'commissions' => __('Commission report')],
        __('Customer due') => ['customer-dues' => __('Customer due'), 'due-collections' => __('Due collections')],
        __('Sales') => ['daily' => __('Daily sales'), 'sales-register' => __('Sales register'), 'products' => __('Product sales'), 'categories' => __('Sales by category'), 'staff' => __('Sales by staff'), 'referrers' => __('Sales by reference')],
        __('Inventory') => ['stock-valuation' => __('Stock valuation'), 'dead-stock' => __('Dead stock')],
    ];
    $dated = \App\Services\ReportDocument::REPORTS[$activeTab][1];
@endphp
<div class="space-y-4">
    <x-page-header class="no-print mb-0" :title="__('Reports')" :subtitle="__('Official reports with letterhead and signatures. Print, or download as PDF or Excel.')" />

    <div class="grid gap-4 lg:grid-cols-[13rem_1fr]">
        <nav class="no-print card h-fit p-2 text-[13px]">
            @foreach ($groups as $group => $items)
                <p class="px-2 pb-1 pt-2 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">{{ $group }}</p>
                @foreach ($items as $key => $label)
                    <button type="button" wire:click="$set('tab', '{{ $key }}')" @class([
                        'flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left',
                        'bg-primary-50 font-medium text-primary-700' => $activeTab === $key,
                        'text-foreground hover:bg-muted-hover' => $activeTab !== $key,
                    ])>
                        <x-icon name="document" class="size-3.5 shrink-0 opacity-60" />
                        {{ $label }}
                    </button>
                @endforeach
            @endforeach
        </nav>

        <div class="min-w-0 space-y-4">
            <div class="no-print card flex flex-row flex-wrap items-end gap-2 p-3">
                @if ($activeTab === 'dead-stock')
                    <div>
                        <label class="form-label">{{ __('Not sold in (days)') }}</label>
                        <input wire:model.live.debounce.500ms="deadDays" type="number" min="1" class="form-input w-28">
                    </div>
                @elseif ($activeTab === 'customer-dues')
                    <div class="inline-flex rounded-md border border-layer-line p-0.5 text-[13px]">
                        @foreach (['summary' => __('Summary'), 'invoices' => __('Invoices'), 'items' => __('Invoices + items')] as $key => $label)
                            <button type="button" wire:click="$set('dueDetail', '{{ $key }}')" @class(['rounded px-2.5 py-1', 'bg-primary-600 text-white' => $dueDetail === $key, 'text-muted-foreground-2 hover:bg-muted-hover' => $dueDetail !== $key])>{{ $label }}</button>
                        @endforeach
                    </div>
                @elseif ($dated)
                    <input wire:model.live="from" type="date" class="form-input w-auto" aria-label="{{ __('From') }}">
                    <input wire:model.live="to" type="date" class="form-input w-auto" aria-label="{{ __('To') }}">
                    @foreach (['today' => __('Today'), 'yesterday' => __('Yesterday'), 'week' => __('This week'), 'month' => __('This month'), 'last-month' => __('Last month'), 'year' => __('This year')] as $key => $label)
                        <button type="button" wire:click="setPeriod('{{ $key }}')" class="chip">{{ $label }}</button>
                    @endforeach
                @else
                    <span class="self-center text-[13px] text-muted-foreground-1">{{ __('Shows stock as of now.') }}</span>
                @endif
                @if ($activeTab === 'categories')
                    <label class="inline-flex items-center gap-1.5 self-center text-[13px]"><input wire:model.live="mainCategories" type="checkbox" class="rounded border-layer-line"> {{ __('Main categories only') }}</label>
                @endif
                @if ($canSeeAll)
                    <select wire:model.live="scope" class="form-input w-auto">
                        <option value="current">{{ __('This branch') }}</option>
                        <option value="all">{{ __('All branches') }}</option>
                    </select>
                @endif
                <div class="flex-1"></div>
                <div class="flex gap-1.5">
                    <button type="button" onclick="window.print()" class="btn-secondary btn-sm"><x-icon name="printer" class="size-3.5" /> {{ __('Print') }}</button>
                    <a href="{{ $exportUrls['pdf'] }}" target="_blank" class="btn-secondary btn-sm"><x-icon name="document" class="size-3.5 text-rose-600" /> PDF</a>
                    <a href="{{ $exportUrls['xlsx'] }}" class="btn-secondary btn-sm"><x-icon name="table" class="size-3.5 text-emerald-600" /> Excel</a>
                    <a href="{{ $exportUrls['csv'] }}" class="btn-ghost btn-sm">CSV</a>
                </div>

                @if ($filterOptions !== [])
                    <div class="flex w-full flex-wrap items-end gap-3 border-t border-card-line pt-3">
                        @foreach ($filterOptions as $key => $filter)
                            <x-multi-select wire:model="filters.{{ $key }}" :label="$filter['label']" :options="$filter['options']"
                                wire:key="report-filter-{{ $key }}-{{ md5(json_encode(array_column($filter['options'], 'id'))) }}" />
                        @endforeach
                        @if ($activeFilterCount > 0)
                            <button type="button" wire:click="clearFilters" class="btn-ghost btn-sm mb-0.5 text-red-600">✕ {{ __('Clear filters') }}</button>
                        @endif
                    </div>
                @elseif ($activeTab === 'summary')
                    <p class="w-full border-t border-card-line pt-2 text-xs text-muted-foreground-1">{{ __('The profit & loss statement always covers the whole business. Use Sales by category, Product sales or Sales by reference to see one category, product or reference person.') }}</p>
                @endif
            </div>

            @if ($activeTab === 'summary')
                @php $figures = $document['figures']; @endphp
                <div class="no-print grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <x-kpi :label="__('Sales profit')" :value="money($figures['sales_profit'])" icon="cart" tone="indigo" :hint="__('Net sales').' '.money($figures['net_sales'])" />
                    @if ($figures['repair_income'] > 0 || app(\App\Services\Settings::class)->bool('repairs_enabled'))<x-kpi :label="__('Repair profit')" :value="money($figures['repair_profit'])" icon="wrench" tone="amber" :hint="__(':count delivered', ['count' => shop_digits($figures['repair_count'])])" />@endif
                    <x-kpi :label="__('Expenses')" :value="'− '.money($figures['expenses'])" icon="banknotes" tone="rose" />
                    <x-kpi :label="__('Net profit')" :value="money($figures['net_profit'])" icon="trending-up" :tone="$figures['net_profit'] >= 0 ? 'emerald' : 'rose'" />
                </div>
            @endif

            <div class="report-desk overflow-x-auto rounded-lg bg-muted p-3 sm:p-6" wire:loading.class="opacity-60">
                <div @class(['report-paper mx-auto bg-white shadow-sm ring-1 ring-black/5', 'max-w-[1120px]' => $document['landscape'], 'max-w-[820px]' => ! $document['landscape']])>
                    @include('reports.document', ['document' => $document, 'pdf' => false])
                </div>
            </div>
        </div>
    </div>

    <x-modal wire:model="showInvoiceList" :title="$invoiceList['title'] ?? __('Invoices')" :subtitle="$invoices['period'] ?? null" width="max-w-5xl">
        @if ($invoices)
            <div class="mb-3 flex flex-wrap gap-x-6 gap-y-1 text-sm">
                <span class="text-muted-foreground-1">{{ __('Invoices') }}: <strong class="text-foreground">{{ shop_digits($invoices['count']) }}</strong></span>
                <span class="text-muted-foreground-1">{{ __('Total') }}: <strong class="tabular-nums text-foreground">{{ money($invoices['total']) }}</strong></span>
                @if ($invoices['due'] > 0)<span class="text-muted-foreground-1">{{ __('Due') }}: <strong class="tabular-nums text-red-600">{{ money($invoices['due']) }}</strong></span>@endif
            </div>
            <div class="overflow-x-auto rounded-lg border border-card-line">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('Invoice') }}</th>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Customer') }}</th>
                            <th class="text-right">{{ __('Items') }}</th>
                            <th class="text-right">{{ __('Total') }}</th>
                            <th class="text-right">{{ __('Due') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Cashier') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($invoices['rows'] as $sale)
                            <tr wire:key="report-invoice-{{ $sale->id }}" wire:click="viewInvoice({{ $sale->id }})" class="cursor-pointer">
                                <td class="font-mono text-xs font-medium text-foreground">{{ $sale->invoice_no }}</td>
                                <td class="whitespace-nowrap text-muted-foreground-2">{{ shop_digits($sale->sold_at->format('d M Y')) }}<div class="text-xs text-muted-foreground">{{ shop_digits($sale->sold_at->format('h:i A')) }}</div></td>
                                <td>{{ $sale->customer?->name ?? __('Walk-in') }}@if ($sale->customer?->phone)<div class="text-xs text-muted-foreground">{{ $sale->customer->phone }}</div>@endif</td>
                                <td class="text-right tabular-nums">{{ shop_digits($sale->items_count) }}</td>
                                <td class="text-right font-semibold tabular-nums text-foreground">{{ money($sale->total) }}</td>
                                <td class="text-right tabular-nums">@if ($sale->due_amount > 0)<span class="font-medium text-red-600">{{ money($sale->due_amount) }}</span>@else<span class="text-muted-foreground">—</span>@endif</td>
                                <td><x-badge :tone="$sale->payment_status->tone()">{{ $sale->payment_status->label() }}</x-badge></td>
                                <td class="text-muted-foreground-1">{{ $sale->user?->name }}</td>
                                <td class="text-right"><button type="button" wire:click.stop="viewInvoice({{ $sale->id }})" class="btn-secondary btn-sm">{{ __('View') }}</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="py-6 text-center text-muted-foreground-1">{{ __('No invoices found.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($invoices['count'] > $invoices['rows']->count())
                <div class="mt-3 flex items-center justify-between gap-2 text-sm text-muted-foreground-1">
                    <span>{{ __('Showing :shown of :total', ['shown' => shop_digits($invoices['rows']->count()), 'total' => shop_digits($invoices['count'])]) }}</span>
                    <button type="button" wire:click="showMoreInvoices" class="btn-secondary btn-sm">{{ __('Show more') }}</button>
                </div>
            @endif
        @endif
    </x-modal>

    <x-modal wire:model="showInvoice" :title="$viewingSale ? __('Invoice').' '.$viewingSale->invoice_no : __('Invoice')" :subtitle="$viewingSale ? shop_digits($viewingSale->sold_at->format('d M Y h:i A')) : null" width="max-w-3xl">
        @if ($viewingSale)
            @php $sale = $viewingSale; @endphp
            <div class="space-y-4 text-sm">
                <div class="flex flex-wrap gap-1.5">
                    @if ($sale->status === \App\Enums\SaleStatus::Voided)
                        <x-badge tone="red">{{ $sale->status->label() }}</x-badge>
                    @else
                        <x-badge :tone="$sale->payment_status->tone()">{{ $sale->payment_status->label() }}</x-badge>
                    @endif
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div><div class="text-muted-foreground-1">{{ __('Customer') }}</div><div>{{ $sale->customer?->name ?? __('Walk-in') }}@if ($sale->customer?->phone) · {{ $sale->customer->phone }}@endif</div></div>
                    <div><div class="text-muted-foreground-1">{{ __('Cashier') }}</div><div>{{ $sale->user?->name ?? '—' }}</div></div>
                    <div><div class="text-muted-foreground-1">{{ __('Branch') }}</div><div>{{ $sale->branch?->localName() }}</div></div>
                    @if ($sale->referrer)
                        <div><div class="text-muted-foreground-1">{{ __('Reference') }}</div><div>{{ $sale->referrer->name }}@if ($sale->referrer->phone) · {{ $sale->referrer->phone }}@endif</div></div>
                    @endif
                    @if ($sale->due_date && $sale->due_amount > 0)
                        <div><div class="text-muted-foreground-1">{{ __('Pay by') }}</div><div @class(['text-red-600' => $sale->isOverdue()])>{{ shop_digits($sale->due_date->format('d M Y')) }}</div></div>
                    @endif
                </div>

                <div class="overflow-x-auto rounded-lg border border-card-line">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>{{ __('Product') }}</th>
                                <th class="text-right">{{ __('Qty') }}</th>
                                <th class="text-right">{{ __('Price') }}</th>
                                <th class="text-right">{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sale->items as $item)
                                <tr wire:key="report-invoice-item-{{ $item->id }}">
                                    <td>{{ $item->product_name }}@if ($item->unit_quantity > 1) <span class="text-xs text-muted-foreground-1">({{ $item->unit_name }})</span>@endif
                                        @if ($item->measure_quantity !== null)
                                            <div class="text-xs text-muted-foreground-1">{{ shop_digits($item->measure_quantity + 0) }} {{ $item->measure_unit }} × {{ money($item->measure_rate) }} · {{ $item->product?->stockBreakdown((float) $item->base_quantity) }}</div>
                                        @endif
                                        @if ($item->returned_quantity > 0)<div class="text-xs text-amber-700">{{ __('Returned') }}: {{ shop_digits($item->returned_quantity + 0) }}</div>@endif
                                    </td>
                                    <td class="text-right tabular-nums">{{ shop_digits($item->quantity + 0) }}</td>
                                    <td class="text-right tabular-nums">{{ money($item->unit_price) }}@if ($item->discount_amount > 0)<div class="text-xs text-muted-foreground">− {{ money($item->discount_amount) }}</div>@endif</td>
                                    <td class="text-right tabular-nums">{{ money($item->line_total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr><td colspan="3" class="text-right">{{ __('Subtotal') }}</td><td class="text-right tabular-nums">{{ money($sale->subtotal) }}</td></tr>
                            @if ($sale->discount_amount > 0)<tr><td colspan="3" class="text-right">{{ __('Discount') }}</td><td class="text-right tabular-nums">− {{ money($sale->discount_amount) }}</td></tr>@endif
                            @if ($sale->vat_amount > 0)<tr><td colspan="3" class="text-right">{{ __('VAT') }}</td><td class="text-right tabular-nums">{{ money($sale->vat_amount) }}</td></tr>@endif
                            @if ($sale->rounding_amount != 0)<tr><td colspan="3" class="text-right">{{ __('Rounding') }}</td><td class="text-right tabular-nums">{{ $sale->rounding_amount > 0 ? '+' : '−' }} {{ money(abs($sale->rounding_amount)) }}</td></tr>@endif
                            <tr class="font-semibold"><td colspan="3" class="text-right">{{ __('Total') }}</td><td class="text-right tabular-nums">{{ money($sale->total) }}</td></tr>
                            @if ($sale->status === \App\Enums\SaleStatus::Completed)
                                <tr><td colspan="3" class="text-right">{{ __('Paid') }}</td><td class="text-right tabular-nums">{{ money($sale->receivedAmount()) }}</td></tr>
                                @if ($sale->due_amount > 0)<tr class="font-semibold text-red-600"><td colspan="3" class="text-right">{{ __('Due') }}</td><td class="text-right tabular-nums">{{ money($sale->due_amount) }}</td></tr>@endif
                            @endif
                        </tfoot>
                    </table>
                </div>

                @if ($sale->payments->isNotEmpty())
                    <div>
                        <h3 class="mb-1 font-semibold">{{ __('Payments') }}</h3>
                        @foreach ($sale->payments as $payment)
                            <div class="flex justify-between py-0.5">
                                <span>{{ $payment->direction->label() }} · {{ $payment->paymentMethod->localName() }}@if ($payment->transaction_ref) <span class="text-muted-foreground">({{ $payment->transaction_ref }})</span>@endif
                                    <span class="text-xs text-muted-foreground">· {{ shop_digits($payment->paid_at->format('d M Y')) }}</span></span>
                                <span @class(['tabular-nums', 'text-red-700' => $payment->direction === \App\Enums\PaymentDirection::Out])>{{ money($payment->amount) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
                @if ($sale->note)<p class="text-muted-foreground-2">{{ $sale->note }}</p>@endif
            </div>
        @endif
        <x-slot:footer>
            @if ($viewingSale)
                @can('pos.sell')
                    <a href="{{ route('sales.receipt', ['sale' => $viewingSale, 'paper' => 'a4']) }}" target="_blank" class="btn-secondary btn-sm"><x-icon name="printer" class="size-3.5" /> {{ __('A4 invoice') }}</a>
                    @if ($viewingSale->branch_id === app(\App\Services\CurrentBranch::class)->get()?->id)
                        <a href="{{ route('sales.show', $viewingSale) }}" target="_blank" class="btn-secondary btn-sm">{{ __('Open invoice') }}</a>
                    @endif
                @endcan
            @endif
            <button type="button" wire:click="$set('showInvoice', false)" class="btn-primary btn-sm">{{ __('Close') }}</button>
        </x-slot:footer>
    </x-modal>

    @include('reports.styles')
    <style>
        .report-paper { padding: 40px 44px; min-height: 600px; }
        .rpt-link { color: #4338ca; font: inherit; text-decoration: underline dotted; text-underline-offset: 2px; cursor: pointer; }
        .rpt-link:hover { color: #312e81; text-decoration-style: solid; }
        @media print {
            @page { size: A4 {{ $document['landscape'] ? 'landscape' : 'portrait' }}; margin: 12mm; }
            .report-desk { background: none !important; padding: 0 !important; }
            .report-paper { box-shadow: none !important; padding: 0 !important; max-width: none !important; min-height: 0; --tw-ring-shadow: 0 0 #0000 !important; }
            .lg\:grid-cols-\[13rem_1fr\] { grid-template-columns: 1fr !important; }
            .rpt-table thead { display: table-header-group; }
            .rpt-link { color: inherit !important; text-decoration: none !important; }
        }
    </style>
</div>
