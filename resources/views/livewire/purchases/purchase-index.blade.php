<div>
    <x-flash />
    <x-page-header :title="__('Purchases')" :subtitle="__('Stock bought from suppliers. Every purchase adds stock and updates average cost.')">
        <button type="button" wire:click="export" class="btn-secondary"><x-icon name="receipt" class="size-4" /> {{ __('Export') }}</button>
        <a href="{{ route('suppliers.index') }}" class="btn-secondary" wire:navigate><x-icon name="building" class="size-4" /> {{ __('Suppliers') }}</a>
        <a href="{{ route('purchases.create') }}" class="btn-primary" wire:navigate><x-icon name="plus" class="size-4" /> {{ __('New purchase') }}</a>
    </x-page-header>

    <div class="mb-5 grid gap-4 sm:grid-cols-3">
        <x-stat :label="__('Purchases')" :value="shop_digits($filteredCount)" icon="truck" tone="brand" />
        <x-stat :label="__('Total bought')" :value="money($filteredTotal)" icon="banknotes" tone="amber" />
    </div>

    <div class="card overflow-hidden">
        <x-list-toolbar :placeholder="__('Purchase or invoice no')">
            <select wire:model.live="supplierId" class="form-input w-48">
                <option value="">{{ __('All suppliers') }}</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                @endforeach
            </select>
            <div class="flex items-center gap-1 rounded-lg border border-layer-line bg-layer px-2">
                <x-icon name="history" class="size-4 text-muted-foreground" />
                <input wire:model.live="from" type="date" class="border-0 py-1.5 text-sm focus:ring-0" title="{{ __('From') }}">
                <span class="text-muted-foreground">–</span>
                <input wire:model.live="to" type="date" class="border-0 py-1.5 text-sm focus:ring-0" title="{{ __('To') }}">
            </div>
        </x-list-toolbar>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <x-table.sort field="number" :sort="$sortField" :dir="$sortDirection">{{ __('Purchase no') }}</x-table.sort>
                        <x-table.sort field="date" :sort="$sortField" :dir="$sortDirection">{{ __('Date') }}</x-table.sort>
                        <th>{{ __('Supplier') }}</th>
                        <th>{{ __('Supplier invoice no') }}</th>
                        <x-table.sort field="items" :sort="$sortField" :dir="$sortDirection" align="right">{{ __('Items') }}</x-table.sort>
                        <x-table.sort field="total" :sort="$sortField" :dir="$sortDirection" align="right">{{ __('Total') }}</x-table.sort>
                        <th>{{ __('Entered by') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purchases as $purchase)
                        <tr wire:key="purchase-{{ $purchase->id }}" x-on:click="Livewire.navigate('{{ route('purchases.show', $purchase) }}')" class="cursor-pointer">
                            <td class="font-mono text-xs font-medium text-foreground">{{ $purchase->reference_no }}</td>
                            <td class="whitespace-nowrap text-muted-foreground-2">{{ shop_digits($purchase->purchase_date->format('d M Y')) }}</td>
                            <td>
                                @if ($purchase->supplier)
                                    <div class="flex items-center gap-2"><x-avatar :name="$purchase->supplier->name" size="size-6" /> {{ $purchase->supplier->name }}</div>
                                @else
                                    <span class="text-muted-foreground">{{ __('Local market') }}</span>
                                @endif
                            </td>
                            <td class="font-mono text-xs text-muted-foreground-1">{{ $purchase->supplier_invoice_no ?? '—' }}</td>
                            <td class="text-right tabular-nums">{{ shop_digits($purchase->items_count) }}</td>
                            <td class="text-right font-semibold tabular-nums text-foreground">{{ money($purchase->total) }}</td>
                            <td class="text-muted-foreground-1">{{ $purchase->user?->name }}</td>
                        </tr>
                    @empty
                        <x-empty :colspan="7" icon="truck" :title="__('No purchases found.')">
                            <a href="{{ route('purchases.create') }}" class="btn-primary" wire:navigate><x-icon name="plus" class="size-4" /> {{ __('New purchase') }}</a>
                        </x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-table.footer :paginator="$purchases" />
    </div>
</div>
