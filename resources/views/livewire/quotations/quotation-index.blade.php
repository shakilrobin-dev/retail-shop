<div x-data x-init="@if ($printId) window.open(@js(route('quotations.print', $printId)), '_blank') @endif">
    <x-flash />
    <x-page-header :title="__('Quotations')" :subtitle="__('Price offers for customers. Print them, or open one in the POS to sell.')">
        <x-branch-badge :branch="$branch" />
        <a href="{{ route('quotations.create') }}" wire:navigate class="btn-primary"><x-icon name="plus" class="size-4" /> {{ __('New quotation') }}</a>
    </x-page-header>

    <div class="card overflow-hidden">
        <x-list-toolbar :placeholder="__('Search number, name or phone')" />
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <x-table.sort field="no" :sort="$sortField" :dir="$sortDirection">{{ __('Quotation') }}</x-table.sort>
                        <x-table.sort field="date" :sort="$sortField" :dir="$sortDirection">{{ __('Date') }}</x-table.sort>
                        <th>{{ __('Customer') }}</th>
                        <th class="text-right">{{ __('Items') }}</th>
                        <x-table.sort field="total" :sort="$sortField" :dir="$sortDirection" align="right">{{ __('Total') }}</x-table.sort>
                        <th>{{ __('Status') }}</th>
                        <th class="w-56"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($quotations as $quotation)
                        <tr wire:key="quotation-{{ $quotation->id }}">
                            <td class="font-mono text-xs font-medium">{{ $quotation->quotation_no }}</td>
                            <td class="whitespace-nowrap">{{ shop_digits($quotation->quotation_date->format('d M Y')) }}
                                @if ($quotation->valid_until)<div class="text-xs text-muted-foreground">{{ __('Valid until') }} {{ shop_digits($quotation->valid_until->format('d M Y')) }}</div>@endif
                            </td>
                            <td>{{ $quotation->customerLabel() ?? '—' }}@if ($quotation->customer_phone)<div class="text-xs text-muted-foreground-1">{{ $quotation->customer_phone }}</div>@endif</td>
                            <td class="text-right tabular-nums">{{ shop_digits($quotation->items_count) }}</td>
                            <td class="text-right font-medium tabular-nums">{{ money($quotation->total) }}</td>
                            <td>
                                @if ($quotation->sale)
                                    <a href="{{ route('sales.show', $quotation->sale) }}" wire:navigate><x-badge tone="green">{{ __('Sold') }} · {{ $quotation->sale->invoice_no }}</x-badge></a>
                                @elseif ($quotation->isExpired())
                                    <x-badge tone="slate">{{ __('Expired') }}</x-badge>
                                @else
                                    <x-badge tone="sky">{{ __('Open') }}</x-badge>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-right">
                                <a href="{{ route('quotations.print', $quotation) }}" target="_blank" class="btn-ghost btn-sm" title="{{ __('Print') }}"><x-icon name="printer" class="size-4" /></a>
                                @unless ($quotation->sale_id)
                                    <a href="{{ route('quotations.edit', $quotation) }}" wire:navigate class="btn-ghost btn-sm" title="{{ __('Edit') }}"><x-icon name="pencil" class="size-4" /></a>
                                    <a href="{{ route('pos', ['quotation' => $quotation->id]) }}" class="btn-secondary btn-sm">{{ __('Sell in POS') }}</a>
                                    <button type="button" wire:click="delete({{ $quotation->id }})" data-confirm="{{ __('Delete this quotation?') }}" data-confirm-button="{{ __('Delete') }}" class="btn-ghost btn-sm text-red-600" title="{{ __('Delete') }}"><x-icon name="close" class="size-4" /></button>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <x-empty :colspan="7" icon="document" :title="__('No quotations yet.')">
                            <a href="{{ route('quotations.create') }}" wire:navigate class="btn-primary"><x-icon name="plus" class="size-4" /> {{ __('New quotation') }}</a>
                        </x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-table.footer :paginator="$quotations" />
    </div>
</div>
