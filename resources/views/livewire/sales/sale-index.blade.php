<div class="space-y-4">
    <x-flash />
    <div class="flex flex-wrap items-end gap-2">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('Invoice no or customer phone') }}" class="form-input max-w-xs">
        <input wire:model.live="from" type="date" class="form-input w-auto">
        <input wire:model.live="to" type="date" class="form-input w-auto">
        <select wire:model.live="status" class="form-input w-auto">
            <option value="">{{ __('All') }}</option>
            <option value="completed">{{ \App\Enums\SaleStatus::Completed->label() }}</option>
            <option value="voided">{{ \App\Enums\SaleStatus::Voided->label() }}</option>
        </select>
        <select wire:model.live="userId" class="form-input w-auto">
            <option value="">{{ __('All staff') }}</option>
            @foreach ($users as $user)
                <option value="{{ $user->id }}">{{ $user->name }}</option>
            @endforeach
        </select>
        <div class="flex-1"></div>
        <a href="{{ route('pos') }}" class="btn-primary" wire:navigate>+ {{ __('New sale') }}</a>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <div class="card p-4"><div class="text-sm text-slate-500">{{ __('Invoices') }}</div><div class="text-xl font-semibold">{{ shop_digits($count) }}</div></div>
        <div class="card p-4"><div class="text-sm text-slate-500">{{ __('Total') }}</div><div class="text-xl font-semibold">{{ money($sum) }}</div></div>
    </div>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>{{ __('Time') }}</th><th>{{ __('Invoice') }}</th><th>{{ __('Customer') }}</th><th class="text-right">{{ __('Items') }}</th><th class="text-right">{{ __('Total') }}</th><th>{{ __('Cashier') }}</th><th></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($sales as $sale)
                    <tr wire:key="sale-{{ $sale->id }}" @class(['text-slate-400 line-through' => $sale->status === \App\Enums\SaleStatus::Voided])>
                        <td class="whitespace-nowrap">{{ shop_digits($sale->sold_at?->format('d M h:i A')) }}</td>
                        <td class="font-mono text-xs">{{ $sale->invoice_no }}</td>
                        <td>{{ $sale->customer?->name ?? __('Walk-in') }}</td>
                        <td class="text-right">{{ shop_digits($sale->items_count) }}</td>
                        <td class="text-right tabular-nums">{{ money($sale->total) }}</td>
                        <td class="text-slate-500">{{ $sale->user?->name }}</td>
                        <td class="whitespace-nowrap text-right">
                            <a href="{{ route('sales.receipt', $sale) }}" target="_blank" class="btn-secondary btn-sm">{{ __('Print') }}</a>
                            <a href="{{ route('sales.show', $sale) }}" class="btn-secondary btn-sm" wire:navigate>{{ __('View') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-slate-500">{{ __('No sales found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $sales->links() }}
</div>
