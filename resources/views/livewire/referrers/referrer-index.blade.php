<div>
    <x-flash />
    <x-page-header :title="__('References')" :subtitle="__('People who bring customers (mason, contractor, engineer...) and the commission they earn.')">
        <button type="button" wire:click="export" class="btn-secondary"><x-icon name="receipt" class="size-4" /> {{ __('Export') }}</button>
        <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> {{ __('New reference') }}</button>
    </x-page-header>

    <div class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi :label="__('Active references')" :value="shop_digits($summary['active'])" icon="users" tone="sky" />
        <x-kpi :label="__('Commission earned')" :value="money($summary['earned'])" icon="trending-up" tone="indigo" :hint="__('All time')" />
        <x-kpi :label="__('Commission paid')" :value="money($summary['paid'])" icon="banknotes" tone="emerald" :hint="__('This month').': '.money($summary['paid_month'])" />
        <x-kpi :label="__('Commission due')" :value="money($summary['due'])" icon="warning" tone="amber" />
    </div>

    <div class="card overflow-hidden">
        <x-list-toolbar :placeholder="__('Search name or phone')" />
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <x-table.sort field="name" :sort="$sortField" :dir="$sortDirection">{{ __('Reference') }}</x-table.sort>
                        <th class="text-right">{{ __('Default %') }}</th>
                        <x-table.sort field="sales" :sort="$sortField" :dir="$sortDirection" align="right">{{ __('Invoices') }}</x-table.sort>
                        <th class="text-right">{{ __('Sales') }}</th>
                        <x-table.sort field="earned" :sort="$sortField" :dir="$sortDirection" align="right">{{ __('Earned') }}</x-table.sort>
                        <x-table.sort field="paid" :sort="$sortField" :dir="$sortDirection" align="right">{{ __('Paid') }}</x-table.sort>
                        <th class="text-right">{{ __('Due') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="w-36"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($referrers as $referrer)
                        @php $due = round((float) $referrer->sales_sum_commission_amount - (float) $referrer->commission_payments_sum_amount, 2); @endphp
                        <tr wire:key="referrer-{{ $referrer->id }}" wire:click="edit({{ $referrer->id }})" class="cursor-pointer">
                            <td>
                                <div class="flex items-center gap-3">
                                    <x-avatar :name="$referrer->name" />
                                    <div>
                                        <div class="font-medium text-foreground">{{ $referrer->name }}</div>
                                        <div class="text-xs text-muted-foreground-1">{{ $referrer->phone ?? '—' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-right tabular-nums">{{ $referrer->commission_percent !== null ? shop_digits($referrer->commission_percent + 0).'%' : '—' }}</td>
                            <td class="text-right tabular-nums">{{ shop_digits($referrer->sales_count) }}</td>
                            <td class="text-right tabular-nums">{{ money($referrer->sales_sum_total) }}</td>
                            <td class="text-right tabular-nums">{{ money($referrer->sales_sum_commission_amount) }}</td>
                            <td class="text-right tabular-nums">{{ money($referrer->commission_payments_sum_amount) }}</td>
                            <td @class(['text-right font-medium tabular-nums', 'text-red-600' => $due > 0])>{{ money($due) }}</td>
                            <td><x-badge :tone="$referrer->is_active ? 'green' : 'slate'">{{ $referrer->is_active ? __('Active') : __('Archived') }}</x-badge></td>
                            <td class="whitespace-nowrap text-right" x-on:click.stop>
                                @if ($due > 0)
                                    <button type="button" wire:click="openPay({{ $referrer->id }})" class="btn-secondary btn-sm">{{ __('Pay') }}</button>
                                @endif
                                <button type="button" wire:click="edit({{ $referrer->id }})" class="btn-ghost btn-sm" title="{{ __('Edit') }}"><x-icon name="pencil" class="size-4" /></button>
                                <button type="button" wire:click="toggleActive({{ $referrer->id }})" @if ($referrer->is_active) data-confirm="{{ __('Archive this reference?') }}" data-confirm-button="{{ __('Archive') }}" @endif class="btn-ghost btn-sm" title="{{ $referrer->is_active ? __('Archive') : __('Restore') }}"><x-icon :name="$referrer->is_active ? 'eye-off' : 'eye'" class="size-4" /></button>
                            </td>
                        </tr>
                    @empty
                        <x-empty :colspan="9" icon="users" :title="__('No references yet.')" :text="__('Add them here, or from the POS while making an invoice.')">
                            <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> {{ __('New reference') }}</button>
                        </x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-table.footer :paginator="$referrers" />
    </div>

    <x-drawer wire:model="showForm" :title="$editingId ? __('Edit reference') : __('New reference')">
        <form id="referrer-form" wire:submit="save" class="space-y-4">
            <x-form.field :label="__('Name')" required error="name" stacked><input wire:model="name" type="text" class="form-input"></x-form.field>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.field :label="__('Phone')" error="phone" stacked><input wire:model="phone" type="text" class="form-input"></x-form.field>
                <x-form.field :label="__('Default commission (% of profit)')" error="commission_percent" stacked>
                    <input wire:model="commission_percent" type="number" step="0.01" min="0" max="100" class="form-input text-right" placeholder="{{ __('Optional') }}">
                </x-form.field>
            </div>
            <x-form.field :label="__('Address')" stacked><input wire:model="address" type="text" class="form-input"></x-form.field>
            <x-form.field :label="__('Notes')" stacked><textarea wire:model="notes" rows="3" class="form-input"></textarea></x-form.field>
            <p class="text-xs text-muted-foreground-1">{{ __('The default % is filled in on the invoice. It can be changed on each invoice, or a fixed amount given instead.') }}</p>
        </form>
        @if ($editingId)
            <div class="mt-6 border-t border-card-line pt-5">
                <h3 class="mb-3 text-xs font-medium text-muted-foreground-1">{{ __('Commission payments') }}</h3>
                @forelse ($history as $payment)
                    <div wire:key="cpay-{{ $payment->id }}" class="flex justify-between border-b border-card-divider py-1.5 text-sm last:border-0">
                        <span>{{ shop_digits($payment->expense_date->format('d M Y')) }}@if ($payment->note)<span class="block text-xs text-muted-foreground">{{ $payment->note }}</span>@endif</span>
                        <span class="font-medium tabular-nums">{{ money($payment->amount) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-muted-foreground">{{ __('No commission paid yet.') }}</p>
                @endforelse
            </div>
        @endif
        <x-slot:footer>
            <button type="button" wire:click="$set('showForm', false)" class="btn-secondary">{{ __('Cancel') }}</button>
            <button type="submit" form="referrer-form" class="btn-primary">{{ __('Save') }}</button>
        </x-slot:footer>
    </x-drawer>

    <x-modal wire:model="showPay" :title="__('Pay commission')" :subtitle="$paying ? $paying->name.' · '.__('Due').': '.money($payingDue) : null" width="max-w-md">
        <form id="pay-form" wire:submit="pay" class="space-y-3">
            <div>
                <label class="form-label">{{ __('Amount') }} <span class="text-red-500">*</span></label>
                <input wire:model="payAmount" type="number" step="0.01" min="0" class="form-input text-right text-lg">
                @error('payAmount') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="form-label">{{ __('Paid by') }}</label>
                    <select wire:model="payMethodId" class="form-input">
                        @foreach ($paymentMethods as $method)
                            <option value="{{ $method->id }}">{{ $method->localName() }}</option>
                        @endforeach
                    </select>
                    @error('payMethodId') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">{{ __('Date') }}</label>
                    <input wire:model="payDate" type="date" max="{{ today()->toDateString() }}" class="form-input">
                    @error('payDate') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <input wire:model="payNote" type="text" placeholder="{{ __('Note (optional)') }}" class="form-input">
            <label class="flex items-center gap-2 text-sm text-muted-foreground-2">
                <input wire:model="payFromDrawer" type="checkbox" class="rounded border-layer-line"> {{ __('Cash taken from the cash drawer') }}
            </label>
            <p class="text-xs text-muted-foreground-1">{{ __('Saved as an expense (Commission). It shows as "Commission given" in the profit & loss.') }}</p>
        </form>
        <x-slot:footer>
            <button type="button" x-on:click="open = false" class="btn-secondary">{{ __('Cancel') }}</button>
            <button type="submit" form="pay-form" class="btn-primary" wire:loading.attr="disabled" wire:target="pay">{{ __('Pay commission') }}</button>
        </x-slot:footer>
    </x-modal>
</div>
