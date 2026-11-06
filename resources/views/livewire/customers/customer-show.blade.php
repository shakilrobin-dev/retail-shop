<div class="mx-auto max-w-6xl space-y-5">
    <x-page-header class="mb-0" :title="$customer->name" :subtitle="$customer->phone" :back="route('customers.index')" :backLabel="__('Customers')" />
    <div class="card grid gap-4 p-5 text-sm md:grid-cols-4">
        <div><div class="text-slate-500">{{ __('Name') }}</div><div class="font-medium">{{ $customer->name }}</div></div>
        <div><div class="text-slate-500">{{ __('Phone') }}</div><div>{{ $customer->phone ?? '—' }}</div></div>
        <div><div class="text-slate-500">{{ __('Total spent') }}</div><div class="font-medium">{{ money($totalSpent) }}</div></div>
        <div><div class="text-slate-500">{{ __('Address') }}</div><div>{{ $customer->address ?? '—' }}</div></div>
        @if ($customer->notes)<div class="md:col-span-4 text-slate-600">{{ $customer->notes }}</div>@endif
    </div>

    <div class="card overflow-x-auto">
        <h3 class="px-4 pt-4 font-semibold">{{ __('Purchases & warranty') }}</h3>
        <table class="data-table">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Invoice') }}</th><th>{{ __('Items') }}</th><th class="text-right">{{ __('Total') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($sales as $sale)
                    <tr wire:key="csale-{{ $sale->id }}" @class(['text-slate-400 line-through' => $sale->status === \App\Enums\SaleStatus::Voided])>
                        <td class="whitespace-nowrap">{{ shop_digits($sale->sold_at?->format('d M Y')) }}</td>
                        <td class="font-mono text-xs">{{ $sale->invoice_no }}</td>
                        <td class="text-xs">
                            @foreach ($sale->items as $item)
                                <div>{{ $item->product_name }} × {{ shop_digits($item->quantity + 0) }}
                                    @if ($item->warranty_days)
                                        @php $until = $sale->sold_at->copy()->addDays($item->warranty_days); @endphp
                                        <span @class(['badge', 'bg-emerald-50 text-emerald-700' => $until->isFuture(), 'bg-slate-100 text-slate-500' => $until->isPast()])>
                                            {{ $until->isFuture() ? __('Warranty till :date', ['date' => shop_digits($until->format('d M Y'))]) : __('Warranty over') }}
                                        </span>
                                    @endif
                                </div>
                            @endforeach
                        </td>
                        <td class="text-right tabular-nums">{{ money($sale->total) }}</td>
                        <td class="text-right"><a href="{{ route('sales.show', $sale) }}" class="btn-secondary btn-sm" wire:navigate>{{ __('View') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-center text-slate-500">{{ __('No purchases yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

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
                    <tr><td colspan="6" class="py-6 text-center text-slate-500">{{ __('No repairs yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
