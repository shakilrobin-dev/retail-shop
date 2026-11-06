@php
    $doneSteps = $setupSteps->where(2, true)->count();
    $gridValues = [$chartMax, $chartMax / 2, 0];
@endphp
<div>
    <x-page-header :title="__('Welcome, :name', ['name' => auth()->user()->name])" :subtitle="__('Summary for today').' · '.shop_digits(now()->format('l, d M Y'))">
        @can('repairs.manage')<a href="{{ route('repairs.create') }}" class="btn-secondary" wire:navigate><x-icon name="wrench" class="size-4" /> {{ __('New repair job') }}</a>@endcan
        @can('purchases.manage')<a href="{{ route('purchases.create') }}" class="btn-secondary" wire:navigate><x-icon name="truck" class="size-4" /> {{ __('New purchase') }}</a>@endcan
        @can('pos.sell')<a href="{{ route('pos') }}" class="btn-primary" wire:navigate><x-icon name="cart" class="size-4" /> {{ __('Open POS') }}</a>@endcan
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
        <x-stat :label="__('Today\'s sales')" :value="money($todaySalesTotal)" :hint="__(':count invoices', ['count' => shop_digits($todayInvoiceCount)])" icon="cart" tone="brand" :href="auth()->user()->can('pos.sell') ? route('sales.index') : null" />
        <x-stat :label="__('Today\'s repair income')" :value="money($todayRepairIncome)" icon="wrench" tone="green" />
        <x-stat :label="__('Repairs in progress')" :value="shop_digits($pendingRepairCount)" icon="history" tone="amber" :href="auth()->user()->can('repairs.manage') ? route('repairs.index') : null" />
        <x-stat :label="__('Ready for delivery')" :value="shop_digits($readyRepairCount)" icon="check" tone="sky" :href="auth()->user()->can('repairs.manage') ? route('repairs.index', ['status' => 'ready']) : null" />
        <x-stat :label="__('Low stock items')" :value="shop_digits($lowStockCount)" icon="warning" tone="red" :href="auth()->user()->can('products.manage') ? route('products.index', ['stockFilter' => 'low']) : null" />
        <x-stat :label="__('Active products')" :value="shop_digits($productCount)" icon="cube" tone="slate" :href="auth()->user()->can('products.manage') ? route('products.index') : null" />
    </div>

    <div class="mt-5 grid gap-5 xl:grid-cols-3">
        {{-- Sales last 14 days --}}
        <x-section :title="__('Sales – last 14 days')" class="xl:col-span-2">
            @can('reports.view')
                <x-slot:actions><a href="{{ route('reports.index', ['tab' => 'daily']) }}" wire:navigate class="text-xs font-medium text-primary-600 hover:underline">{{ __('View as table') }} →</a></x-slot:actions>
            @endcan
            @if ($chartMax <= 0)
                <div class="flex h-56 items-center justify-center">
                    <x-empty icon="chart" :title="__('No sales in the last 14 days yet.')" :text="__('The chart fills up as you sell from the POS.')" />
                </div>
            @else
            <div class="flex gap-3">
                <div class="flex h-48 flex-col justify-between text-right text-[11px] tabular-nums text-muted-foreground">
                    @foreach ($gridValues as $gridValue)
                        <span class="leading-none">{{ shop_digits(number_format($gridValue)) }}</span>
                    @endforeach
                </div>
                <div class="relative flex-1">
                    <div class="pointer-events-none absolute inset-x-0 top-0 h-48">
                        <div class="absolute inset-x-0 top-0 border-t border-dashed border-card-line"></div>
                        <div class="absolute inset-x-0 top-1/2 border-t border-dashed border-card-line"></div>
                        <div class="absolute inset-x-0 bottom-0 border-t border-layer-line"></div>
                    </div>
                    <div class="relative flex h-48 items-end gap-0.5">
                        @foreach ($chart as $point)
                            @php $height = $point['total'] > 0 ? max(2, round($point['total'] / max(1, $chartMax) * 100, 1)) : 0; @endphp
                            <div x-data="{ tip: false }" x-on:mouseenter="tip = true" x-on:mouseleave="tip = false" x-on:focus="tip = true" x-on:blur="tip = false"
                                class="group relative flex h-full flex-1 items-end justify-center outline-none" tabindex="0"
                                aria-label="{{ $point['date']->format('d M') }}: {{ money($point['total']) }}">
                                <div @class(['w-full max-w-6 rounded-t transition-colors group-hover:bg-primary-700', 'bg-primary-600' => $point['date']->isToday(), 'bg-primary-500' => ! $point['date']->isToday()]) style="height: {{ $height }}%"></div>
                                <div x-cloak x-show="tip" class="pointer-events-none absolute bottom-full z-10 mb-2 whitespace-nowrap rounded-lg bg-slate-900 px-2.5 py-1.5 text-xs text-white shadow-lg">
                                    <div class="font-medium">{{ shop_digits($point['date']->format('D, d M')) }}</div>
                                    <div class="tabular-nums">{{ money($point['total']) }} · {{ __(':count invoices', ['count' => shop_digits($point['invoices'])]) }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-2 flex gap-0.5 text-center text-[10px] text-muted-foreground">
                        @foreach ($chart as $point)
                            <span @class(['flex-1', 'font-semibold text-foreground' => $point['date']->isToday()])>{{ $loop->index % 2 === 1 || $loop->last ? shop_digits($point['date']->format('d/m')) : '' }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif
        </x-section>

        @if ($doneSteps < $setupSteps->count())
            <x-section :title="__('Get your shop ready')" :description="__(':done of :total done', ['done' => shop_digits($doneSteps), 'total' => shop_digits($setupSteps->count())])">
                <div class="mb-4 h-1.5 overflow-hidden rounded-full bg-surface">
                    <div class="h-full rounded-full bg-emerald-500" style="width: {{ $setupSteps->count() ? round($doneSteps / $setupSteps->count() * 100) : 0 }}%"></div>
                </div>
                <ul class="space-y-1">
                    @foreach ($setupSteps as [$label, $url, $done])
                        <li>
                            <a href="{{ $url }}" wire:navigate class="flex items-center gap-3 rounded-lg px-2 py-2 text-sm hover:bg-layer-hover">
                                <span @class(['flex size-5 shrink-0 items-center justify-center rounded-full border', 'border-emerald-500 bg-emerald-500 text-white' => $done, 'border-layer-line' => ! $done])>
                                    @if ($done)<svg class="size-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>@endif
                                </span>
                                <span @class(['flex-1', 'text-muted-foreground line-through' => $done, 'text-foreground' => ! $done])>{{ $label }}</span>
                                @unless ($done)<x-icon name="chevron-right" class="size-4 text-muted-foreground" />@endunless
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-section>
        @else
            @include('livewire.partials.dashboard-low-stock')
        @endif
    </div>

    <div class="mt-5 grid gap-5 xl:grid-cols-2">
        <x-section :title="__('Recent sales')" :padding="false">
            @can('pos.sell')
                <x-slot:actions><a href="{{ route('sales.index') }}" wire:navigate class="text-xs font-medium text-primary-600 hover:underline">{{ __('View all') }} →</a></x-slot:actions>
            @endcan
            <table class="data-table">
                <tbody>
                    @forelse ($recentSales as $sale)
                        <tr wire:key="dsale-{{ $sale->id }}">
                            <td class="w-20 text-xs text-muted-foreground-1">{{ shop_digits($sale->sold_at->format('h:i A')) }}</td>
                            <td class="font-mono text-xs">@can('pos.sell')<a href="{{ route('sales.show', $sale) }}" class="text-primary-600 hover:underline" wire:navigate>{{ $sale->invoice_no }}</a>@else{{ $sale->invoice_no }}@endcan</td>
                            <td>{{ $sale->customer?->name ?? __('Walk-in') }}</td>
                            <td class="text-right font-medium tabular-nums">{{ money($sale->total) }}</td>
                        </tr>
                    @empty
                        <x-empty :colspan="4" icon="receipt" :title="__('No sales yet.')" />
                    @endforelse
                </tbody>
            </table>
        </x-section>

        @if ($doneSteps < $setupSteps->count())
            @include('livewire.partials.dashboard-low-stock')
        @else
            <x-section :title="__('Keyboard shortcuts')">
                <div class="grid gap-2 text-sm sm:grid-cols-2">
                    @foreach ([['F2', __('Search product in POS')], ['F8', __('Hold sale')], ['F10', __('Pay / complete sale')], ['Ctrl K', __('Search anything')]] as [$key, $label])
                        <div class="flex items-center gap-2 rounded-lg border border-card-line px-3 py-2"><kbd class="rounded bg-surface px-1.5 py-0.5 font-mono text-xs">{{ $key }}</kbd> <span class="text-muted-foreground-2">{{ $label }}</span></div>
                    @endforeach
                </div>
            </x-section>
        @endif
    </div>
</div>
