<form wire:submit="save" class="space-y-4">
    <x-page-header class="mb-0" :title="__('New purchase')" :subtitle="__('Scan products from the supplier invoice, check costs, then save.')" :back="route('purchases.index')" :backLabel="__('Purchases')">
        <x-branch-badge :branch="app(\App\Services\CurrentBranch::class)->get()" />
    </x-page-header>
    <div class="card grid gap-3 p-5 md:grid-cols-4">
        <div>
            <label class="form-label">{{ __('Supplier') }}</label>
            <select wire:model="supplier_id" class="form-input">
                <option value="">{{ __('No supplier / local market') }}</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}">{{ $supplier->name }}@if ($supplier->company) ({{ $supplier->company }})@endif</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label">{{ __('Supplier invoice no') }}</label>
            <input wire:model="supplier_invoice_no" type="text" class="form-input">
        </div>
        <div>
            <label class="form-label">{{ __('Date') }}</label>
            <input wire:model="purchase_date" type="date" class="form-input">
            @error('purchase_date') <p class="form-error">{{ $message }}</p> @enderror
        </div>
        <div class="flex items-end">
            @can('products.manage')
                <button type="button" wire:click="$dispatch('open-quick-product', { term: '', withStock: false })" class="btn-secondary w-full">+ {{ __('New product') }}</button>
            @endcan
        </div>
    </div>

    <div class="card space-y-3 p-5">
        <x-product-search :results="$results" :message="$scanMessage" :term="$productSearch" :missing="$missingProductTerm" quick-create :quick-create-stock="false" />
        @error('items') <p class="form-error">{{ $message }}</p> @enderror

        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Product') }}</th>
                        <th class="w-32">{{ __('Unit') }}</th>
                        <th class="w-28">{{ __('Quantity') }}</th>
                        <th class="w-32">{{ __('Cost per unit') }}</th>
                        <th class="w-36">{{ __('New selling price') }}</th>
                        <th class="w-32 text-right">{{ __('Total') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $index => $item)
                        @php $measure = $item['measure'] ?? null; @endphp
                        <tr wire:key="item-{{ $index }}-{{ $item['product_id'] }}">
                            <td class="font-medium">
                                <span class="inline-flex items-center gap-1.5">{{ $item['name'] }} <x-product-info-button :product-id="$item['product_id']" /></span>
                                @if ($measure && (float) $item['pieces'] > 0)
                                    <div class="mt-0.5 text-xs font-medium text-primary-700 dark:text-primary-400">{{ $this->packBreakdown($item, (float) $item['pieces']) }} · {{ shop_digits(round((float) $item['pieces'] * $measure['per'], 3) + 0) }} {{ $measure['unit'] }}</div>
                                @endif
                            </td>
                            <td>
                                @if ($measure)
                                    <div class="text-sm font-semibold text-foreground">{{ $measure['unit'] }}</div>
                                    <label class="mt-1 flex items-center gap-1 text-xs text-muted-foreground-1">
                                        <input wire:model.live.debounce.500ms="items.{{ $index }}.pieces" type="number" step="1" min="0" class="form-input w-20 py-0.5 text-center text-xs" title="{{ __('Pieces') }}">
                                        {{ $item['units'][$item['product_unit_id']] ?? __('pcs') }}
                                    </label>
                                @else
                                <select wire:model.live="items.{{ $index }}.product_unit_id" class="form-input">
                                    @foreach ($item['units'] as $unitId => $unitName)
                                        <option value="{{ $unitId }}">{{ $unitName }}@if (($item['unit_quantities'][$unitId] ?? 1) > 1) ({{ $item['unit_quantities'][$unitId] + 0 }})@endif</option>
                                    @endforeach
                                </select>
                                @endif
                            </td>
                            <td>
                                <input wire:model.live.debounce.600ms="items.{{ $index }}.quantity" type="number" step="any" min="0" class="form-input">
                                @if ($measure)<div class="mt-0.5 text-[11px] text-muted-foreground">{{ $measure['unit'] }}</div>@endif
                                @error("items.$index.quantity") <p class="form-error">{{ $message }}</p> @enderror
                            </td>
                            <td>
                                <input wire:model.live.debounce.400ms="items.{{ $index }}.unit_cost" type="number" step="0.01" min="0" class="form-input">
                                @if ($measure)<div class="mt-0.5 text-[11px] text-muted-foreground">/ {{ $measure['unit'] }}</div>@endif
                                @error("items.$index.unit_cost") <p class="form-error">{{ $message }}</p> @enderror
                            </td>
                            <td>
                                <input wire:model="items.{{ $index }}.new_selling_price" type="number" step="0.01" min="0" placeholder="{{ $item['selling_price'] }}" class="form-input">
                                @if ($measure)<div class="mt-0.5 text-[11px] text-muted-foreground">/ {{ $measure['unit'] }}</div>@endif
                            </td>
                            <td class="text-right tabular-nums">{{ money($this->lineTotal($item)) }}</td>
                            <td class="text-right"><button type="button" wire:click="removeItem({{ $index }})" data-confirm="{{ __('Remove this product from the purchase?') }}" class="btn-secondary btn-sm">✕</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-8 text-center text-muted-foreground-1">{{ __('Scan products from the supplier invoice.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div class="card space-y-3 p-5">
            <div>
                <label class="form-label">{{ __('Note') }}</label>
                <textarea wire:model="note" rows="3" class="form-input"></textarea>
            </div>
        </div>
        <div class="card space-y-3 p-5">
            <div class="flex justify-between text-sm"><span>{{ __('Subtotal') }}</span><span class="tabular-nums">{{ money($this->subtotal()) }}</span></div>
            <div class="flex items-center justify-between gap-3 text-sm">
                <span>{{ __('Discount') }}</span>
                <input wire:model.live.debounce.400ms="discount" type="number" step="0.01" min="0" class="form-input w-36 text-right">
            </div>
            <div class="flex justify-between border-t border-card-line pt-3 text-lg font-semibold"><span>{{ __('Total paid') }}</span><span class="tabular-nums">{{ money($this->total()) }}</span></div>
            <div class="grid grid-cols-2 gap-2">
                <select wire:model="payment_method_id" class="form-input">
                    @foreach ($paymentMethods as $method)
                        <option value="{{ $method->id }}">{{ $method->localName() }}</option>
                    @endforeach
                </select>
                <input wire:model="transaction_ref" type="text" placeholder="{{ __('Transaction ID (optional)') }}" class="form-input">
            </div>
            <label class="flex items-center gap-2 text-sm text-muted-foreground-2">
                <input wire:model="paid_from_drawer" type="checkbox" class="rounded border-layer-line"> {{ __('Cash taken from the cash drawer') }}
            </label>
            <button type="submit" class="btn-primary w-full h-10" wire:loading.attr="disabled">{{ __('Save purchase & add stock') }}</button>
        </div>
    </div>
</form>
