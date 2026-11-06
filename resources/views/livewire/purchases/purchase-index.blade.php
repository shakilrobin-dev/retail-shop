<div class="space-y-4">
    <x-flash />
    <div class="flex flex-wrap items-end gap-2">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('Purchase or invoice no') }}" class="form-input max-w-xs">
        <select wire:model.live="supplierId" class="form-input w-auto">
            <option value="">{{ __('All suppliers') }}</option>
            @foreach ($suppliers as $supplier)
                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
            @endforeach
        </select>
        <input wire:model.live="from" type="date" class="form-input w-auto" title="{{ __('From') }}">
        <input wire:model.live="to" type="date" class="form-input w-auto" title="{{ __('To') }}">
        <div class="flex-1"></div>
        <a href="{{ route('suppliers.index') }}" class="btn-secondary" wire:navigate>{{ __('Suppliers') }}</a>
        <a href="{{ route('purchases.create') }}" class="btn-primary" wire:navigate>+ {{ __('New purchase') }}</a>
    </div>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Purchase no') }}</th><th>{{ __('Supplier') }}</th><th class="text-right">{{ __('Items') }}</th><th class="text-right">{{ __('Total') }}</th><th>{{ __('By') }}</th><th></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
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
                    <tr><td colspan="7" class="py-8 text-center text-slate-500">{{ __('No purchases found.') }}</td></tr>
                @endforelse
            </tbody>
            @if ($purchases->isNotEmpty())
                <tfoot><tr class="font-semibold"><td colspan="4" class="text-right">{{ __('Total') }}</td><td class="text-right tabular-nums">{{ money($filteredTotal) }}</td><td colspan="2"></td></tr></tfoot>
            @endif
        </table>
    </div>
    {{ $purchases->links() }}
</div>
