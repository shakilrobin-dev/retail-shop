<div>
    <x-flash />
    <x-page-header :title="__('Expenses')" :subtitle="__('Rent, salary, bills and other shop costs. Cash from the drawer reduces the expected cash.')">
        <button type="button" wire:click="export" class="btn-secondary"><x-icon name="receipt" class="size-4" /> {{ __('Export') }}</button>
        <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> {{ __('New expense') }}</button>
    </x-page-header>

    <div class="grid gap-5 xl:grid-cols-[1fr_20rem]">
        <div class="card overflow-hidden">
            <div class="flex flex-wrap items-center gap-2 border-b border-card-divider px-4 py-3">
                <div class="flex items-center gap-1 rounded-lg border border-layer-line bg-layer px-2">
                    <x-icon name="history" class="size-4 text-muted-foreground" />
                    <input wire:model.live="from" type="date" class="border-0 py-1.5 text-sm focus:ring-0">
                    <span class="text-muted-foreground">–</span>
                    <input wire:model.live="to" type="date" class="border-0 py-1.5 text-sm focus:ring-0">
                </div>
                <select wire:model.live="categoryFilter" class="form-input w-48">
                    <option value="">{{ __('All categories') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->localName() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <x-table.sort field="date" :sort="$sortField" :dir="$sortDirection">{{ __('Date') }}</x-table.sort>
                            <th>{{ __('Category') }}</th>
                            <th>{{ __('Note') }}</th>
                            <th>{{ __('Paid by') }}</th>
                            <x-table.sort field="amount" :sort="$sortField" :dir="$sortDirection" align="right">{{ __('Amount') }}</x-table.sort>
                            <th class="w-12"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($expenses as $expense)
                            <tr wire:key="exp-{{ $expense->id }}">
                                <td class="whitespace-nowrap text-muted-foreground-2">{{ shop_digits($expense->expense_date->format('d M Y')) }}</td>
                                <td><x-badge>{{ $expense->category->localName() }}</x-badge></td>
                                <td class="text-foreground">{{ $expense->note ?? '—' }}<div class="text-xs text-muted-foreground">{{ $expense->user?->name }}</div></td>
                                <td class="text-xs text-muted-foreground-2">{{ $expense->payments->first()?->paymentMethod->localName() }}</td>
                                <td class="text-right font-semibold tabular-nums text-foreground">{{ money($expense->amount) }}</td>
                                <td class="text-right"><button type="button" wire:click="delete({{ $expense->id }})" data-confirm="{{ __('Delete this expense?') }}" data-confirm-button="{{ __('Delete') }}" class="btn-ghost btn-sm text-red-600" title="{{ __('Delete') }}"><x-icon name="close" class="size-4" /></button></td>
                            </tr>
                        @empty
                            <x-empty :colspan="6" icon="banknotes" :title="__('No expenses in this period.')">
                                <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> {{ __('New expense') }}</button>
                            </x-empty>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-table.footer :paginator="$expenses" />
        </div>

        <x-section :title="__('By category')">
            @php $max = max(1, (float) $byCategory->max('total')); @endphp
            <div class="space-y-3">
                @forelse ($byCategory as $row)
                    <div>
                        <div class="flex justify-between text-sm"><span class="text-foreground">{{ $row->category?->localName() }}</span><span class="font-medium tabular-nums">{{ money($row->total) }}</span></div>
                        <div class="mt-1 h-1.5 rounded-full bg-surface"><div class="h-full rounded-full bg-primary-500" style="width: {{ round($row->total / $max * 100) }}%"></div></div>
                    </div>
                @empty
                    <p class="text-sm text-muted-foreground-1">{{ __('No expenses in this period.') }}</p>
                @endforelse
            </div>
            <div class="mt-4 flex justify-between border-t border-card-line pt-3 text-sm font-semibold"><span>{{ __('Total') }}</span><span class="tabular-nums">{{ money($total) }}</span></div>
        </x-section>
    </div>

    <x-drawer wire:model="showForm" :title="__('New expense')">
        <form id="expense-form" wire:submit="save" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.field :label="__('Date')" required error="expense_date" stacked><input wire:model="expense_date" type="date" class="form-input"></x-form.field>
                <x-form.field :label="__('Amount')" required error="amount" stacked><input wire:model="amount" type="number" step="0.01" min="0" class="form-input text-right"></x-form.field>
            </div>
            <x-form.field :label="__('Category')" required error="expense_category_id" stacked>
                <select wire:model="expense_category_id" class="form-input">
                    <option value="">{{ __('Choose...') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->localName() }}</option>
                    @endforeach
                </select>
                <div class="mt-2 flex gap-1">
                    <input wire:model="newCategory" type="text" placeholder="{{ __('New category') }}" class="form-input py-1.5 text-sm">
                    <button type="button" wire:click="addCategory" class="btn-secondary btn-sm">{{ __('Add') }}</button>
                </div>
                @error('newCategory') <p class="form-error">{{ $message }}</p> @enderror
            </x-form.field>
            <x-form.field :label="__('Paid by')" stacked>
                <select wire:model="payment_method_id" class="form-input">
                    @foreach ($paymentMethods as $method)
                        <option value="{{ $method->id }}">{{ $method->localName() }}</option>
                    @endforeach
                </select>
                <label class="mt-2 flex items-center gap-2 text-sm text-muted-foreground-2"><input wire:model="from_drawer" type="checkbox" class="rounded border-layer-line"> {{ __('Cash taken from the cash drawer') }}</label>
            </x-form.field>
            <x-form.field :label="__('Note')" stacked><input wire:model="note" type="text" class="form-input" placeholder="{{ __('e.g. November shop rent') }}"></x-form.field>
        </form>
        <x-slot:footer>
            <button type="button" wire:click="$set('showForm', false)" class="btn-secondary">{{ __('Cancel') }}</button>
            <button type="submit" form="expense-form" class="btn-primary">{{ __('Save expense') }}</button>
        </x-slot:footer>
    </x-drawer>
</div>
