<div class="mx-auto max-w-6xl space-y-5"
    x-data
    x-init="
        @if (session('print_slip')) window.open('{{ route('repairs.slip', $job) }}', '_blank'); @endif
        @if (session('print_bill')) window.open('{{ route('repairs.bill', $job) }}', '_blank'); @endif
    ">
    <x-flash />
    <x-page-header class="mb-0" :title="__('Repair').' '.$job->job_no" :subtitle="trim($job->device_brand.' '.$job->device_model).' · '.$job->customer->name" :back="route('repairs.index')" :backLabel="__('Repairs')" />
    @error('stock') <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p> @enderror
    @error('status') <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p> @enderror

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('repairs.slip', $job) }}" target="_blank" class="btn-secondary"><x-icon name="printer" class="size-4" /> {{ __('Job slip') }}</a>
            <a href="{{ route('repairs.bill', $job) }}" target="_blank" class="btn-secondary"><x-icon name="receipt" class="size-4" /> {{ __('Bill') }}</a>
            @unless ($isClosed)
                <button type="button" wire:click="openDelivery" class="btn-primary"><x-icon name="check" class="size-4" /> {{ __('Deliver') }}</button>
                <button type="button" wire:click="$set('panel', 'cancel')" class="btn-secondary text-red-600">{{ __('Cancel job') }}</button>
            @endunless
            @if ($job->status === \App\Enums\RepairStatus::Delivered && ! $job->warranty_of_job_id)
                <button type="button" wire:click="$set('panel', 'warranty')" class="btn-secondary">{{ __('Warranty claim (came back)') }}</button>
            @endif
        </div>
        @if ($job->status === \App\Enums\RepairStatus::Cancelled)
            <x-badge tone="red" class="px-3 py-1 text-sm">{{ $job->status->label() }}</x-badge>
        @else
            <x-status-pipeline :steps="$pipeline" :current="$job->status->value" action="pipelineTo" :disabled="$isClosed" />
        @endif
    </div>

    <div class="flex flex-wrap gap-2">
        <x-smart-button icon="banknotes" :label="__('Bill')" :value="money($job->total)" />
        <x-smart-button icon="check" :label="__('Paid')" :value="money($job->paid_amount)" />
        <x-smart-button icon="cube" :label="__('Parts used')" :value="shop_digits($job->parts->count())" />
        <x-smart-button icon="history" :label="__('Customer repairs')" :value="shop_digits($customerJobs)" :href="route('customers.show', $job->customer)" />
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Job info --}}
        <div class="card space-y-3 p-5 text-sm lg:col-span-2">
            <div class="grid gap-3 sm:grid-cols-3">
                <div><div class="text-slate-500">{{ __('Job no') }}</div><div class="font-mono font-semibold">{{ $job->job_no }}</div></div>
                <div><div class="text-slate-500">{{ __('Customer') }}</div><a href="{{ route('customers.show', $job->customer) }}" class="text-brand-600 hover:underline" wire:navigate>{{ $job->customer->name }}</a><div>{{ $job->customer->phone }}</div></div>
                <div><div class="text-slate-500">{{ __('Received') }}</div><div>{{ shop_digits($job->created_at->format('d M Y h:i A')) }}</div><div class="text-xs text-slate-400">{{ $job->receiver?->name }}</div></div>
                <div><div class="text-slate-500">{{ __('Device') }}</div><div>{{ __('repair_devices.'.$job->device_type) }} · {{ $job->device_brand }} {{ $job->device_model }} {{ $job->device_color }}</div></div>
                <div><div class="text-slate-500">{{ __('IMEI / serial') }}</div><div class="font-mono">{{ $job->imei_serial ?? '—' }}</div></div>
                <div><div class="text-slate-500">{{ __('Password / pattern') }}</div><div x-data="{ show: false }"><span x-show="show" x-cloak>{{ $job->device_password ?? '—' }}</span><button type="button" x-show="!show" x-on:click="show = true" class="text-xs text-brand-600 hover:underline">{{ __('Show') }}</button></div></div>
                <div><div class="text-slate-500">{{ __('Delivery date') }}</div><div>{{ $job->estimated_delivery_at ? shop_digits($job->estimated_delivery_at->format('d M Y h:i A')) : '—' }}</div></div>
                <div><div class="text-slate-500">{{ __('Estimated cost') }}</div><div>{{ $job->estimated_cost !== null ? money($job->estimated_cost) : '—' }}</div></div>
                <div><div class="text-slate-500">{{ __('Accessories received') }}</div><div>{{ collect($job->accessories ?? [])->map(fn ($a) => __('repair_accessories.'.$a))->implode(', ') ?: '—' }}</div></div>
            </div>
            <div><div class="text-slate-500">{{ __('Problem') }}</div><div class="whitespace-pre-line">{{ $job->problem }}</div></div>
            @if ($job->condition_notes)<div><div class="text-slate-500">{{ __('Device condition') }}</div><div class="whitespace-pre-line">{{ $job->condition_notes }}</div></div>@endif
            @if ($job->warrantyOf)<div class="rounded bg-amber-50 p-2 text-amber-800">{{ __('Warranty claim of') }} <a href="{{ route('repairs.show', $job->warrantyOf) }}" class="font-mono underline" wire:navigate>{{ $job->warrantyOf->job_no }}</a></div>@endif
            @if ($job->warranty_until)<div class="rounded bg-emerald-50 p-2 text-emerald-800">{{ __('Repair warranty till :date', ['date' => shop_digits($job->warranty_until->format('d M Y'))]) }}</div>@endif
        </div>

        {{-- Status --}}
        <div class="card space-y-3 p-5">
            <h3 class="font-semibold">{{ __('Status') }}</h3>
            @unless ($isClosed)
                <input wire:model="statusNote" type="text" placeholder="{{ __('Note (optional)') }}" class="form-input text-sm">
                <div class="grid grid-cols-2 gap-2">
                    @foreach ($nextStatuses as $next)
                        <button type="button" wire:click="setStatus('{{ $next->value }}')" @disabled($job->status === $next)
                            @class(['btn-sm btn', 'bg-brand-600 text-white' => $job->status === $next, 'btn-secondary' => $job->status !== $next])>{{ $next->label() }}</button>
                    @endforeach
                </div>

            @endunless
            <ol class="relative ml-2 space-y-4 border-l border-slate-200 pl-5 pt-1 text-xs">
                @foreach ($job->statusHistories->sortByDesc('id') as $history)
                    <li class="relative">
                        <span class="absolute -left-[26px] top-0.5 size-2.5 rounded-full border-2 border-white {{ $loop->first ? 'bg-brand-500' : 'bg-slate-300' }}"></span>
                        <div class="font-medium text-slate-800">{{ $history->status->label() }}</div>
                        <div class="text-slate-500">{{ shop_digits($history->created_at->format('d M, h:i A')) }} · {{ $history->user?->name }}</div>
                        @if ($history->note)<div class="mt-0.5 text-slate-600">{{ $history->note }}</div>@endif
                    </li>
                @endforeach
            </ol>
        </div>
    </div>

    {{-- Delivery / cancel / warranty panels --}}
    @if ($panel === 'deliver' && ! $isClosed)
        <form wire:submit="deliver" class="card space-y-3 border-2 border-brand-200 p-5">
            <h3 class="font-semibold">{{ __('Deliver device') }} · {{ __('Due') }}: {{ money(max(0, $due)) }}</h3>
            @foreach ($deliveryPayments as $index => $payment)
                <div wire:key="dpay-{{ $index }}" class="flex flex-wrap gap-2">
                    <select wire:model="deliveryPayments.{{ $index }}.payment_method_id" class="form-input w-36">
                        @foreach ($paymentMethods as $method)
                            <option value="{{ $method->id }}">{{ $method->localName() }}</option>
                        @endforeach
                    </select>
                    <input wire:model="deliveryPayments.{{ $index }}.amount" type="number" step="0.01" min="0" class="form-input w-36 text-right">
                    <input wire:model="deliveryPayments.{{ $index }}.transaction_ref" type="text" placeholder="{{ __('Transaction ID (optional)') }}" class="form-input w-56">
                </div>
            @endforeach
            <button type="button" wire:click="addDeliveryPayment" class="text-xs text-brand-600 hover:underline">+ {{ __('split payment') }}</button>
            @error('payments') <p class="form-error">{{ $message }}</p> @enderror
            <div class="flex gap-2">
                <button type="button" wire:click="$set('panel', '')" class="btn-secondary">{{ __('Back') }}</button>
                <button type="submit" class="btn-primary">{{ __('Take payment & deliver') }}</button>
            </div>
        </form>
    @endif

    @if ($panel === 'cancel' && ! $isClosed)
        <form wire:submit="cancelJob" class="card grid gap-3 border-2 border-red-200 p-5 md:grid-cols-4">
            <div class="md:col-span-2">
                <label class="form-label">{{ __('Reason') }}</label>
                <input wire:model="cancel_reason" type="text" class="form-input" placeholder="{{ __('e.g. Not repairable, customer refused cost') }}">
                @error('cancel_reason') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">{{ __('Checking fee kept') }}</label>
                <input wire:model="cancel_fee" type="number" step="0.01" min="0" class="form-input">
            </div>
            <div>
                <label class="form-label">{{ __('Refund advance by') }}</label>
                <select wire:model="refund_method_id" class="form-input">
                    @foreach ($paymentMethods as $method)
                        <option value="{{ $method->id }}">{{ $method->localName() }}</option>
                    @endforeach
                </select>
                @error('refund_method_id') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <p class="text-xs text-slate-500 md:col-span-4">{{ __('Used parts go back to stock. Paid amount above the fee is refunded.') }}</p>
            <div class="flex gap-2 md:col-span-4">
                <button type="button" wire:click="$set('panel', '')" class="btn-secondary">{{ __('Back') }}</button>
                <button type="submit" wire:confirm="{{ __('Cancel this repair job?') }}" class="btn-danger">{{ __('Cancel job') }}</button>
            </div>
        </form>
    @endif

    @if ($panel === 'warranty')
        <form wire:submit="createWarrantyClaim" class="card space-y-3 border-2 border-amber-200 p-5">
            <label class="form-label">{{ __('What is the problem now?') }}</label>
            <textarea wire:model="warranty_problem" rows="2" class="form-input"></textarea>
            @error('warranty_problem') <p class="form-error">{{ $message }}</p> @enderror
            @error('problem') <p class="form-error">{{ $message }}</p> @enderror
            <div class="flex gap-2">
                <button type="button" wire:click="$set('panel', '')" class="btn-secondary">{{ __('Back') }}</button>
                <button type="submit" class="btn-primary">{{ __('Create warranty job') }}</button>
            </div>
        </form>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Parts --}}
        <div class="card space-y-3 p-5 lg:col-span-2">
            <h3 class="font-semibold">{{ __('Parts used') }}</h3>
            @unless ($isClosed)
                <x-product-search :results="$results" :message="$scanMessage" :placeholder="__('Scan or search spare part to use...')" />
            @endunless
            <table class="data-table">
                <thead><tr><th>{{ __('Part') }}</th><th class="text-right">{{ __('Qty') }}</th><th class="text-right">{{ __('Price') }}</th><th class="text-right">{{ __('Total') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($job->parts as $part)
                        <tr wire:key="part-{{ $part->id }}">
                            <td>{{ $part->product_name }}</td>
                            <td class="text-right">{{ shop_digits($part->quantity + 0) }}</td>
                            <td class="text-right tabular-nums">{{ money($part->unit_price) }}</td>
                            <td class="text-right tabular-nums">{{ money($part->line_total) }}</td>
                            <td class="text-right">@unless ($isClosed)<button type="button" wire:click="removePart({{ $part->id }})" wire:confirm="{{ __('Remove part and put back in stock?') }}" class="btn-secondary btn-sm">✕</button>@endunless</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-4 text-center text-slate-400">{{ __('No parts used yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Bill --}}
        <div class="card space-y-3 p-5 text-sm">
            <h3 class="font-semibold">{{ __('Bill') }}</h3>
            <form wire:submit="saveCharges" class="space-y-2">
                <div class="flex justify-between"><span>{{ __('Parts') }}</span><span class="tabular-nums">{{ money($job->parts_total) }}</span></div>
                <div class="flex items-center justify-between gap-2"><span>{{ __('Service charge') }}</span><input wire:model="service_charge" type="number" step="0.01" min="0" @disabled($isClosed) class="form-input w-32 py-1 text-right"></div>
                <div class="flex items-center justify-between gap-2"><span>{{ __('Discount') }}</span><input wire:model="discount" type="number" step="0.01" min="0" @disabled($isClosed) class="form-input w-32 py-1 text-right"></div>
                @if ($techniciansEnabled)
                    <select wire:model="technician_id" @disabled($isClosed) class="form-input py-1">
                        <option value="">{{ __('No technician') }}</option>
                        @foreach ($technicians as $technician)
                            <option value="{{ $technician->id }}">{{ $technician->name }}</option>
                        @endforeach
                    </select>
                @endif
                @unless ($isClosed)<button type="submit" class="btn-secondary btn-sm w-full">{{ __('Update bill') }}</button>@endunless
            </form>
            <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-semibold"><span>{{ __('Total') }}</span><span class="tabular-nums">{{ money($job->total) }}</span></div>
            <div class="flex justify-between"><span>{{ __('Paid') }}</span><span class="tabular-nums">{{ money($job->paid_amount) }}</span></div>
            <div @class(['flex justify-between font-semibold', 'text-red-600' => $due > 0, 'text-emerald-700' => $due <= 0])><span>{{ $due >= 0 ? __('Due') : ($isClosed ? __('Refund due') : __('Advance balance')) }}</span><span class="tabular-nums">{{ money(abs($due)) }}</span></div>

            @unless ($isClosed)
                <form wire:submit="addPayment" class="flex gap-1 border-t border-slate-200 pt-3">
                    <input wire:model="payment_amount" type="number" step="0.01" min="0" placeholder="{{ __('Advance') }}" class="form-input py-1">
                    <select wire:model="payment_method_id" class="form-input w-28 py-1">
                        @foreach ($paymentMethods as $method)
                            <option value="{{ $method->id }}">{{ $method->localName() }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn-secondary btn-sm">+</button>
                </form>
                @error('payment_amount') <p class="form-error">{{ $message }}</p> @enderror
            @endunless

            <div class="space-y-1 border-t border-slate-200 pt-2 text-xs text-slate-500">
                @foreach ($job->payments as $payment)
                    <div class="flex justify-between"><span>{{ shop_digits($payment->paid_at->format('d M')) }} · {{ $payment->direction->label() }} · {{ $payment->paymentMethod->localName() }}</span><span class="tabular-nums">{{ money($payment->amount) }}</span></div>
                @endforeach
            </div>
        </div>
    </div>
</div>
