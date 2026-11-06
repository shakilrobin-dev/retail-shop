<div class="mx-auto max-w-5xl space-y-4">
    <x-flash />
    @error('stock') <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p> @enderror

    <div class="card grid gap-4 p-5 text-sm md:grid-cols-4">
        <div><div class="text-slate-500">{{ __('Purchase no') }}</div><div class="font-mono font-medium">{{ $purchase->reference_no }}</div></div>
        <div><div class="text-slate-500">{{ __('Date') }}</div><div>{{ shop_digits($purchase->purchase_date->format('d M Y')) }}</div></div>
        <div><div class="text-slate-500">{{ __('Supplier') }}</div><div>{{ $purchase->supplier?->name ?? '—' }} @if ($purchase->supplier_invoice_no)<span class="text-slate-400">· {{ $purchase->supplier_invoice_no }}</span>@endif</div></div>
        <div><div class="text-slate-500">{{ __('Entered by') }}</div><div>{{ $purchase->user?->name }}</div></div>
    </div>

    <div class="flex flex-wrap gap-2">
        <a href="{{ route('purchases.index') }}" class="btn-secondary" wire:navigate>← {{ __('Purchases') }}</a>
        <a href="{{ route('labels.index', ['purchase' => $purchase->id]) }}" class="btn-secondary" wire:navigate>{{ __('Print barcode labels') }}</a>
        <button type="button" wire:click="$toggle('showReturn')" class="btn-secondary">{{ __('Return to supplier') }}</button>
    </div>

    <form wire:submit="saveReturn" class="card overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('Product') }}</th>
                    <th class="text-right">{{ __('Quantity') }}</th>
                    <th class="text-right">{{ __('Cost per unit') }}</th>
                    <th class="text-right">{{ __('Total') }}</th>
                    <th class="text-right">{{ __('Returned') }}</th>
                    @if ($showReturn)<th class="w-40">{{ __('Return now (pieces)') }}</th>@endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($purchase->items as $item)
                    <tr wire:key="pitem-{{ $item->id }}">
                        <td>{{ $item->product->name }}</td>
                        <td class="text-right">{{ shop_digits($item->quantity + 0) }} {{ $item->productUnit->name }}@if ($item->unit_quantity > 1)<div class="text-xs text-slate-400">= {{ shop_digits($item->base_quantity + 0) }} {{ __('pcs') }}</div>@endif</td>
                        <td class="text-right tabular-nums">{{ money($item->unit_cost) }}</td>
                        <td class="text-right tabular-nums">{{ money($item->line_total) }}</td>
                        <td class="text-right">{{ $item->returned_base_quantity > 0 ? shop_digits($item->returned_base_quantity + 0) : '—' }}</td>
                        @if ($showReturn)
                            <td>
                                <input wire:model="returns.{{ $item->id }}" type="number" step="any" min="0" max="{{ $item->base_quantity - $item->returned_base_quantity }}" class="form-input">
                                @error("returns.$item->id") <p class="form-error">{{ $message }}</p> @enderror
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="text-sm">
                <tr><td colspan="3" class="text-right">{{ __('Subtotal') }}</td><td class="text-right tabular-nums">{{ money($purchase->subtotal) }}</td><td colspan="2"></td></tr>
                @if ($purchase->discount > 0)
                    <tr><td colspan="3" class="text-right">{{ __('Discount') }}</td><td class="text-right tabular-nums">− {{ money($purchase->discount) }}</td><td colspan="2"></td></tr>
                @endif
                <tr class="font-semibold"><td colspan="3" class="text-right">{{ __('Total paid') }}</td><td class="text-right tabular-nums">{{ money($purchase->total) }}</td><td colspan="2"></td></tr>
            </tfoot>
        </table>

        @if ($showReturn)
            <div class="grid gap-3 border-t border-slate-200 p-5 md:grid-cols-4">
                @error('returns') <p class="form-error md:col-span-4">{{ $message }}</p> @enderror
                <div>
                    <label class="form-label">{{ __('Money received by') }}</label>
                    <select wire:model="refund_method_id" class="form-input">
                        @foreach ($paymentMethods as $method)
                            <option value="{{ $method->id }}">{{ $method->localName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="form-label">{{ __('Reason / note') }}</label>
                    <input wire:model="return_note" type="text" class="form-input">
                </div>
                <div class="flex flex-col justify-end gap-2">
                    <label class="flex items-center gap-2 text-xs"><input wire:model="into_drawer" type="checkbox" class="rounded border-slate-300"> {{ __('Cash put in drawer') }}</label>
                    <button type="submit" class="btn-danger">{{ __('Save return') }}</button>
                </div>
            </div>
        @endif
    </form>

    <div class="grid gap-4 md:grid-cols-2">
        <div class="card p-5 text-sm">
            <h3 class="mb-2 font-semibold">{{ __('Payments') }}</h3>
            @foreach ($purchase->payments as $payment)
                <div class="flex justify-between py-1"><span>{{ $payment->paymentMethod->localName() }} @if ($payment->transaction_ref)<span class="text-slate-400">({{ $payment->transaction_ref }})</span>@endif</span><span class="tabular-nums">{{ money($payment->amount) }}</span></div>
            @endforeach
            @if ($purchase->note)<p class="mt-3 text-slate-600">{{ $purchase->note }}</p>@endif
        </div>
        @if ($purchase->returns->isNotEmpty())
            <div class="card p-5 text-sm">
                <h3 class="mb-2 font-semibold">{{ __('Returns') }}</h3>
                @foreach ($purchase->returns as $return)
                    <div class="flex justify-between py-1">
                        <span class="font-mono text-xs">{{ $return->reference_no }} · {{ shop_digits($return->created_at->format('d M Y')) }}</span>
                        <span class="tabular-nums">{{ money($return->total) }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
