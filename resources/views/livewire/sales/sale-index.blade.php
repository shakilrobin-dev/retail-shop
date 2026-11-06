@php $pageIds = $sales->pluck('id')->all(); @endphp
<div>
    <x-flash />
    <x-page-header :title="__('Sales')" :subtitle="__('Invoices, returns and reprints.')">
        <button type="button" wire:click="export" class="btn-secondary"><x-icon name="receipt" class="size-4" /> {{ __('Export') }}</button>
        <a href="{{ route('pos') }}" class="btn-primary" wire:navigate><x-icon name="cart" class="size-4" /> {{ __('New sale') }}</a>
    </x-page-header>

    <div class="mb-5 grid gap-4 sm:grid-cols-3">
        <x-stat :label="__('Invoices')" :value="shop_digits($count)" icon="receipt" tone="brand" />
        <x-stat :label="__('Total')" :value="money($sum)" icon="banknotes" tone="green" />
        <x-stat :label="__('Average sale')" :value="money($count ? $sum / $count : 0)" icon="trending-up" tone="slate" />
    </div>

    <div class="card overflow-hidden">
        <x-filter-tabs :tabs="$tabs" :active="$status" model="status" />
        <div class="border-t border-card-line"></div>
        <x-list-toolbar :placeholder="__('Invoice no or customer phone')">
            <div class="flex items-center gap-1 rounded-lg border border-layer-line bg-layer px-2">
                <x-icon name="history" class="size-4 text-muted-foreground" />
                <input wire:model.live="from" type="date" class="border-0 py-1.5 text-sm focus:ring-0" title="{{ __('From') }}">
                <span class="text-muted-foreground">–</span>
                <input wire:model.live="to" type="date" class="border-0 py-1.5 text-sm focus:ring-0" title="{{ __('To') }}">
            </div>
            <select wire:model.live="userId" class="form-input w-40">
                <option value="">{{ __('All staff') }}</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </select>
        </x-list-toolbar>
        <x-table.bulk-bar :count="count($selected)">
            <button type="button" wire:click="export" class="btn-secondary btn-sm"><x-icon name="receipt" class="size-3.5" /> {{ __('Export selected') }}</button>
        </x-table.bulk-bar>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <x-table.check-all :ids="$pageIds" :selected="$selected" />
                        <x-table.sort field="invoice" :sort="$sortField" :dir="$sortDirection">{{ __('Invoice') }}</x-table.sort>
                        <x-table.sort field="date" :sort="$sortField" :dir="$sortDirection">{{ __('Date') }}</x-table.sort>
                        <th>{{ __('Customer') }}</th>
                        <x-table.sort field="items" :sort="$sortField" :dir="$sortDirection" align="right">{{ __('Items') }}</x-table.sort>
                        <x-table.sort field="total" :sort="$sortField" :dir="$sortDirection" align="right">{{ __('Total') }}</x-table.sort>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Cashier') }}</th>
                        <th class="w-12"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sales as $sale)
                        <tr wire:key="sale-{{ $sale->id }}" x-on:click="Livewire.navigate('{{ route('sales.show', $sale) }}')" class="cursor-pointer">
                            <x-table.check :value="$sale->id" />
                            <td class="font-mono text-xs font-medium text-foreground">{{ $sale->invoice_no }}</td>
                            <td class="whitespace-nowrap text-muted-foreground-2">{{ shop_digits($sale->sold_at?->format('d M Y')) }}<div class="text-xs text-muted-foreground">{{ shop_digits($sale->sold_at?->format('h:i A')) }}</div></td>
                            <td>
                                @if ($sale->customer)
                                    <div class="flex items-center gap-2"><x-avatar :name="$sale->customer->name" size="size-6" /> <span>{{ $sale->customer->name }}</span></div>
                                @else
                                    <span class="text-muted-foreground">{{ __('Walk-in') }}</span>
                                @endif
                            </td>
                            <td class="text-right tabular-nums">{{ shop_digits($sale->items_count) }}</td>
                            <td class="text-right font-semibold tabular-nums text-foreground">{{ money($sale->total) }}</td>
                            <td><x-badge :tone="$sale->status === \App\Enums\SaleStatus::Voided ? 'red' : 'green'">{{ $sale->status->label() }}</x-badge></td>
                            <td class="text-muted-foreground-1">{{ $sale->user?->name }}</td>
                            <td class="text-right" x-on:click.stop>
                                <a href="{{ route('sales.receipt', $sale) }}" target="_blank" class="btn-ghost btn-sm" title="{{ __('Print') }}"><x-icon name="printer" class="size-4" /></a>
                            </td>
                        </tr>
                    @empty
                        <x-empty :colspan="9" icon="receipt" :title="__('No sales found.')" :text="__('Change the dates or search by invoice number.')" />
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-table.footer :paginator="$sales" />
    </div>
</div>
