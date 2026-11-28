<form wire:submit="save" class="space-y-4">
    <x-page-header class="mb-0" :title="$quotation ? __('Edit quotation').' '.$quotation->quotation_no : __('New quotation')" :subtitle="__('Prices can be changed here. Nothing is taken from stock until it is sold at the POS.')" :back="route('quotations.index')" :backLabel="__('Quotations')">
        <x-branch-badge :branch="$branch" />
    </x-page-header>

    <div class="card grid gap-3 p-5 md:grid-cols-5">
        <div class="md:col-span-2">
            <label class="form-label">{{ __('Customer name') }}</label>
            <input wire:model="customerName" type="text" class="form-input">
        </div>
        <div>
            <label class="form-label">{{ __('Phone') }}</label>
            <input wire:model="customerPhone" type="text" class="form-input">
        </div>
        <div>
            <label class="form-label">{{ __('Date') }}</label>
            <input wire:model="quotationDate" type="date" class="form-input">
            @error('quotationDate') <p class="form-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="form-label">{{ __('Valid until') }}</label>
            <input wire:model="validUntil" type="date" class="form-input">
            @error('validUntil') <p class="form-error">{{ $message }}</p> @enderror
        </div>
        <div class="md:col-span-5">
            <label class="form-label">{{ __('Address / site') }}</label>
            <input wire:model="customerAddress" type="text" class="form-input">
        </div>
    </div>

    <div class="card space-y-3 p-5">
        <x-product-search :results="$results" :message="$scanMessage" :term="$productSearch" :missing="$missingProductTerm" measure-entry quick-create :quick-create-stock="false" />
        @error('cart') <p class="form-error">{{ $message }}</p> @enderror

        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    @php $allTicked = $cart !== [] && collect($cart)->every(fn ($line) => $line['bill_discount'] ?? true); @endphp
                    <tr>
                        <th class="w-8 px-2!">
                            <input type="checkbox" class="rounded border-layer-line" @checked($allTicked) @disabled($cart === [])
                                wire:click="setBillDiscountForAll({{ $allTicked ? 'false' : 'true' }})"
                                title="{{ __('Bill discount applies to ticked items. Tick or untick all.') }}" aria-label="{{ __('Bill discount on all items') }}">
                        </th>
                        <th>{{ __('Product') }}</th>
                        <th class="w-28">{{ __('Unit') }}</th>
                        <th class="w-32 text-center">{{ __('Qty') }}</th>
                        <th class="w-32">{{ __('Price') }}</th>
                        <th class="w-24" title="{{ __('Item discount in %. Several, one after another: 20,10,5') }}">{{ __('Disc. %') }}</th>
                        <th class="w-28 text-right">{{ __('Total') }}</th>
                        <th class="w-10"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($cart as $index => $line)
                        @php
                            $measure = $line['measure'] ?? null;
                            $soldQty = $measure ? (float) $line['pieces'] : (float) $line['quantity'];
                            $billedMeasure = $measure ? round($soldQty * $measure['per'], 3) : 0;
                            $amounts = $this->lineAmounts($line);
                            $lineTotal = $amounts['total'];
                            $discountValid = \App\Services\DiscountChain::isValid($line['discount'] ?? '');
                        @endphp
                        <tr wire:key="qline-{{ $line['product_unit_id'] }}-{{ $index }}" @class(['bg-muted/40' => ! ($line['bill_discount'] ?? true)])>
                            <td class="px-2!">
                                <input type="checkbox" wire:model.live="cart.{{ $index }}.bill_discount" class="rounded border-layer-line"
                                    title="{{ __('Apply the bill discount to this item') }}" aria-label="{{ __('Apply the bill discount to :item', ['item' => $line['name']]) }}">
                            </td>
                            <td>
                                <div class="flex items-center gap-1.5 font-medium">{{ $line['name'] }} <x-product-info-button :product-id="$line['product_id']" /></div>
                                @error("cart.$index") <p class="form-error">{{ $message }}</p> @enderror
                            </td>
                            @if ($measure)
                                <x-cart.measured-quantity :index="$index" :line="$line" :pieces="$soldQty" :billed="$billedMeasure" :breakdown="$this->packBreakdown($line, $soldQty)" />
                            @else
                            <td>
                                @if (count($line['units']) > 1)
                                    <select wire:model.live="cart.{{ $index }}.product_unit_id" class="form-input py-1">
                                        @foreach ($line['units'] as $unitId => $unit)
                                            <option value="{{ $unitId }}">{{ $unit['name'] }}@if ($unit['quantity'] > 1) ({{ $unit['quantity'] + 0 }})@endif</option>
                                        @endforeach
                                    </select>
                                @else
                                    <span class="text-sm text-muted-foreground-2">{{ collect($line['units'])->first()['name'] }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex items-center justify-center gap-1">
                                    <button type="button" wire:click="decrement({{ $index }})" class="btn-secondary btn-sm">−</button>
                                    <input wire:model.live.debounce.600ms="cart.{{ $index }}.quantity" type="number" step="any" min="0" class="form-input w-14 px-1 py-1 text-center">
                                    <button type="button" wire:click="increment({{ $index }})" class="btn-secondary btn-sm">+</button>
                                </div>
                                @error("cart.$index.quantity") <p class="form-error">{{ $message }}</p> @enderror
                            </td>
                            @endif
                            <td>
                                <input wire:model.live.debounce.500ms="cart.{{ $index }}.unit_price" type="number" step="0.01" min="0" class="form-input min-w-20 py-1">
                                @if ($measure)<div class="mt-0.5 text-[11px] text-muted-foreground">{{ __('per :unit', ['unit' => $measure['unit']]) }}</div>@endif
                                @error("cart.$index.unit_price") <p class="form-error">{{ $message }}</p> @enderror
                            </td>
                            <td>
                                <input wire:model.live.debounce.600ms="cart.{{ $index }}.discount" type="text" inputmode="decimal" placeholder="%"
                                    @class(['form-input w-20 py-1 text-right', 'border-red-500 ring-1 ring-red-200' => ! $discountValid])
                                    title="{{ __('Item discount in %. Several, one after another: 20,10,5') }}" aria-label="{{ __('Discount % for :item', ['item' => $line['name']]) }}">
                                @if (! $discountValid)
                                    <div class="mt-0.5 text-[11px] text-red-600">{{ __('e.g. 20,10,5') }}</div>
                                @elseif ($amounts['discount'] > 0)
                                    <div class="mt-0.5 whitespace-nowrap text-[11px] text-emerald-700 dark:text-emerald-400">− {{ money($amounts['discount']) }}</div>
                                @endif
                                @error("cart.$index.discount") <p class="form-error">{{ $message }}</p> @enderror
                            </td>
                            <td class="text-right font-medium tabular-nums">
                                {{ money($lineTotal) }}
                                @if ($amounts['discount'] > 0)<div class="text-[11px] font-normal text-muted-foreground line-through">{{ money($amounts['gross']) }}</div>@endif
                                @if ($measure && $soldQty > 0)<div class="whitespace-nowrap text-[11px] font-normal text-muted-foreground">{{ shop_digits($billedMeasure + 0) }} {{ $measure['unit'] }} × {{ money($line['unit_price'] === '' ? 0 : $line['unit_price']) }}</div>@endif
                            </td>
                            <td><button type="button" wire:click="removeLine({{ $index }})" class="text-muted-foreground hover:text-red-600" title="{{ __('Remove') }}">✕</button></td>
                        </tr>
                    @empty
                        <x-empty :colspan="8" icon="document" :title="__('Search products to add them to the quotation.')" />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div class="card p-5">
            <label class="form-label">{{ __('Note / terms') }}</label>
            <textarea wire:model="note" rows="4" class="form-input" placeholder="{{ __('e.g. Delivery in 3 days. Prices may change after the validity date.') }}"></textarea>
        </div>
        <div class="card space-y-2 p-5 text-sm">
            <div class="flex justify-between"><span>{{ __('Subtotal') }}</span><span class="tabular-nums">{{ money($totals['subtotal']) }}</span></div>
            <div class="flex items-center justify-between gap-2">
                <span>{{ __('Discount') }}</span>
                <div class="flex gap-1">
                    <select wire:model.live="discountType" class="form-input w-20 py-1">
                        <option value="amount">{{ app(\App\Services\Settings::class)->get('currency_symbol') }}</option>
                        <option value="percent">%</option>
                    </select>
                    <input wire:model.live.debounce.400ms="discountValue" type="number" step="0.01" min="0" class="form-input w-28 py-1 text-right">
                </div>
            </div>
            @if ($totals['discount_amount'] > 0 || ($cart !== [] && $totals['discountable'] < $totals['subtotal']))
                <div class="flex justify-between gap-2 text-muted-foreground-1">
                    <span class="text-xs">@if ($totals['discountable'] < $totals['subtotal']){{ __('On ticked items (:amount)', ['amount' => money($totals['discountable'])]) }}@endif</span>
                    <span class="tabular-nums">− {{ money($totals['discount_amount']) }}</span>
                </div>
            @endif
            @if ($vatEnabled && $totals['vat_amount'] > 0)
                <div class="flex justify-between"><span>{{ __('VAT') }}</span><span class="tabular-nums">{{ money($totals['vat_amount']) }}</span></div>
            @endif
            @if ($totals['rounding_amount'] != 0)
                <div class="flex justify-between text-muted-foreground-1"><span>{{ __('Rounding') }}</span><span class="tabular-nums">{{ $totals['rounding_amount'] > 0 ? '+' : '−' }} {{ money(abs($totals['rounding_amount'])) }}</span></div>
            @endif
            <div class="flex justify-between border-t border-card-line pt-3 text-lg font-semibold"><span>{{ __('Total') }}</span><span class="tabular-nums">{{ money($totals['total']) }}</span></div>
            <button type="submit" class="btn-primary h-10 w-full" wire:loading.attr="disabled">{{ __('Save quotation') }}</button>
        </div>
    </div>
</form>
