<div class="space-y-5">
    <x-flash />
    <x-page-header class="mb-0" :title="__('Sales')" :subtitle="__('Invoices, returns and reprints.')">
        <a href="{{ route('pos') }}" class="btn-primary" wire:navigate><x-icon name="cart" class="size-4" /> {{ __('New sale') }}</a>
    </x-page-header>
    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat :label="__('Invoices')" :value="shop_digits($count)" icon="receipt" tone="brand" />
        <x-stat :label="__('Total')" :value="money($sum)" icon="banknotes" tone="green" />
        <x-stat :label="__('Average sale')" :value="money($count ? $sum / $count : 0)" icon="trending-up" tone="slate" />
    </div>

    <div class="card">
    <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 p-3">
        <div class="relative min-w-60 flex-1"><x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" /><input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('Invoice no or customer phone') }}" class="form-input pl-9"></div>
        <input wire:model.live="from" type="date" class="form-input w-40">
        <input wire:model.live="to" type="date" class="form-input w-40">
        <select wire:model.live="status" class="form-input w-40">
            <option value="">{{ __('All') }}</option>
            <option value="completed">{{ \App\Enums\SaleStatus::Completed->label() }}</option>
            <option value="voided">{{ \App\Enums\SaleStatus::Voided->label() }}</option>
        </select>
        <select wire:model.live="userId" class="form-input w-40">
            <option value="">{{ __('All staff') }}</option>
            @foreach ($users as $user)
                <option value="{{ $user->id }}">{{ $user->name }}</option>
            @endforeach
        </select>
    </div>
        <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>{{ __('Time') }}</th><th>{{ __('Invoice') }}</th><th>{{ __('Customer') }}</th><th class="text-right">{{ __('Items') }}</th><th class="text-right">{{ __('Total') }}</th><th>{{ __('Cashier') }}</th><th></th></tr></thead>
            <tbody>
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
                    <x-empty :colspan="7" icon="receipt" :title="__('No sales found.')" :text="__('Change the dates or search by invoice number.')" />
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
    {{ $sales->links() }}
</div>
