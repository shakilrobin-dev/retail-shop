<div class="mx-auto max-w-6xl space-y-5" x-on:print-due-receipt.window="window.open($event.detail.url, '_blank')">
    <x-page-header class="mb-0" :title="$customer->name" :subtitle="$customer->phone" :back="route('customers.index')" :backLabel="__('Customers')">
        <a href="{{ $statementUrl }}" target="_blank" class="btn-secondary"><x-icon name="document" class="size-4" /> {{ __('Due statement (PDF)') }}</a>
        @if ($totalDue > 0)
            @can('customers.collect-due')
                <button type="button" wire:click="openCollect" class="btn-primary">{{ __('Collect due') }}</button>
            @endcan
        @endif
    </x-page-header>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi :label="__('Total due')" :value="money($totalDue)" icon="banknotes" :tone="$totalDue > 0 ? 'rose' : 'emerald'" :hint="__(':count unpaid invoices', ['count' => shop_digits($dueSales->count())])" />
        <x-kpi :label="__('Overdue')" :value="money($overdue)" icon="warning" :tone="$overdue > 0 ? 'amber' : 'slate'" :hint="$dueSales->first() ? __('Oldest: :days days', ['days' => shop_digits($dueSales->first()->ageInDays())]) : null" />
        <x-kpi :label="__('Credit limit')" :value="$customer->credit_limit !== null ? money($customer->credit_limit) : __('No limit')" icon="shield" tone="sky" :hint="$customer->credit_limit !== null ? __('Available').' '.money($customer->availableCredit()) : null" />
        <x-kpi :label="__('Total spent')" :value="money($totalSpent)" icon="cart" tone="indigo" />
    </div>

    <div class="card grid gap-4 p-5 text-sm md:grid-cols-3">
        <div><div class="text-muted-foreground-1">{{ __('Phone') }}</div><div>{{ $customer->phone ?? '—' }}</div></div>
        <div class="md:col-span-2"><div class="text-muted-foreground-1">{{ __('Address') }}</div><div>{{ $customer->address ?? '—' }}</div></div>
        @if ($customer->notes)<div class="md:col-span-3 text-muted-foreground-2">{{ $customer->notes }}</div>@endif
    </div>

    @if ($showCollect)
        <form wire:submit="collect" class="card space-y-3 p-5">
            <h3 class="card-title">{{ __('Collect due') }}</h3>
            <div class="grid gap-3 md:grid-cols-5">
                <div>
                    <label class="form-label">{{ __('Amount received') }}</label>
                    <input wire:model="collectAmount" type="number" step="0.01" min="0" class="form-input text-right" autofocus>
                    @error('collectAmount') <p class="form-error">{{ $message }}</p> @enderror
                    @error('amount') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">{{ __('Received by') }}</label>
                    <select wire:model="collectMethodId" class="form-input">
                        @foreach ($paymentMethods as $method)
                            <option value="{{ $method->id }}">{{ $method->localName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="form-label">{{ __('Apply to') }}</label>
                    <select wire:model="collectSaleId" class="form-input">
                        <option value="">{{ __('Oldest invoices first') }}</option>
                        @foreach ($dueSales as $dueSale)
                            <option value="{{ $dueSale->id }}">{{ $dueSale->invoice_no }} · {{ shop_digits($dueSale->sold_at->format('d M Y')) }} · {{ __('due') }} {{ money($dueSale->due_amount) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">{{ __('Transaction ID (optional)') }}</label>
                    <input wire:model="collectRef" type="text" class="form-input">
                </div>
                <div class="md:col-span-4">
                    <label class="form-label">{{ __('Note (optional)') }}</label>
                    <input wire:model="collectNote" type="text" class="form-input">
                </div>
                <div class="flex items-end gap-2">
                    <button type="button" wire:click="$set('showCollect', false)" class="btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn-primary flex-1">{{ __('Save & print') }}</button>
                </div>
            </div>
        </form>
    @endif

    @if ($dueSales->isNotEmpty())
        <div class="card overflow-x-auto">
            <div class="card-header"><h3 class="card-title">{{ __('Unpaid invoices') }}</h3></div>
            <table class="data-table">
                <thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Date') }}</th><th>{{ __('Pay by') }}</th><th class="text-right">{{ __('Days') }}</th><th class="text-right">{{ __('Total') }}</th><th class="text-right">{{ __('Paid') }}</th><th class="text-right">{{ __('Due') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($dueSales as $dueSale)
                        <tr wire:key="due-{{ $dueSale->id }}">
                            <td><a href="{{ route('sales.show', $dueSale) }}" wire:navigate class="font-mono text-xs text-primary-600 hover:underline">{{ $dueSale->invoice_no }}</a></td>
                            <td class="whitespace-nowrap">{{ shop_digits($dueSale->sold_at->format('d M Y')) }}</td>
                            <td class="whitespace-nowrap">
                                {{ $dueSale->due_date ? shop_digits($dueSale->due_date->format('d M Y')) : '—' }}
                                @if ($dueSale->isOverdue())<x-badge tone="red">{{ __('Overdue') }}</x-badge>@endif
                            </td>
                            <td class="text-right">{{ shop_digits($dueSale->ageInDays()) }}</td>
                            <td class="text-right tabular-nums">{{ money($dueSale->total) }}</td>
                            <td class="text-right tabular-nums">{{ money((float) $dueSale->total - (float) $dueSale->due_amount) }}</td>
                            <td class="text-right font-semibold tabular-nums text-red-600">{{ money($dueSale->due_amount) }}</td>
                            <td class="text-right">
                                @can('customers.collect-due')
                                    <button type="button" wire:click="openCollect({{ $dueSale->id }})" class="btn-secondary btn-sm">{{ __('Collect') }}</button>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot><tr class="font-semibold"><td colspan="6" class="text-right">{{ __('Total due') }}</td><td class="text-right tabular-nums text-red-600">{{ money($totalDue) }}</td><td></td></tr></tfoot>
            </table>
        </div>
    @endif

    @if ($collections->isNotEmpty())
        <div class="card overflow-x-auto">
            <div class="card-header"><h3 class="card-title">{{ __('Due payments received') }}</h3></div>
            <table class="data-table">
                <thead><tr><th>{{ __('Receipt no') }}</th><th>{{ __('Date') }}</th><th>{{ __('Received by') }}</th><th>{{ __('Staff') }}</th><th class="text-right">{{ __('Amount') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($collections as $collection)
                        <tr wire:key="col-{{ $collection->id }}">
                            <td class="font-mono text-xs">{{ $collection->receipt_no }}</td>
                            <td class="whitespace-nowrap">{{ shop_digits($collection->paid_at->format('d M Y h:i A')) }}</td>
                            <td>{{ $collection->paymentMethod->localName() }}@if ($collection->transaction_ref) <span class="text-xs text-muted-foreground">({{ $collection->transaction_ref }})</span>@endif</td>
                            <td>{{ $collection->user?->name }}</td>
                            <td class="text-right font-medium tabular-nums text-emerald-700">{{ money($collection->amount) }}</td>
                            <td class="text-right"><a href="{{ route('due-receipts.show', $collection) }}" target="_blank" class="btn-secondary btn-sm">{{ __('Print') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="card overflow-x-auto">
        <h3 class="px-4 pt-4 font-semibold">{{ __('Purchases & warranty') }}</h3>
        <table class="data-table">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Invoice') }}</th><th>{{ __('Items') }}</th><th class="text-right">{{ __('Total') }}</th><th class="text-right">{{ __('Due') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($sales as $sale)
                    <tr wire:key="csale-{{ $sale->id }}" @class(['text-muted-foreground line-through' => $sale->status === \App\Enums\SaleStatus::Voided])>
                        <td class="whitespace-nowrap">{{ shop_digits($sale->sold_at?->format('d M Y')) }}</td>
                        <td class="font-mono text-xs">{{ $sale->invoice_no }}</td>
                        <td class="text-xs">
                            @foreach ($sale->items as $item)
                                <div>{{ $item->product_name }} × {{ shop_digits($item->quantity + 0) }}
                                    @if ($item->warranty_days)
                                        @php $until = $sale->sold_at->copy()->addDays($item->warranty_days); @endphp
                                        <span @class(['badge', 'bg-emerald-50 text-emerald-700' => $until->isFuture(), 'bg-surface text-muted-foreground-1' => $until->isPast()])>
                                            {{ $until->isFuture() ? __('Warranty till :date', ['date' => shop_digits($until->format('d M Y'))]) : __('Warranty over') }}
                                        </span>
                                    @endif
                                </div>
                            @endforeach
                        </td>
                        <td class="text-right tabular-nums">{{ money($sale->total) }}</td>
                        <td class="text-right tabular-nums">@if ($sale->due_amount > 0)<span class="text-red-600">{{ money($sale->due_amount) }}</span>@else<span class="text-muted-foreground">—</span>@endif</td>
                        <td class="text-right"><a href="{{ route('sales.show', $sale) }}" class="btn-secondary btn-sm" wire:navigate>{{ __('View') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-6 text-center text-muted-foreground-1">{{ __('No purchases yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if (app(\App\Services\Settings::class)->bool('repairs_enabled'))
    <div class="card overflow-x-auto">
        <h3 class="px-4 pt-4 font-semibold">{{ __('Repairs') }}</h3>
        <table class="data-table">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Job no') }}</th><th>{{ __('Device') }}</th><th>{{ __('Status') }}</th><th class="text-right">{{ __('Total') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($repairs as $repair)
                    <tr wire:key="crepair-{{ $repair->id }}">
                        <td>{{ shop_digits($repair->created_at->format('d M Y')) }}</td>
                        <td class="font-mono text-xs">{{ $repair->job_no }}</td>
                        <td>{{ $repair->device_brand }} {{ $repair->device_model }}</td>
                        <td>{{ $repair->status->label() }}</td>
                        <td class="text-right tabular-nums">{{ money($repair->total) }}</td>
                        <td class="text-right"><a href="{{ route('repairs.show', $repair) }}" class="btn-secondary btn-sm" wire:navigate>{{ __('View') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-6 text-center text-muted-foreground-1">{{ __('No repairs yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endif
</div>
