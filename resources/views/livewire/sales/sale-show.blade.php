<div class="mx-auto max-w-6xl space-y-5">
    <x-flash />
    <x-page-header class="mb-0" :title="__('Invoice').' '.$sale->invoice_no" :subtitle="shop_digits($sale->sold_at->format('d M Y h:i A'))" :back="route('sales.index')" :backLabel="__('Sales')">
        <x-badge :tone="$sale->status === \App\Enums\SaleStatus::Voided ? 'red' : 'green'" class="px-3 py-1 text-sm">{{ $sale->status->label() }}</x-badge>
    </x-page-header>

    @if ($sale->status === \App\Enums\SaleStatus::Voided)
        <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">
            {{ __('Voided by :user on :date. Reason: :reason', ['user' => $sale->voidedBy?->name, 'date' => shop_digits($sale->voided_at->format('d M Y h:i A')), 'reason' => $sale->void_reason]) }}
        </div>
    @endif

    <div class="card grid gap-4 p-5 text-sm md:grid-cols-4">
        <div><div class="text-muted-foreground-1">{{ __('Invoice') }}</div><div class="font-mono font-medium">{{ $sale->invoice_no }}</div></div>
        <div><div class="text-muted-foreground-1">{{ __('Date') }}</div><div>{{ shop_digits($sale->sold_at->format('d M Y h:i A')) }}</div></div>
        <div><div class="text-muted-foreground-1">{{ __('Customer') }}</div><div>
            @if ($sale->customer)
                <a href="{{ route('customers.show', $sale->customer) }}" class="text-primary-600 hover:underline" wire:navigate>{{ $sale->customer->name }}</a> · {{ $sale->customer->phone }}
            @else
                {{ __('Walk-in') }}
            @endif
        </div></div>
        <div><div class="text-muted-foreground-1">{{ __('Cashier') }}</div><div>{{ $sale->user?->name }}</div></div>
    </div>

    <div class="flex flex-wrap gap-2">
        <a href="{{ route('sales.receipt', $sale) }}" target="_blank" class="btn-secondary">{{ __('Print receipt') }}</a>
        <a href="{{ route('sales.receipt', ['sale' => $sale, 'paper' => 'a4']) }}" target="_blank" class="btn-secondary">{{ __('A4 invoice') }}</a>
        @if ($sale->status === \App\Enums\SaleStatus::Completed)
            @can('sales.return')
                <button type="button" wire:click="$toggle('showReturn')" class="btn-secondary">{{ __('Return / exchange') }}</button>
            @endcan
            @can('sales.void')
                @if ($sale->returns->isEmpty())
                    <button type="button" wire:click="$toggle('showVoid')" class="btn-secondary text-red-600">{{ __('Void sale') }}</button>
                @endif
            @endcan
        @endif
    </div>

    @if ($showVoid)
        <form wire:submit="void" class="card flex flex-row flex-wrap items-end gap-2 p-4">
            <div class="flex-1">
                <label class="form-label">{{ __('Why is this sale cancelled?') }}</label>
                <input wire:model="void_reason" type="text" class="form-input">
                @error('void_reason') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <button type="submit" data-confirm="{{ __('Void this sale? Stock will return and money will be refunded.') }}" data-confirm-button="{{ __('Void sale') }}" class="btn-danger">{{ __('Void sale') }}</button>
        </form>
    @endif

    <form wire:submit="saveReturn" class="card overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('Product') }}</th>
                    <th class="text-right">{{ __('Qty') }}</th>
                    <th class="text-right">{{ __('Price') }}</th>
                    <th class="text-right">{{ __('Total') }}</th>
                    <th class="text-right">{{ __('Returned') }}</th>
                    @if ($showReturn)<th class="w-32">{{ __('Return qty') }}</th><th>{{ __('Back to stock') }}</th>@endif
                </tr>
            </thead>
            <tbody>
                @foreach ($sale->items as $item)
                    <tr wire:key="sitem-{{ $item->id }}">
                        <td>{{ $item->product_name }}@if ($item->unit_quantity > 1) <span class="text-xs text-muted-foreground-1">({{ $item->unit_name }})</span>@endif
                            @if ($item->warranty_days)<div class="text-xs text-muted-foreground">{{ __('Warranty till :date', ['date' => shop_digits($sale->sold_at->copy()->addDays($item->warranty_days)->format('d M Y'))]) }}</div>@endif
                        </td>
                        <td class="text-right">{{ shop_digits($item->quantity + 0) }}</td>
                        <td class="text-right tabular-nums">{{ money($item->unit_price) }}@if ($item->discount_amount > 0)<div class="text-xs text-muted-foreground">− {{ money($item->discount_amount) }}</div>@endif</td>
                        <td class="text-right tabular-nums">{{ money($item->line_total) }}</td>
                        <td class="text-right">{{ $item->returned_quantity > 0 ? shop_digits($item->returned_quantity + 0) : '—' }}</td>
                        @if ($showReturn)
                            <td>
                                <input wire:model="returns.{{ $item->id }}.quantity" type="number" step="any" min="0" max="{{ $item->quantity - $item->returned_quantity }}" class="form-input">
                                @error("returns.$item->id.quantity") <p class="form-error">{{ $message }}</p> @enderror
                            </td>
                            <td><label class="flex items-center gap-1 text-xs"><input wire:model="returns.{{ $item->id }}.restock" type="checkbox" class="rounded border-layer-line"> {{ __('Good condition') }}</label></td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="text-sm">
                <tr><td colspan="3" class="text-right">{{ __('Subtotal') }}</td><td class="text-right tabular-nums">{{ money($sale->subtotal) }}</td><td colspan="3"></td></tr>
                @if ($sale->discount_amount > 0)<tr><td colspan="3" class="text-right">{{ __('Discount') }}</td><td class="text-right tabular-nums">− {{ money($sale->discount_amount) }}</td><td colspan="3"></td></tr>@endif
                @if ($sale->vat_amount > 0)<tr><td colspan="3" class="text-right">{{ __('VAT') }}</td><td class="text-right tabular-nums">{{ money($sale->vat_amount) }}</td><td colspan="3"></td></tr>@endif
                <tr class="font-semibold"><td colspan="3" class="text-right">{{ __('Total') }}</td><td class="text-right tabular-nums">{{ money($sale->total) }}</td><td colspan="3"></td></tr>
            </tfoot>
        </table>

        @if ($showReturn)
            <div class="grid gap-3 border-t border-card-line p-5 md:grid-cols-4">
                @error('returns') <p class="form-error md:col-span-4">{{ $message }}</p> @enderror
                <div>
                    <label class="form-label">{{ __('Refund by') }}</label>
                    <select wire:model="refund_method_id" class="form-input">
                        @foreach ($paymentMethods as $method)
                            <option value="{{ $method->id }}">{{ $method->localName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="form-label">{{ __('Reason') }}</label>
                    <input wire:model="return_reason" type="text" class="form-input">
                </div>
                <div class="flex items-end"><button type="submit" class="btn-danger w-full">{{ __('Save return & refund') }}</button></div>
                <p class="text-xs text-muted-foreground-1 md:col-span-4">{{ __('For an exchange: save the return here, then sell the new item in POS.') }}</p>
            </div>
        @endif
    </form>

    <div class="grid gap-4 md:grid-cols-2">
        <div class="card p-5 text-sm">
            <h3 class="mb-2 font-semibold">{{ __('Payments') }}</h3>
            @foreach ($sale->payments as $payment)
                <div class="flex justify-between py-1">
                    <span>{{ $payment->direction->label() }} · {{ $payment->paymentMethod->localName() }} @if ($payment->transaction_ref)<span class="text-muted-foreground">({{ $payment->transaction_ref }})</span>@endif</span>
                    <span @class(['tabular-nums', 'text-red-700' => $payment->direction === \App\Enums\PaymentDirection::Out])>{{ money($payment->amount) }}</span>
                </div>
            @endforeach
            @if ($sale->change_amount > 0)<div class="flex justify-between py-1 text-muted-foreground-1"><span>{{ __('Change given') }}</span><span class="tabular-nums">{{ money($sale->change_amount) }}</span></div>@endif
            @if ($sale->note)<p class="mt-3 text-muted-foreground-2">{{ $sale->note }}</p>@endif
        </div>
        @if ($sale->returns->isNotEmpty())
            <div class="card p-5 text-sm">
                <h3 class="mb-2 font-semibold">{{ __('Returns') }}</h3>
                @foreach ($sale->returns as $return)
                    <div class="border-b border-card-divider py-2 last:border-0">
                        <div class="flex justify-between"><span class="font-mono text-xs">{{ $return->reference_no }} · {{ shop_digits($return->created_at->format('d M Y')) }} · {{ $return->user?->name }}</span><span class="tabular-nums">{{ money($return->total) }}</span></div>
                        @foreach ($return->items as $returnItem)
                            <div class="text-xs text-muted-foreground-1">{{ $returnItem->saleItem->product_name }} × {{ shop_digits($returnItem->quantity + 0) }} @unless ($returnItem->restock)({{ __('damaged') }})@endunless</div>
                        @endforeach
                        @if ($return->reason)<div class="text-xs text-muted-foreground-1">{{ $return->reason }}</div>@endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
