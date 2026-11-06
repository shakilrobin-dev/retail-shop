@php
    $doneSteps = $setupSteps->where(2, true)->count();
    $gridValues = [$chartMax, $chartMax / 2, 0];
    $change = fn (float $now, float $before) => $before > 0 ? round(($now - $before) / $before * 100) : null;
    $todayChange = $change($todaySalesTotal, $yesterdayTotal);
    $monthChange = $change($month['net_sales'], $lastMonth['net_sales']);
    $mixColors = ['#4f46e5', '#d97706', '#059669', '#0284c7', '#e11d48', '#7c3aed'];
    $mixTotal = max(1, (float) $paymentMix->sum('money_in'));
    $repairColors = ['received' => 'bg-slate-400', 'diagnosing' => 'bg-sky-600', 'waiting_for_parts' => 'bg-amber-600', 'repairing' => 'bg-indigo-600', 'ready' => 'bg-emerald-600'];
    $repairMax = max(1, (int) $repairCounts->max());
    $topMax = max(1, (float) $topProducts->max('revenue'));
    $chartTotal = $chart->sum('total');
    $chartInvoices = $chart->sum('invoices');
    $canSell = auth()->user()->can('pos.sell');
@endphp
<div>
    <x-page-header :title="__('Welcome, :name', ['name' => auth()->user()->name])" :subtitle="shop_digits(now()->format('l, d M Y')).' · '.app(\App\Services\CurrentBranch::class)->get()?->localName()">
        @can('repairs.manage')<a href="{{ route('repairs.create') }}" class="btn-secondary" wire:navigate><x-icon name="wrench" class="size-4 text-amber-600" /> {{ __('New repair job') }}</a>@endcan
        @can('purchases.manage')<a href="{{ route('purchases.create') }}" class="btn-secondary" wire:navigate><x-icon name="truck" class="size-4 text-sky-600" /> {{ __('New purchase') }}</a>@endcan
        @can('pos.sell')<a href="{{ route('pos') }}" class="btn-primary" wire:navigate><x-icon name="cart" class="size-4" /> {{ __('Open POS') }}</a>@endcan
    </x-page-header>

    @if ($doneSteps < $setupSteps->count())
        <div x-data="{ open: false }" class="mb-5 rounded-lg border border-primary-200 bg-primary-50/60 dark:border-primary-500/30 dark:bg-primary-500/10">
            <button type="button" x-on:click="open = !open" class="flex w-full items-center gap-3 px-4 py-2.5 text-start">
                <span class="flex h-6 min-w-6 items-center justify-center rounded-md bg-primary px-1 text-[11px] font-bold text-primary-foreground">{{ shop_digits($doneSteps) }}/{{ shop_digits($setupSteps->count()) }}</span>
                <span class="flex-1 text-[13px] font-medium text-foreground">{{ __('Get your shop ready') }} <span class="font-normal text-muted-foreground-1">· {{ __('Next') }}: {{ $setupSteps->firstWhere(2, false)[0] }}</span></span>
                <div class="hidden h-1.5 w-40 overflow-hidden rounded-full bg-primary-100 sm:block dark:bg-primary-500/20"><div class="h-full rounded-full bg-primary" style="width: {{ round($doneSteps / max(1, $setupSteps->count()) * 100) }}%"></div></div>
                <x-icon name="chevron-down" class="size-4 text-muted-foreground-1" />
            </button>
            <div x-show="open" x-cloak class="grid gap-1 border-t border-primary-200 px-3 py-2 sm:grid-cols-2 lg:grid-cols-3 dark:border-primary-500/30">
                @foreach ($setupSteps as [$label, $url, $done])
                    <a href="{{ $url }}" wire:navigate class="flex items-center gap-2 rounded-md px-2 py-1.5 text-[13px] hover:bg-layer">
                        <span @class(['flex size-4 shrink-0 items-center justify-center rounded-full border', 'border-emerald-600 bg-emerald-600 text-white' => $done, 'border-layer-line bg-layer' => ! $done])>
                            @if ($done)<svg class="size-2.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>@endif
                        </span>
                        <span @class(['text-muted-foreground line-through' => $done, 'text-foreground' => ! $done])>{{ $label }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- KPIs --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
        <x-kpi :label="__('Today\'s sales')" :value="money($todaySalesTotal)" icon="cart" tone="indigo" :change="$todayChange" :changeLabel="__('vs yesterday')" :hint="__(':count invoices', ['count' => shop_digits($todayInvoiceCount)])" :href="auth()->user()->can('pos.sell') ? route('sales.index') : null" />
        <x-kpi :label="__('This month')" :value="money($month['net_sales'])" icon="trending-up" tone="emerald" :change="$monthChange" :changeLabel="__('vs last month')" :hint="__(':count invoices', ['count' => shop_digits($month['invoice_count'])])" />
        @can('products.view-cost')
            <x-kpi :label="__('Profit this month')" :value="money($month['net_profit'])" icon="banknotes" tone="violet" :hint="__('After expenses of :amount', ['amount' => money($month['expenses'])])" :href="auth()->user()->can('reports.view') ? route('reports.index') : null" />
        @endcan
        <x-kpi :label="__('Repairs in shop')" :value="shop_digits($pendingRepairCount + $readyRepairCount)" icon="wrench" tone="amber" :hint="__(':count ready for pickup', ['count' => shop_digits($readyRepairCount)])" :href="auth()->user()->can('repairs.manage') ? route('repairs.index') : null" />
        @can('products.view-cost')
            <x-kpi :label="__('Stock value')" :value="money($stockValue)" icon="archive" tone="sky" :hint="__(':count products', ['count' => shop_digits($productCount)])" :href="auth()->user()->can('stock.adjust') ? route('stock.index') : null" />
        @endcan
        <x-kpi :label="__('Stock alerts')" :value="shop_digits($lowStockCount)" icon="warning" tone="rose" :hint="__(':count out of stock', ['count' => shop_digits($outOfStockCount)])" :href="auth()->user()->can('products.manage') ? route('products.index', ['stockFilter' => 'low']) : null" />
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-3">
        <x-section class="xl:col-span-2">
            <x-slot:title>{{ __('Sales – last 30 days') }}</x-slot:title>
            <x-slot:actions>
                <span class="text-xs text-muted-foreground-1">{{ __('Total') }} <span class="font-semibold text-foreground">{{ money($chartTotal) }}</span> · {{ __(':count invoices', ['count' => shop_digits($chartInvoices)]) }}</span>
                @can('reports.view')<a href="{{ route('reports.index', ['tab' => 'daily']) }}" wire:navigate class="text-xs font-medium text-primary hover:underline">{{ __('Table') }} →</a>@endcan
            </x-slot:actions>
            @if ($chartMax <= 0)
                <div class="flex h-52 items-center justify-center"><x-empty icon="chart" :title="__('No sales in the last 30 days yet.')" :text="__('The chart fills up as you sell from the POS.')" /></div>
            @else
                <div class="flex gap-3">
                    <div class="flex h-52 flex-col justify-between text-right text-[11px] tabular-nums text-muted-foreground">
                        @foreach ($gridValues as $gridValue)<span class="leading-none">{{ shop_digits(number_format($gridValue)) }}</span>@endforeach
                    </div>
                    <div class="relative flex-1">
                        <div class="pointer-events-none absolute inset-x-0 top-0 h-52">
                            <div class="absolute inset-x-0 top-0 border-t border-dashed border-card-line"></div>
                            <div class="absolute inset-x-0 top-1/2 border-t border-dashed border-card-line"></div>
                            <div class="absolute inset-x-0 bottom-0 border-t border-layer-line"></div>
                        </div>
                        <div class="relative flex h-52 items-end gap-0.5">
                            @foreach ($chart as $point)
                                @php $height = $point['total'] > 0 ? max(2, round($point['total'] / $chartMax * 100, 1)) : 0; @endphp
                                <div x-data="{ tip: false }" x-on:mouseenter="tip = true" x-on:mouseleave="tip = false" x-on:focus="tip = true" x-on:blur="tip = false"
                                    class="group relative flex h-full flex-1 items-end justify-center outline-none" tabindex="0" aria-label="{{ $point['date']->format('d M') }}: {{ money($point['total']) }}">
                                    <div @class(['w-full max-w-5 rounded-t-sm transition-colors', 'bg-primary' => $point['date']->isToday(), 'bg-primary/55 group-hover:bg-primary' => ! $point['date']->isToday()]) style="height: {{ $height }}%"></div>
                                    <div x-cloak x-show="tip" class="pointer-events-none absolute bottom-full z-10 mb-2 whitespace-nowrap rounded-md bg-foreground px-2.5 py-1.5 text-xs text-background shadow-lg">
                                        <div class="font-medium">{{ shop_digits($point['date']->format('D, d M')) }}</div>
                                        <div class="tabular-nums">{{ money($point['total']) }} · {{ __(':count invoices', ['count' => shop_digits($point['invoices'])]) }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-1.5 flex gap-0.5 text-center text-[10px] text-muted-foreground">
                            @foreach ($chart as $point)
                                <span @class(['flex-1', 'font-semibold text-foreground' => $point['date']->isToday()])>{{ $loop->index % 5 === 4 || $loop->last ? shop_digits($point['date']->format('d/m')) : '' }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </x-section>

        <x-section :title="__('Payments – last 7 days')">
            @forelse ($paymentMix as $row)
                @php $share = round($row->money_in / $mixTotal * 100); $color = $mixColors[$loop->index % count($mixColors)]; @endphp
                <div class="mb-3 last:mb-0">
                    <div class="flex items-center justify-between text-[13px]">
                        <span class="flex items-center gap-2"><span class="size-2 rounded-full" style="background: {{ $color }}"></span>{{ app()->getLocale() === 'bn' && $row->name_bn ? $row->name_bn : $row->name }}</span>
                        <span class="tabular-nums"><span class="font-medium text-foreground">{{ money($row->money_in) }}</span> <span class="ms-1 text-xs text-muted-foreground-1">{{ shop_digits($share) }}%</span></span>
                    </div>
                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-surface"><div class="h-full rounded-full" style="width: {{ $share }}%; background: {{ $color }}"></div></div>
                </div>
            @empty
                <x-empty icon="banknotes" :title="__('No payments yet.')" />
            @endforelse
            @if ($shift && $shiftSummary)
                <div class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-[13px]">
                    <div class="flex items-center justify-between"><span class="flex items-center gap-1.5 font-medium text-emerald-800"><span class="size-1.5 rounded-full bg-emerald-500"></span> {{ __('Register is open') }}</span><span class="text-xs text-emerald-700">{{ __('Since') }} {{ shop_digits($shift->opened_at->format('h:i A')) }}</span></div>
                    <div class="mt-1 flex justify-between text-emerald-800"><span>{{ __('Cash that should be in drawer') }}</span><span class="font-semibold tabular-nums">{{ money($shiftSummary['expected']) }}</span></div>
                </div>
            @elseif (auth()->user()->can('cash-shifts.manage'))
                <a href="{{ route('cash-register') }}" wire:navigate class="mt-4 flex items-center justify-between rounded-md border border-dashed border-layer-line px-3 py-2.5 text-[13px] text-muted-foreground-1 hover:bg-muted">{{ __('Cash register is closed') }} <span class="font-medium text-primary">{{ __('Open') }} →</span></a>
            @endif
        </x-section>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
        <x-section :title="__('Top products – 7 days')" :padding="false">
            <ul class="divide-y divide-card-divider">
                @forelse ($topProducts as $row)
                    <li class="px-4 py-2.5">
                        <div class="flex items-center justify-between gap-3 text-[13px]">
                            <span class="flex min-w-0 items-center gap-2"><span class="w-4 text-xs tabular-nums text-muted-foreground">{{ shop_digits($loop->iteration) }}</span><span class="truncate text-foreground">{{ $row->name }}</span></span>
                            <span class="shrink-0 tabular-nums"><span class="font-medium">{{ money($row->revenue) }}</span> <span class="text-xs text-muted-foreground-1">· {{ shop_digits($row->quantity + 0) }} {{ __('pcs') }}</span></span>
                        </div>
                        <div class="ms-6 mt-1.5 h-1 overflow-hidden rounded-full bg-surface"><div class="h-full rounded-full bg-emerald-600" style="width: {{ round($row->revenue / $topMax * 100) }}%"></div></div>
                    </li>
                @empty
                    <li class="py-8"><x-empty icon="cube" :title="__('No sales in this period.')" /></li>
                @endforelse
            </ul>
        </x-section>

        <x-section :title="__('Repairs by status')">
            <x-slot:actions>@can('repairs.manage')<a href="{{ route('repairs.index') }}" wire:navigate class="text-xs font-medium text-primary hover:underline">{{ __('Board') }} →</a>@endcan</x-slot:actions>
            <div class="space-y-3">
                @foreach ([\App\Enums\RepairStatus::Received, \App\Enums\RepairStatus::Diagnosing, \App\Enums\RepairStatus::WaitingForParts, \App\Enums\RepairStatus::Repairing, \App\Enums\RepairStatus::Ready] as $status)
                    @php $count = (int) ($repairCounts[$status->value] ?? 0); @endphp
                    <div class="flex items-center gap-3 text-[13px]">
                        <span class="w-32 shrink-0 text-muted-foreground-2">{{ $status->label() }}</span>
                        <div class="h-5 flex-1 overflow-hidden rounded bg-surface"><div class="h-full rounded {{ $repairColors[$status->value] }}" style="width: {{ $count ? max(6, round($count / $repairMax * 100)) : 0 }}%"></div></div>
                        <span class="w-6 text-right font-medium tabular-nums">{{ shop_digits($count) }}</span>
                    </div>
                @endforeach
            </div>
            <div class="mt-4 flex justify-between border-t border-card-divider pt-3 text-xs text-muted-foreground-1">
                <span>{{ __('Repair income this month') }}</span><span class="font-semibold text-foreground">{{ money($month['repair_income']) }}</span>
            </div>
        </x-section>

        <x-section :title="__('Low stock')" :padding="false" class="lg:col-span-2 xl:col-span-1">
            <x-slot:actions>@can('products.manage')<a href="{{ route('products.index', ['stockFilter' => 'low']) }}" wire:navigate class="text-xs font-medium text-primary hover:underline">{{ __('View all') }} →</a>@endcan</x-slot:actions>
            <ul class="divide-y divide-card-divider">
                @forelse ($lowStock as $stock)
                    <li wire:key="dlow-{{ $stock->id }}" class="flex items-center justify-between gap-3 px-4 py-2.5 text-[13px]">
                        <span class="truncate">{{ $stock->product->localName() }}</span>
                        <span class="flex shrink-0 items-center gap-2"><span class="text-xs text-muted-foreground-1">{{ __('Alert at') }} {{ shop_digits($stock->product->reorder_level + 0) }}</span><x-badge :tone="$stock->quantity <= 0 ? 'red' : 'amber'">{{ shop_digits($stock->quantity + 0) }}</x-badge></span>
                    </li>
                @empty
                    <li class="py-8"><x-empty icon="check" :title="__('All products have enough stock.')" /></li>
                @endforelse
            </ul>
        </x-section>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-3">
        <x-section :title="__('Recent sales')" :padding="false" class="xl:col-span-2">
            <x-slot:actions>@can('pos.sell')<a href="{{ route('sales.index') }}" wire:navigate class="text-xs font-medium text-primary hover:underline">{{ __('View all') }} →</a>@endcan</x-slot:actions>
            <table class="data-table">
                <thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Time') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Cashier') }}</th><th class="text-right">{{ __('Total') }}</th></tr></thead>
                <tbody>
                    @forelse ($recentSales as $sale)
                        <tr wire:key="dsale-{{ $sale->id }}" @if ($canSell) x-on:click="Livewire.navigate('{{ route('sales.show', $sale) }}')" class="cursor-pointer" @endif>
                            <td class="font-mono text-xs text-foreground">{{ $sale->invoice_no }}</td>
                            <td class="text-xs text-muted-foreground-1">{{ shop_digits($sale->sold_at->format('d M, h:i A')) }}</td>
                            <td>@if ($sale->customer)<span class="flex items-center gap-2"><x-avatar :name="$sale->customer->name" size="size-5" class="text-[9px]" /> {{ $sale->customer->name }}</span>@else<span class="text-muted-foreground-1">{{ __('Walk-in') }}</span>@endif</td>
                            <td class="text-muted-foreground-1">{{ $sale->user?->name }}</td>
                            <td class="text-right font-medium tabular-nums">{{ money($sale->total) }}</td>
                        </tr>
                    @empty
                        <x-empty :colspan="5" icon="receipt" :title="__('No sales yet.')" />
                    @endforelse
                </tbody>
            </table>
        </x-section>

        <x-section :title="__('Recent activity')">
            <ul class="space-y-3">
                @forelse ($activities as $activity)
                    @php
                        $subject = $activity->subject;
                        $what = $subject?->name ?? $subject?->job_no ?? ($subject?->product?->name ?? null);
                        if (! $what && $subject instanceof \App\Models\Expense) { $what = __('expense').' · '.($subject->note ?? '').' '.money($subject->amount); }
                        $what ??= \Illuminate\Support\Str::headline(class_basename($activity->subject_type));
                        $verb = ['created' => __('created'), 'updated' => __('changed'), 'deleted' => __('deleted')][$activity->event] ?? $activity->description;
                    @endphp
                    <li class="flex gap-2.5 text-[13px]">
                        <x-avatar :name="$activity->causer?->name ?? __('System')" size="size-6" class="text-[10px]" />
                        <div class="min-w-0">
                            <p class="truncate"><span class="font-medium">{{ $activity->causer?->name ?? __('System') }}</span> <span class="text-muted-foreground-1">{{ $verb }}</span> {{ $what }}</p>
                            <p class="text-xs text-muted-foreground">{{ $activity->created_at->diffForHumans() }}</p>
                        </div>
                    </li>
                @empty
                    <li><p class="text-[13px] text-muted-foreground-1">{{ __('No changes recorded yet.') }}</p></li>
                @endforelse
            </ul>
            <div class="mt-4 flex justify-between border-t border-card-divider pt-3 text-xs text-muted-foreground-1">
                <span>{{ __('New customers this month') }}</span><span class="font-semibold text-foreground">{{ shop_digits($newCustomers) }}</span>
            </div>
        </x-section>
    </div>
</div>
