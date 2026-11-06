<div class="space-y-4">
    <div class="flex flex-wrap items-end gap-2">
        <a href="{{ route('stock.index') }}" class="btn-secondary" wire:navigate>← {{ __('Stock') }}</a>
        @if ($product)
            <span class="badge bg-indigo-50 px-3 py-1.5 text-sm text-indigo-700">{{ $product->localName() }}
                <button type="button" wire:click="$set('productId', null)" class="ml-1">✕</button></span>
        @endif
        <select wire:model.live="type" class="form-input w-auto">
            <option value="">{{ __('All types') }}</option>
            @foreach (\App\Enums\StockMovementType::cases() as $case)
                <option value="{{ $case->value }}">{{ $case->label() }}</option>
            @endforeach
        </select>
        <input wire:model.live="from" type="date" class="form-input w-auto">
        <input wire:model.live="to" type="date" class="form-input w-auto">
    </div>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>{{ __('Time') }}</th><th>{{ __('Product') }}</th><th>{{ __('Type') }}</th><th>{{ __('Reference') }}</th><th class="text-right">{{ __('Change') }}</th><th class="text-right">{{ __('Balance') }}</th><th>{{ __('By') }}</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($movements as $movement)
                    @php
                        $ref = $movement->reference;
                        $refText = $ref?->invoice_no ?? $ref?->reference_no ?? $ref?->job_no;
                        $refUrl = match ($movement->reference_type) {
                            'sale' => route('sales.show', $movement->reference_id),
                            'purchase' => route('purchases.show', $movement->reference_id),
                            'repair_job' => route('repairs.show', $movement->reference_id),
                            'stock_transfer' => route('stock.transfers.show', $movement->reference_id),
                            'sale_return' => $ref ? route('sales.show', $ref->sale_id) : null,
                            'purchase_return' => $ref ? route('purchases.show', $ref->purchase_id) : null,
                            default => null,
                        };
                    @endphp
                    <tr wire:key="mv-{{ $movement->id }}">
                        <td class="whitespace-nowrap text-xs">{{ shop_digits($movement->created_at->format('d M Y h:i A')) }}</td>
                        <td>{{ $movement->product?->localName() }}</td>
                        <td>{{ $movement->type->label() }}@if ($movement->note)<div class="text-xs text-slate-400">{{ $movement->note }}</div>@endif</td>
                        <td class="font-mono text-xs">@if ($refUrl)<a href="{{ $refUrl }}" class="text-indigo-600 hover:underline" wire:navigate>{{ $refText }}</a>@else{{ $refText }}@endif</td>
                        <td @class(['text-right tabular-nums font-medium', 'text-emerald-700' => $movement->quantity > 0, 'text-red-700' => $movement->quantity < 0])>{{ $movement->quantity > 0 ? '+' : '' }}{{ shop_digits($movement->quantity + 0) }}</td>
                        <td class="text-right tabular-nums">{{ shop_digits($movement->balance_after + 0) }}</td>
                        <td class="text-slate-500">{{ $movement->user?->name }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-slate-500">{{ __('No stock changes yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $movements->links() }}
</div>
