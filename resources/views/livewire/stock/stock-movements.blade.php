<div class="space-y-5">
    <x-page-header class="mb-0" :title="__('Stock history')" :subtitle="__('Every stock change: purchases, sales, returns, repairs, adjustments and transfers.')" :back="route('stock.index')" :backLabel="__('Stock')" />
    <div class="card">
    <div class="flex flex-wrap items-center gap-2 border-b border-card-divider p-3">
        @if ($product)
            <span class="badge bg-primary-50 px-3 py-1.5 text-sm text-primary-700">{{ $product->localName() }}
                <button type="button" wire:click="$set('productId', null)" class="ml-1">✕</button></span>
        @endif
        <select wire:model.live="type" class="form-input w-44">
            <option value="">{{ __('All types') }}</option>
            @foreach (\App\Enums\StockMovementType::cases() as $case)
                <option value="{{ $case->value }}">{{ $case->label() }}</option>
            @endforeach
        </select>
        <input wire:model.live="from" type="date" class="form-input w-44">
        <input wire:model.live="to" type="date" class="form-input w-44">
    </div>

    <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>{{ __('Time') }}</th><th>{{ __('Product') }}</th><th>{{ __('Type') }}</th><th>{{ __('Reference') }}</th><th class="text-right">{{ __('Change') }}</th><th class="text-right">{{ __('Balance') }}</th><th>{{ __('By') }}</th></tr></thead>
            <tbody>
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
                        <td>{{ $movement->type->label() }}@if ($movement->note)<div class="text-xs text-muted-foreground">{{ $movement->note }}</div>@endif</td>
                        <td class="font-mono text-xs">@if ($refUrl)<a href="{{ $refUrl }}" class="text-primary-600 hover:underline" wire:navigate>{{ $refText }}</a>@else{{ $refText }}@endif</td>
                        <td @class(['text-right tabular-nums font-medium', 'text-emerald-700' => $movement->quantity > 0, 'text-red-700' => $movement->quantity < 0])>{{ $movement->quantity > 0 ? '+' : '' }}{{ shop_digits($movement->quantity + 0) }}</td>
                        <td class="text-right tabular-nums">{{ shop_digits($movement->balance_after + 0) }}</td>
                        <td class="text-muted-foreground-1">{{ $movement->user?->name }}</td>
                    </tr>
                @empty
                    <x-empty :colspan="7" icon="history" :title="__('No stock changes yet.')" />
                @endforelse
            </tbody>
        </table>
    </div>
    </div>
    {{ $movements->links() }}
</div>
