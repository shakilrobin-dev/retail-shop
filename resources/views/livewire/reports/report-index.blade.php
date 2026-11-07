@php
    $groups = [
        __('Financial') => ['summary' => __('Profit & loss'), 'payments' => __('Collections by payment'), 'expenses' => __('Expense report')],
        __('Sales') => ['daily' => __('Daily sales'), 'sales-register' => __('Sales register'), 'products' => __('Product sales'), 'categories' => __('Sales by category'), 'staff' => __('Sales by staff')],
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
            </div>

            @if ($activeTab === 'summary')
                @php $figures = $document['figures']; @endphp
                <div class="no-print grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <x-kpi :label="__('Sales profit')" :value="money($figures['sales_profit'])" icon="cart" tone="indigo" :hint="__('Net sales').' '.money($figures['net_sales'])" />
                    <x-kpi :label="__('Repair profit')" :value="money($figures['repair_profit'])" icon="wrench" tone="amber" :hint="__(':count delivered', ['count' => shop_digits($figures['repair_count'])])" />
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

    @include('reports.styles')
    <style>
        .report-paper { padding: 40px 44px; min-height: 600px; }
        @media print {
            @page { size: A4 {{ $document['landscape'] ? 'landscape' : 'portrait' }}; margin: 12mm; }
            .report-desk { background: none !important; padding: 0 !important; }
            .report-paper { box-shadow: none !important; padding: 0 !important; max-width: none !important; min-height: 0; --tw-ring-shadow: 0 0 #0000 !important; }
            .lg\:grid-cols-\[13rem_1fr\] { grid-template-columns: 1fr !important; }
            .rpt-table thead { display: table-header-group; }
        }
    </style>
</div>
