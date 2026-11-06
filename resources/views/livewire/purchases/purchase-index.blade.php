<div class="space-y-5">
    <x-flash />
    <x-page-header class="mb-0" :title="__('Purchases')" :subtitle="__('Stock bought from suppliers. Every purchase adds stock and updates average cost.')">
        <a href="{{ route('suppliers.index') }}" class="btn-secondary" wire:navigate><x-icon name="building" class="size-4" /> {{ __('Suppliers') }}</a>
        <a href="{{ route('purchases.create') }}" class="btn-primary" wire:navigate><x-icon name="plus" class="size-4" /> {{ __('New purchase') }}</a>
    </x-page-header>
    <div class="card">
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 p-3">
            <div class="relative min-w-60 flex-1">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('Purchase or invoice no') }}" class="form-input pl-9">
            </div>
            <select wire:model.live="supplierId" class="form-input w-48">
                <option value="">{{ __('All suppliers') }}</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                @endforeach
            </select>
            <input wire:model.live="from" type="date" class="form-input w-40" title="{{ __('From') }}">
            <input wire:model.live="to" type="date" class="form-input w-40" title="{{ __('To') }}">
        </div>
        <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Purchase no') }}</th><th>{{ __('Supplier') }}</th><th class="text-right">{{ __('Items') }}</th><th class="text-right">{{ __('Total') }}</th><th>{{ __('By') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($purchases as $purchase)
                    <tr wire:key="purchase-{{ $purchase->id }}">
                        <td>{{ shop_digits($purchase->purchase_date->format('d M Y')) }}</td>
                        <td class="font-mono text-xs">{{ $purchase->reference_no }}@if ($purchase->supplier_invoice_no)<div class="text-slate-400">{{ $purchase->supplier_invoice_no }}</div>@endif</td>
                        <td>{{ $purchase->supplier?->name ?? '—' }}</td>
                        <td class="text-right">{{ shop_digits($purchase->items_count) }}</td>
                        <td class="text-right tabular-nums">{{ money($purchase->total) }}</td>
                        <td class="text-slate-500">{{ $purchase->user?->name }}</td>
                        <td class="text-right"><a href="{{ route('purchases.show', $purchase) }}" class="btn-secondary btn-sm" wire:navigate>{{ __('View') }}</a></td>
                    </tr>
                @empty
                    <x-empty :colspan="7" icon="truck" :title="__('No purchases found.')">
                        <a href="{{ route('purchases.create') }}" class="btn-primary" wire:navigate><x-icon name="plus" class="size-4" /> {{ __('New purchase') }}</a>
                    </x-empty>
                @endforelse
            </tbody>
            @if ($purchases->isNotEmpty())
                <tfoot><tr class="font-semibold"><td colspan="4" class="text-right">{{ __('Total') }}</td><td class="text-right tabular-nums">{{ money($filteredTotal) }}</td><td colspan="2"></td></tr></tfoot>
            @endif
        </table>
        </div>
    </div>
    {{ $purchases->links() }}
</div>
