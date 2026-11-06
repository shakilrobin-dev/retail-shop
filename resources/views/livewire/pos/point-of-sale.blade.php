<div x-data="{
        focusScan() { document.querySelector('[data-scan-input]')?.focus() },
        printUrl(url) {
            const old = document.getElementById('receipt-frame'); if (old) old.remove();
            const frame = document.createElement('iframe');
            frame.id = 'receipt-frame'; frame.style.position = 'fixed'; frame.style.width = '0'; frame.style.height = '0'; frame.style.border = '0';
            frame.src = url; document.body.appendChild(frame);
        }
    }"
    x-on:keydown.window.f2.prevent="focusScan()"
    x-on:keydown.window.f8.prevent="$wire.hold()"
    x-on:keydown.window.f10.prevent="$wire.showPayment ? $wire.complete() : $wire.openPayment()"
    x-on:keydown.window.escape="$wire.set('showPayment', false); focusScan()"
    x-on:print-receipt.window="printUrl($event.detail.url)"
    x-on:focus-payment.window="setTimeout(() => document.querySelector('[data-pay-input]')?.focus(), 50)">

    <x-flash />

    @if (! $shift)
        {{-- Register closed: ask for opening cash --}}
        <div class="mx-auto mt-10 max-w-md card p-6 text-center">
            <h2 class="text-lg font-semibold">{{ __('Cash register is closed') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('Count the cash in the drawer and open the register to start selling.') }}</p>
            @if ($lastShift?->counted_cash !== null)
                <p class="mt-2 text-xs text-slate-400">{{ __('Last closing cash') }}: {{ money($lastShift->counted_cash) }}</p>
            @endif
            @can('cash-shifts.manage')
                <form wire:submit="openRegister" class="mt-4 flex gap-2">
                    <input wire:model="openingCash" type="number" step="0.01" min="0" class="form-input text-right" placeholder="{{ __('Opening cash') }}" autofocus>
                    <button type="submit" class="btn-primary whitespace-nowrap">{{ __('Open register') }}</button>
                </form>
                @error('openingCash') <p class="form-error">{{ $message }}</p> @enderror
            @else
                <p class="mt-4 text-sm text-red-600">{{ __('Ask a manager to open the cash register.') }}</p>
            @endcan
        </div>
    @else
        <div class="grid gap-4 xl:grid-cols-3">
            {{-- Left: scan + cart --}}
            <div class="space-y-3 xl:col-span-2">
                <x-product-search :results="$results" :message="$scanMessage" />
                @error('cart') <p class="form-error text-sm">{{ $message }}</p> @enderror
                @error('stock') <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p> @enderror

                <div class="card overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>{{ __('Product') }}</th>
                                <th class="w-36">{{ __('Unit') }}</th>
                                <th class="w-36 text-center">{{ __('Qty') }}</th>
                                <th class="w-28">{{ __('Price') }}</th>
                                @can('pos.discount')<th class="w-24">{{ __('Disc.') }}</th>@endcan
                                <th class="w-28 text-right">{{ __('Total') }}</th>
                                <th class="w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($cart as $index => $line)
                                @php
                                    $unitQty = $line['units'][$line['product_unit_id']]['quantity'] ?? 1;
                                    $needed = (float) $line['quantity'] * $unitQty;
                                @endphp
                                <tr wire:key="cart-{{ $line['product_unit_id'] }}-{{ $index }}">
                                    <td>
                                        <div class="font-medium">{{ $line['name'] }}</div>
                                        <div @class(['text-xs', 'text-red-600' => $needed > $line['stock'], 'text-slate-400' => $needed <= $line['stock']])>
                                            {{ __('In stock') }}: {{ shop_digits($line['stock'] + 0) }}
                                        </div>
                                        @error("cart.$index") <p class="form-error">{{ $message }}</p> @enderror
                                    </td>
                                    <td>
                                        @if (count($line['units']) > 1)
                                            <select wire:model.live="cart.{{ $index }}.product_unit_id" class="form-input py-1">
                                                @foreach ($line['units'] as $unitId => $unit)
                                                    <option value="{{ $unitId }}">{{ $unit['name'] }}@if ($unit['quantity'] > 1) ({{ $unit['quantity'] + 0 }})@endif</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <span class="text-sm text-slate-600">{{ collect($line['units'])->first()['name'] }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="flex items-center justify-center gap-1">
                                            <button type="button" wire:click="decrement({{ $index }})" class="btn-secondary btn-sm">−</button>
                                            <input wire:model.live.debounce.400ms="cart.{{ $index }}.quantity" type="number" step="any" min="0" class="form-input w-16 px-1 py-1 text-center">
                                            <button type="button" wire:click="increment({{ $index }})" class="btn-secondary btn-sm">+</button>
                                        </div>
                                    </td>
                                    <td>
                                        @if ($canEditPrice)
                                            <input wire:model.live.debounce.500ms="cart.{{ $index }}.unit_price" type="number" step="0.01" min="0" class="form-input py-1">
                                        @else
                                            <span class="tabular-nums">{{ money($line['unit_price']) }}</span>
                                        @endif
                                    </td>
                                    @can('pos.discount')
                                        <td><input wire:model.live.debounce.500ms="cart.{{ $index }}.discount_amount" type="number" step="0.01" min="0" class="form-input py-1"></td>
                                    @endcan
                                    <td class="text-right font-medium tabular-nums">{{ money(max(0, (float) $line['quantity'] * (float) $line['unit_price'] - (float) $line['discount_amount'])) }}</td>
                                    <td><button type="button" wire:click="removeLine({{ $index }})" class="text-slate-400 hover:text-red-600" title="{{ __('Remove') }}">✕</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="py-16 text-center text-slate-400">{{ __('Scan a product to start the sale.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap gap-2 text-xs text-slate-500">
                    <span class="rounded bg-white px-2 py-1 ring-1 ring-slate-200">F2 {{ __('Search') }}</span>
                    <span class="rounded bg-white px-2 py-1 ring-1 ring-slate-200">F8 {{ __('Hold') }}</span>
                    <span class="rounded bg-white px-2 py-1 ring-1 ring-slate-200">F10 {{ __('Pay') }}</span>
                    <span class="rounded bg-white px-2 py-1 ring-1 ring-slate-200">Esc {{ __('Back') }}</span>
                </div>

                @if ($heldSales->isNotEmpty())
                    <div class="card p-4">
                        <h3 class="mb-2 text-sm font-semibold">{{ __('Sales on hold') }}</h3>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($heldSales as $held)
                                <button type="button" wire:click="resume({{ $held->id }})" wire:key="held-{{ $held->id }}" class="btn-secondary btn-sm">
                                    {{ $held->hold_note ?: ($held->customer?->name ?? __(':count items', ['count' => $held->items_count])) }}
                                    · {{ money($held->total) }} · {{ shop_digits($held->created_at->format('h:i A')) }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Right: customer, totals, payment --}}
            <div class="space-y-3">
                <div class="card p-4">
                    @if ($customer)
                        <div class="flex items-center justify-between text-sm">
                            <div><div class="font-medium">{{ $customer->name }}</div><div class="text-slate-500">{{ $customer->phone }}</div></div>
                            <button type="button" wire:click="$set('customerId', null)" class="text-xs text-slate-500 hover:underline">{{ __('Change') }}</button>
                        </div>
                    @else
                        <label class="form-label">{{ __('Customer (optional)') }}</label>
                        <input wire:model.live.debounce.300ms="customerSearch" type="search" placeholder="{{ __('Phone number') }}" class="form-input">
                        @foreach ($customerResults as $result)
                            <button type="button" wire:click="selectCustomer({{ $result->id }})" wire:key="cust-{{ $result->id }}" class="mt-1 block w-full rounded px-2 py-1 text-left text-sm hover:bg-slate-100">
                                {{ $result->name }} · {{ $result->phone }}
                            </button>
                        @endforeach
                        @if (mb_strlen($customerSearch) >= 6 && $customerResults->isEmpty())
                            <div class="mt-2 flex gap-2">
                                <input wire:model="newCustomerName" type="text" placeholder="{{ __('New customer name') }}" class="form-input">
                                <button type="button" wire:click="createCustomer" class="btn-secondary">{{ __('Add') }}</button>
                            </div>
                            @error('customerSearch') <p class="form-error">{{ $message }}</p> @enderror
                            @error('newCustomerName') <p class="form-error">{{ $message }}</p> @enderror
                        @endif
                    @endif
                </div>

                <div class="card space-y-2 p-4 text-sm">
                    <div class="flex justify-between"><span>{{ __('Subtotal') }}</span><span class="tabular-nums">{{ money($totals['subtotal']) }}</span></div>
                    @can('pos.discount')
                        <div class="flex items-center justify-between gap-2">
                            <span>{{ __('Discount') }}</span>
                            <div class="flex gap-1">
                                <select wire:model.live="discountType" class="form-input w-20 py-1">
                                    <option value="amount">{{ app(\App\Services\Settings::class)->get('currency_symbol') }}</option>
                                    <option value="percent">%</option>
                                </select>
                                <input wire:model.live.debounce.400ms="discountValue" type="number" step="0.01" min="0" class="form-input w-24 py-1 text-right">
                            </div>
                        </div>
                        @if ($totals['discount_amount'] > 0)
                            <div class="flex justify-between text-slate-500"><span></span><span class="tabular-nums">− {{ money($totals['discount_amount']) }}</span></div>
                        @endif
                        @error('discountValue') <p class="form-error">{{ $message }}</p> @enderror
                    @endcan
                    @if ($vatEnabled && $totals['vat_amount'] > 0)
                        <div class="flex justify-between"><span>{{ __('VAT') }}</span><span class="tabular-nums">{{ money($totals['vat_amount']) }}</span></div>
                    @endif
                    <div class="flex justify-between border-t border-slate-200 pt-2 text-2xl font-bold"><span>{{ __('Total') }}</span><span class="tabular-nums">{{ money($totals['total']) }}</span></div>
                </div>

                @if ($showPayment)
                    <div class="card space-y-3 p-4">
                        <h3 class="font-semibold">{{ __('Payment') }}</h3>
                        @foreach ($payments as $index => $payment)
                            <div wire:key="pay-{{ $index }}" class="space-y-1">
                                <div class="flex gap-1">
                                    <select wire:model.live="payments.{{ $index }}.payment_method_id" class="form-input w-28 shrink-0">
                                        @foreach ($paymentMethods as $method)
                                            <option value="{{ $method->id }}">{{ $method->localName() }}</option>
                                        @endforeach
                                    </select>
                                    <input wire:model.live.debounce.300ms="payments.{{ $index }}.amount" type="number" step="0.01" min="0"
                                        @if ($loop->first) data-pay-input @endif
                                        wire:keydown.enter.prevent="complete" class="form-input min-w-0 flex-1 text-right text-lg" placeholder="0.00">
                                    <button type="button" wire:click="payExact({{ $index }})" class="btn-secondary btn-sm shrink-0" title="{{ __('Exact amount') }}">=</button>
                                    @if (count($payments) > 1)
                                        <button type="button" wire:click="removePayment({{ $index }})" class="btn-secondary btn-sm">✕</button>
                                    @endif
                                </div>
                                @if ($paymentMethods->firstWhere('id', (int) $payment['payment_method_id'])?->type !== \App\Enums\PaymentMethodType::Cash)
                                    <input wire:model="payments.{{ $index }}.transaction_ref" type="text" placeholder="{{ __('Transaction ID / last 4 digits') }}" class="form-input py-1 text-xs">
                                @endif
                            </div>
                        @endforeach
                        <div class="flex flex-wrap gap-1">
                            @foreach ($paymentMethods as $method)
                                <button type="button" wire:click="addPayment({{ $method->id }})" class="btn-secondary btn-sm">+ {{ $method->localName() }}</button>
                            @endforeach
                        </div>
                        @error('payments') <p class="form-error text-sm">{{ $message }}</p> @enderror
                        <div class="space-y-1 border-t border-slate-200 pt-2 text-sm">
                            <div class="flex justify-between"><span>{{ __('Paid') }}</span><span class="tabular-nums">{{ money($paid) }}</span></div>
                            @if ($due > 0)
                                <div class="flex justify-between font-semibold text-red-600"><span>{{ __('Due') }}</span><span class="tabular-nums">{{ money($due) }}</span></div>
                            @endif
                            <div class="flex justify-between text-xl font-bold text-emerald-700"><span>{{ __('Change amount') }}</span><span class="tabular-nums">{{ money($change) }}</span></div>
                        </div>
                        <input wire:model="note" type="text" placeholder="{{ __('Note on receipt (optional)') }}" class="form-input text-sm">
                        <div class="flex gap-2">
                            <button type="button" wire:click="$set('showPayment', false)" class="btn-secondary">{{ __('Back') }}</button>
                            <button type="button" wire:click="complete" wire:loading.attr="disabled" @disabled($due > 0) class="btn-primary flex-1 py-3 text-base">
                                {{ __('Complete sale') }} (F10)
                            </button>
                        </div>
                    </div>
                @else
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" wire:click="hold" @disabled(! $cart) class="btn-secondary py-3">{{ __('Hold') }} (F8)</button>
                        <button type="button" wire:click="clearCart" wire:confirm="{{ __('Clear the cart?') }}" @disabled(! $cart) class="btn-secondary py-3">{{ __('Clear') }}</button>
                        <input wire:model="holdNote" type="text" placeholder="{{ __('Hold note (e.g. customer name)') }}" class="form-input col-span-2 text-sm">
                        <button type="button" wire:click="openPayment" @disabled(! $cart) class="btn-primary col-span-2 py-4 text-lg">{{ __('Pay') }} (F10)</button>
                    </div>
                    @if ($drawerEnabled)
                        @can('pos.open-drawer')
                            <a href="{{ route('pos.drawer') }}" target="_blank" class="block text-center text-xs text-slate-500 hover:underline">{{ __('Open cash drawer (no sale)') }}</a>
                        @endcan
                    @endif
                @endif
            </div>
        </div>
    @endif

    {{-- Sale completed --}}
    @if ($completedSale)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" x-init="$nextTick(() => $refs.newSale?.focus())">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-xl">
                <x-icon name="check" class="mx-auto size-12 text-emerald-600" />
                <h2 class="mt-2 text-lg font-semibold">{{ __('Sale completed') }}</h2>
                <p class="font-mono text-sm text-slate-500">{{ $completedSale->invoice_no }}</p>
                <div class="mt-4 rounded-lg bg-slate-50 p-3">
                    <div class="text-sm text-slate-500">{{ __('Change to return') }}</div>
                    <div class="text-3xl font-bold text-emerald-700">{{ money($completedSale->change_amount) }}</div>
                </div>
                <div class="mt-5 flex flex-col gap-2">
                    @if ($printMode !== 'off')
                        <button type="button" x-on:click="printUrl('{{ route('sales.receipt', $completedSale) }}')" class="btn-secondary py-2.5">
                            {{ $printMode === 'on' ? __('Print again') : __('Print receipt') }}
                        </button>
                    @endif
                    <button type="button" x-ref="newSale" wire:click="newSale" x-on:click="setTimeout(() => focusScan(), 50)" class="btn-primary py-3">{{ __('New sale') }} (Enter)</button>
                </div>
            </div>
        </div>
    @endif
</div>
