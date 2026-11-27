<div x-data="{
        showProfit: false,
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
    x-on:keydown.window.f9.prevent="togglePosFull()"
    x-on:keydown.window.f10.prevent="$wire.showPayment ? $wire.complete() : $wire.openPayment()"
    x-on:keydown.window.escape="$wire.set('showPayment', false); focusScan()"
    x-on:print-receipt.window="printUrl($event.detail.url)"
    x-on:focus-payment.window="setTimeout(() => document.querySelector('[data-pay-input]')?.focus(), 50)">

    <x-flash />

    @if (! $shift)
        {{-- Register closed: ask for opening cash --}}
        <div x-show="full" x-cloak class="mb-2 flex justify-end">
            <button type="button" x-on:click="togglePosFull()" class="btn-secondary btn-sm"><x-icon name="minimize" class="size-3.5" /> {{ __('Exit full screen') }}</button>
        </div>
        <div class="mx-auto mt-10 max-w-md card p-6 text-center">
            <h2 class="text-lg font-semibold">{{ __('Cash register is closed') }}</h2>
            <p class="mt-1 text-sm text-muted-foreground-1">{{ __('Count the cash in the drawer and open the register to start selling.') }}</p>
            @if ($lastShift?->counted_cash !== null)
                <p class="mt-2 text-xs text-muted-foreground">{{ __('Last closing cash') }}: {{ money($lastShift->counted_cash) }}</p>
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
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">
            <span class="flex flex-wrap items-center gap-2"><x-branch-badge :branch="$branch" class="bg-white! py-0.5! dark:bg-transparent!" /><span class="size-2 rounded-full bg-emerald-500"></span> {{ __('Register is open') }} · {{ __('Open since') }} {{ shop_digits($shift->opened_at->format('h:i A')) }} · {{ $shift->user?->name }}</span>
            <span class="flex items-center gap-3">
                @can('cash-shifts.manage')<a href="{{ route('cash-register') }}" wire:navigate class="font-medium hover:underline">{{ __('Cash register') }} →</a>@endcan
                <button type="button" x-show="full" x-cloak x-on:click="togglePosFull()" class="inline-flex items-center gap-1 rounded-md border border-emerald-300 bg-white/60 px-2 py-0.5 text-xs font-medium hover:bg-white dark:bg-transparent">
                    <x-icon name="minimize" class="size-3.5" /> {{ __('Exit full screen') }} (F9)
                </button>
            </span>
        </div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
            {{-- Left: scan + cart --}}
            <div class="space-y-3 xl:col-span-2">
                @if ($loadedQuotation)
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-800 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-300">
                        <span><x-icon name="document" class="inline size-4" /> {{ __('Selling quotation :no', ['no' => $loadedQuotation->quotation_no]) }}@if ($loadedQuotation->customerLabel()) · {{ $loadedQuotation->customerLabel() }}@endif</span>
                        <button type="button" wire:click="clearCart" data-confirm="{{ __('Clear the cart?') }}" class="text-xs font-medium hover:underline">{{ __('Cancel') }}</button>
                    </div>
                @endif
                <x-product-search :results="$results" :message="$scanMessage" :term="$productSearch" :missing="$missingProductTerm" quick-create />
                @error('cart') <p class="form-error text-sm">{{ $message }}</p> @enderror

                @php
                    $costTotal = 0;
                    foreach ($cart as $costLine) {
                        $costTotal += (float) (empty($costLine['measure']) ? $costLine['quantity'] : $costLine['pieces']) * ($unitCosts[$costLine['product_unit_id']] ?? 0);
                    }
                    $profitTotal = round($totals['subtotal'] - $totals['discount_amount'] + $totals['rounding_amount'] - $costTotal, 2);
                @endphp

                <div class="card min-h-64 overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>{{ __('Product') }}</th>
                                <th class="w-28">{{ __('Unit') }}</th>
                                <th class="w-28 text-center">{{ __('Qty') }}</th>
                                <th class="w-28">{{ __('Price') }}</th>
                                @can('pos.discount')<th class="w-24">{{ __('Disc.') }}</th>@endcan
                                <th class="w-24 whitespace-nowrap text-right">{{ __('Total') }}</th>
                                @if ($canViewCost)
                                    <th x-show="showProfit" x-cloak class="no-print whitespace-nowrap bg-amber-50 px-2! text-right dark:bg-amber-950/30">{{ __('Profit') }}<div class="text-[10px] font-normal normal-case">{{ __('Buying price') }}</div></th>
                                @endif
                                <th class="w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($cart as $index => $line)
                                @php
                                    $measure = $line['measure'] ?? null;
                                    $unitQty = $line['units'][$line['product_unit_id']]['quantity'] ?? 1;
                                    $soldQty = $measure ? (float) $line['pieces'] : (float) $line['quantity'];
                                    $needed = $soldQty * $unitQty;
                                    $billedMeasure = $measure ? round($soldQty * $measure['per'], 3) : 0;
                                    $gross = $measure ? round($billedMeasure * (float) $line['unit_price'], 2) : (float) $line['quantity'] * (float) $line['unit_price'];
                                    $lineTotal = max(0, $gross - (float) $line['discount_amount']);
                                    $unitCost = $unitCosts[$line['product_unit_id']] ?? 0;
                                    $lineCost = $soldQty * $unitCost;
                                    $lineProfit = $lineTotal - $lineCost;
                                @endphp
                                <tr wire:key="cart-{{ $line['product_unit_id'] }}-{{ $index }}">
                                    <td>
                                        <div class="flex items-center gap-1.5 font-medium">{{ $line['name'] }} <x-product-info-button :product-id="$line['product_id']" /></div>
                                        <div @class(['text-xs', 'text-red-600' => $needed > $line['stock'], 'text-muted-foreground' => $needed <= $line['stock']])>
                                            {{ __('In stock') }}: {{ $measure ? $this->packBreakdown($line, (float) $line['stock']) : shop_digits($line['stock'] + 0) }}
                                        </div>
                                        @if ($measure && $soldQty > 0)
                                            <div class="mt-0.5 text-xs font-medium text-primary-700 dark:text-primary-400">
                                                {{ __('Give') }}: {{ $this->packBreakdown($line, $soldQty) }} · {{ __('Billed') }} {{ shop_digits($billedMeasure + 0) }} {{ $measure['unit'] }}
                                            </div>
                                        @endif
                                        @error("cart.$index") <p class="form-error">{{ $message }}</p> @enderror
                                    </td>
                                    <td>
                                        @if ($measure)
                                            <label class="flex items-center gap-1 text-xs text-muted-foreground-1">
                                                <input wire:model.live.debounce.500ms="cart.{{ $index }}.pieces" type="number" step="1" min="0" class="form-input w-16 px-1 py-1 text-center" title="{{ __('Pieces') }}">
                                                {{ $line['units'][$line['product_unit_id']]['name'] ?? __('pcs') }}
                                            </label>
                                        @elseif (count($line['units']) > 1)
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
                                            <input wire:model.live.debounce.600ms="cart.{{ $index }}.quantity" type="number" step="any" min="0" @class(['form-input px-1 py-1 text-center', 'w-14' => ! $measure, 'w-20' => $measure]) @if ($measure) title="{{ $measure['unit'] }}" @endif>
                                            <button type="button" wire:click="increment({{ $index }})" class="btn-secondary btn-sm">+</button>
                                        </div>
                                        @if ($measure)<div class="mt-0.5 text-center text-[11px] text-muted-foreground">{{ $measure['unit'] }}</div>@endif
                                    </td>
                                    <td>
                                        @if ($canEditPrice)
                                            <input wire:model.live.debounce.500ms="cart.{{ $index }}.unit_price" type="number" step="0.01" min="0" class="form-input min-w-20 py-1">
                                        @else
                                            <span class="tabular-nums">{{ money($line['unit_price']) }}</span>
                                        @endif
                                        @if ($measure)<div class="mt-0.5 text-[11px] text-muted-foreground">/ {{ $measure['unit'] }}</div>@endif
                                    </td>
                                    @can('pos.discount')
                                        <td><input wire:model.live.debounce.500ms="cart.{{ $index }}.discount_amount" type="number" step="0.01" min="0" class="form-input min-w-14 py-1"></td>
                                    @endcan
                                    <td class="text-right font-medium tabular-nums">{{ money($lineTotal) }}</td>
                                    @if ($canViewCost)
                                        <td x-show="showProfit" x-cloak class="no-print whitespace-nowrap bg-amber-50/60 px-2! text-right tabular-nums dark:bg-amber-950/20">
                                            <div @class(['font-medium', 'text-emerald-700' => $lineProfit >= 0, 'text-red-600' => $lineProfit < 0])>{{ money($lineProfit) }}</div>
                                            <div class="text-xs text-muted-foreground" title="{{ money($unitCost) }} × {{ shop_digits($soldQty + 0) }}">{{ money($lineCost) }}</div>
                                        </td>
                                    @endif
                                    <td><button type="button" wire:click="removeLine({{ $index }})" data-confirm="{{ __('Remove this item from the cart?') }}" class="text-muted-foreground hover:text-red-600" title="{{ __('Remove') }}">✕</button></td>
                                </tr>
                            @empty
                                <x-empty :colspan="$canViewCost ? 8 : 7" icon="cart" :title="__('Scan a product to start the sale.')" :text="__('Use the barcode scanner, type a name, or tap a quick item below.')" />
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap gap-2 text-xs text-muted-foreground-1">
                        @foreach (['F2' => __('Search'), 'F8' => __('Hold'), 'F9' => __('Full screen'), 'F10' => __('Pay'), 'Esc' => __('Back')] as $key => $label)
                            <span class="inline-flex items-center gap-1.5"><kbd class="rounded border border-card-line bg-layer px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground-2">{{ $key }}</kbd> {{ $label }}</span>
                        @endforeach
                    </div>
                    @if ($canViewCost)
                        <button type="button" x-on:click="showProfit = ! showProfit" x-bind:aria-pressed="showProfit" class="no-print btn-secondary btn-sm"
                            x-bind:class="showProfit && 'border-amber-300! bg-amber-50! text-amber-800! dark:bg-amber-950/40! dark:text-amber-300!'">
                            <x-icon name="eye" class="size-3.5" x-show="! showProfit" />
                            <x-icon name="eye-off" class="size-3.5" x-show="showProfit" x-cloak />
                            <span x-text="showProfit ? @js(__('Hide cost & profit')) : @js(__('Show cost & profit'))">{{ __('Show cost & profit') }}</span>
                        </button>
                    @endif
                </div>

                @if ($quickItems->isNotEmpty())
                    <div class="card p-4">
                        <h3 class="mb-3 text-xs font-medium text-muted-foreground-1">{{ __('Quick items') }}</h3>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-6">
                            @foreach ($quickItems as $quick)
                                <button type="button" wire:click="pickUnit({{ $quick->id }})" wire:key="quick-{{ $quick->id }}"
                                    class="flex min-h-16 flex-col justify-between rounded-lg border border-card-line bg-layer p-2.5 text-left transition hover:border-primary-400 hover:bg-primary-50">
                                    <span class="line-clamp-2 text-xs font-medium text-foreground">{{ $quick->product->localName() }}@unless ($quick->is_base) ({{ $quick->localName() }})@endunless</span>
                                    <span class="mt-1 text-xs font-semibold tabular-nums text-primary-700">{{ money($quick->selling_price) }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

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
            <div class="space-y-3 xl:sticky xl:top-20 xl:self-start">
                <div class="card p-4">
                    @if ($customer)
                        <div class="flex items-center justify-between text-sm">
                            <div><div class="font-medium">{{ $customer->name }}</div><div class="text-muted-foreground-1">{{ $customer->phone }}</div></div>
                            <button type="button" wire:click="$set('customerId', null)" class="text-xs text-muted-foreground-1 hover:underline">{{ __('Change') }}</button>
                        </div>
                        @if ($customerDue > 0 || $customer->credit_limit !== null)
                            <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 border-t border-card-line pt-2 text-xs">
                                <span @class(['font-medium', 'text-red-600' => $customerDue > 0, 'text-muted-foreground-1' => $customerDue <= 0])>{{ __('Previous due') }}: {{ money($customerDue) }}</span>
                                @if ($customerDue > 0)
                                    @can('customers.collect-due')
                                        <a href="{{ route('customers.show', ['customer' => $customer, 'collect' => 1]) }}" target="_blank" class="font-medium text-primary hover:underline">{{ __('Collect due') }} ↗</a>
                                    @endcan
                                @endif
                                @if ($customer->credit_limit !== null)
                                    <span class="text-muted-foreground-1">{{ __('Credit limit') }}: {{ money($customer->credit_limit) }}</span>
                                @endif
                            </div>
                        @endif
                    @else
                        <label class="form-label">{{ __('Customer (optional)') }}</label>
                        <input wire:model.live.debounce.300ms="customerSearch" type="search" placeholder="{{ __('Phone number') }}" class="form-input">
                        @foreach ($customerResults as $result)
                            <button type="button" wire:click="selectCustomer({{ $result->id }})" wire:key="cust-{{ $result->id }}" class="mt-1 block w-full rounded px-2 py-1 text-left text-sm hover:bg-muted-hover">
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

                {{-- Reference person (optional) --}}
                <div class="card p-4">
                    @if ($referrer)
                        <div class="flex items-center justify-between gap-2 text-sm">
                            <div class="min-w-0">
                                <div class="text-xs text-muted-foreground-1">{{ __('Reference') }}</div>
                                <div class="truncate font-medium">{{ $referrer->name }}@if ($referrer->phone) <span class="font-normal text-muted-foreground-1">· {{ $referrer->phone }}</span>@endif</div>
                            </div>
                            <button type="button" wire:click="clearReferrer" class="shrink-0 text-xs text-muted-foreground-1 hover:underline">{{ __('Remove') }}</button>
                        </div>
                    @else
                        <label class="form-label">{{ __('Reference (optional)') }}</label>
                        <input wire:model.live.debounce.300ms="referrerSearch" type="search" placeholder="{{ __('Name or phone') }}" class="form-input">
                        @foreach ($referrerResults as $result)
                            <button type="button" wire:click="selectReferrer({{ $result->id }})" wire:key="ref-{{ $result->id }}" class="mt-1 block w-full rounded px-2 py-1 text-left text-sm hover:bg-muted-hover">
                                {{ $result->name }}@if ($result->phone) · {{ $result->phone }}@endif
                            </button>
                        @endforeach
                        @if (mb_strlen(trim($referrerSearch)) >= 2 && $referrerResults->isEmpty())
                            <div class="mt-2 space-y-1.5 rounded-md border border-dashed border-card-line p-2">
                                <p class="text-xs text-muted-foreground-1">{{ __('Not found. Add a new reference:') }}</p>
                                <input wire:model="newReferrerName" type="text" placeholder="{{ __('Name') }}" class="form-input py-1 text-sm">
                                <div class="flex gap-2">
                                    <input wire:model="newReferrerPhone" type="text" placeholder="{{ __('Phone (optional)') }}" class="form-input py-1 text-sm">
                                    <button type="button" wire:click="createReferrer" class="btn-secondary btn-sm shrink-0">{{ __('Add') }}</button>
                                </div>
                                @error('newReferrerName') <p class="form-error">{{ $message }}</p> @enderror
                                @error('newReferrerPhone') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
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
                            <div class="flex justify-between text-muted-foreground-1"><span></span><span class="tabular-nums">− {{ money($totals['discount_amount']) }}</span></div>
                        @endif
                        @error('discountValue') <p class="form-error">{{ $message }}</p> @enderror
                    @endcan
                    @if ($vatEnabled && $totals['vat_amount'] > 0)
                        <div class="flex justify-between"><span>{{ __('VAT') }}</span><span class="tabular-nums">{{ money($totals['vat_amount']) }}</span></div>
                    @endif
                    @if ($totals['rounding_amount'] != 0)
                        <div class="flex justify-between text-muted-foreground-1"><span>{{ __('Rounding') }}</span><span class="tabular-nums">{{ $totals['rounding_amount'] > 0 ? '+' : '−' }} {{ money(abs($totals['rounding_amount'])) }}</span></div>
                    @endif
                    @if ($canViewCost && $cart)
                        <div x-show="showProfit" x-cloak class="no-print space-y-1 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs dark:border-amber-900 dark:bg-amber-950/30">
                            <div class="flex justify-between text-amber-900 dark:text-amber-200"><span>{{ __('Total buying price') }}</span><span class="tabular-nums">{{ money($costTotal) }}</span></div>
                            <div @class(['flex justify-between text-sm font-semibold', 'text-emerald-700' => $profitTotal >= 0, 'text-red-600' => $profitTotal < 0])>
                                <span>{{ __('Total profit') }}</span>
                                <span class="tabular-nums">{{ money($profitTotal) }}@if ($totals['subtotal'] - $totals['discount_amount'] > 0) <span class="text-xs font-normal">({{ shop_digits(round($profitTotal / ($totals['subtotal'] - $totals['discount_amount']) * 100, 1)) }}%)</span>@endif</span>
                            </div>
                        </div>
                    @endif
                    <div class="-mx-4 -mb-4 mt-3 flex items-baseline justify-between rounded-b-xl bg-slate-900 px-4 py-4 text-white"><span class="text-sm font-medium text-muted-foreground">{{ __('Total') }}</span><span class="text-3xl font-bold tabular-nums">{{ money($totals['total']) }}</span></div>
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
                                        <button type="button" wire:click="removePayment({{ $index }})" data-confirm="{{ __('Remove this payment line?') }}" class="btn-secondary btn-sm">✕</button>
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
                        <div class="grid grid-cols-2 gap-1">
                            <button type="button" wire:click="payExact(0)" class="btn-secondary btn-sm">{{ __('Full paid') }}</button>
                            @if ($creditAllowed)
                                <button type="button" wire:click="allOnDue" class="btn-secondary btn-sm text-red-600">{{ __('All on due') }}</button>
                            @endif
                        </div>
                        @error('payments') <p class="form-error text-sm">{{ $message }}</p> @enderror
                        <div class="space-y-1 border-t border-card-line pt-2 text-sm">
                            <div class="flex justify-between"><span>{{ __('Paid') }}</span><span class="tabular-nums">{{ money($paid) }}</span></div>
                            @if ($due > 0)
                                <div class="flex justify-between font-semibold text-red-600"><span>{{ $paid > 0 ? __('Due (partly paid)') : __('Due (not paid)') }}</span><span class="tabular-nums">{{ money($due) }}</span></div>
                            @else
                                <div class="flex justify-between text-xl font-bold text-emerald-700"><span>{{ __('Change amount') }}</span><span class="tabular-nums">{{ money($change) }}</span></div>
                            @endif
                        </div>
                        @if ($due > 0)
                            @if (! $creditAllowed)
                                <p class="rounded-md bg-red-50 px-3 py-2 text-xs text-red-700">{{ __('Full payment is required.') }}</p>
                            @elseif (! $customer)
                                <p class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">{{ __('Select a customer above to sell on due.') }}</p>
                            @else
                                <div class="space-y-2 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-900">
                                    <p>{{ __(':amount will be added to :name\'s due. Total due after this sale: :total.', ['amount' => money($due), 'name' => $customer->name, 'total' => money($customerDue + $due)]) }}</p>
                                    <label class="flex items-center justify-between gap-2">
                                        <span>{{ __('Pay by') }}</span>
                                        <input wire:model="dueDate" type="date" min="{{ today()->toDateString() }}" placeholder="{{ $defaultDueDate }}" class="form-input h-7 w-36 py-0 text-xs">
                                    </label>
                                    @error('dueDate') <p class="form-error">{{ $message }}</p> @enderror
                                </div>
                            @endif
                        @endif
                        <input wire:model="note" type="text" placeholder="{{ __('Note on receipt (optional)') }}" class="form-input text-sm">
                        <div class="flex gap-2">
                            <button type="button" wire:click="$set('showPayment', false)" class="btn-secondary">{{ __('Back') }}</button>
                            <button type="button" wire:click="complete" wire:loading.attr="disabled" @disabled($due > 0 && (! $creditAllowed || ! $customer)) class="btn-primary h-11 flex-1 text-base">
                                {{ $due > 0 && $creditAllowed && $customer ? __('Complete on due') : __('Complete sale') }} (F10)
                            </button>
                        </div>
                    </div>
                @else
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" wire:click="hold" @disabled(! $cart) class="btn-secondary h-9">{{ __('Hold') }} (F8)</button>
                        <button type="button" wire:click="clearCart" data-confirm="{{ __('Clear the cart?') }}" data-confirm-button="{{ __('Clear') }}" @disabled(! $cart) class="btn-secondary h-9">{{ __('Clear') }}</button>
                        <input wire:model="holdNote" type="text" placeholder="{{ __('Hold note (e.g. customer name)') }}" class="form-input col-span-2 text-sm">
                        <button type="button" wire:click="openPayment" @disabled(! $cart) class="btn-primary col-span-2 h-12 text-base">{{ __('Pay') }} (F10)</button>
                    </div>
                    @if ($drawerEnabled)
                        @can('pos.open-drawer')
                            <a href="{{ route('pos.drawer') }}" target="_blank" class="block text-center text-xs text-muted-foreground-1 hover:underline">{{ __('Open cash drawer (no sale)') }}</a>
                        @endcan
                    @endif
                @endif
            </div>
        </div>
    @endif

    {{-- Sale completed --}}
    @if ($completedSale)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" x-init="$nextTick(() => $refs.newSale?.focus())">
            <div class="w-full max-w-sm rounded-2xl bg-layer p-6 text-center shadow-xl">
                <x-icon name="check" class="mx-auto size-12 text-emerald-600" />
                <h2 class="mt-2 text-lg font-semibold">{{ __('Sale completed') }}</h2>
                <p class="font-mono text-sm text-muted-foreground-1">{{ $completedSale->invoice_no }}</p>
                @if ($completedSale->due_amount > 0)
                    <div class="mt-4 rounded-lg bg-red-50 p-3">
                        <div class="text-sm text-red-700">{{ __('Added to due') }}</div>
                        <div class="text-3xl font-bold text-red-700">{{ money($completedSale->due_amount) }}</div>
                        <div class="mt-1 text-xs text-red-700/80">{{ __('Pay by') }} {{ shop_digits($completedSale->due_date?->format('d M Y')) }}</div>
                    </div>
                @else
                    <div class="mt-4 rounded-lg bg-muted p-3">
                        <div class="text-sm text-muted-foreground-1">{{ __('Change to return') }}</div>
                        <div class="text-3xl font-bold text-emerald-700">{{ money($completedSale->change_amount) }}</div>
                    </div>
                @endif
                <div class="mt-5 flex flex-col gap-2">
                    @if ($printMode !== 'off')
                        <button type="button" x-on:click="printUrl('{{ route('sales.receipt', $completedSale) }}')" class="btn-secondary h-10">
                            {{ $printMode === 'on' ? __('Print again') : __('Print receipt') }}
                        </button>
                    @endif
                    <button type="button" x-ref="newSale" wire:click="newSale" x-on:click="setTimeout(() => focusScan(), 50)" class="btn-primary h-10">{{ __('New sale') }} (Enter)</button>
                </div>
            </div>
        </div>
    @endif
</div>
