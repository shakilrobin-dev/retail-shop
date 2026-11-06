<div class="mx-auto max-w-5xl space-y-5">
    <x-flash />
    <x-page-header class="mb-0" :title="__('Transfer').' '.$transfer->reference_no" :back="route('stock.transfers.index')" :backLabel="__('Transfers')" />
    @error('stock') <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p> @enderror

    <div class="card grid gap-4 p-5 text-sm md:grid-cols-4">
        <div><div class="text-slate-500">{{ __('No') }}</div><div class="font-mono font-medium">{{ $transfer->reference_no }}</div></div>
        <div><div class="text-slate-500">{{ __('From') }}</div><div>{{ $transfer->fromBranch->localName() }}</div><div class="text-xs text-slate-400">{{ $transfer->sender?->name }} · {{ shop_digits($transfer->sent_at?->format('d M h:i A')) }}</div></div>
        <div><div class="text-slate-500">{{ __('To') }}</div><div>{{ $transfer->toBranch->localName() }}</div>@if ($transfer->received_at)<div class="text-xs text-slate-400">{{ $transfer->receiver?->name }} · {{ shop_digits($transfer->received_at->format('d M h:i A')) }}</div>@endif</div>
        <div><div class="text-slate-500">{{ __('Status') }}</div><div class="font-medium">{{ $transfer->status->label() }}</div></div>
        @if ($transfer->note)<div class="text-slate-600 md:col-span-4">{{ $transfer->note }}</div>@endif
    </div>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>{{ __('Product') }}</th><th class="text-right">{{ __('Sent') }}</th><th class="{{ $canReceive ? 'w-40' : 'text-right' }}">{{ __('Received') }}</th></tr></thead>
            <tbody>
                @foreach ($transfer->items as $item)
                    <tr wire:key="ti-{{ $item->id }}">
                        <td>{{ $item->product->name }}</td>
                        <td class="text-right">{{ shop_digits($item->quantity_sent + 0) }}</td>
                        <td @class(['text-right' => ! $canReceive])>
                            @if ($canReceive)
                                <input wire:model="received.{{ $item->id }}" type="number" step="any" min="0" class="form-input">
                                @error("received.$item->id") <p class="form-error">{{ $message }}</p> @enderror
                            @else
                                <span @class(['text-red-600 font-medium' => $item->quantity_received !== null && $item->quantity_received < $item->quantity_sent])>{{ $item->quantity_received !== null ? shop_digits($item->quantity_received + 0) : '—' }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="flex justify-end gap-2">
        @if ($canCancel)
            <button type="button" wire:click="cancel" wire:confirm="{{ __('Cancel this transfer and return stock?') }}" class="btn-secondary text-red-600">{{ __('Cancel transfer') }}</button>
        @endif
        @if ($canReceive)
            <button type="button" wire:click="receive" class="btn-primary">{{ __('Confirm received') }}</button>
        @endif
    </div>
</div>
