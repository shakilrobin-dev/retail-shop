<div class="space-y-4">
    <x-flash />

    <form wire:submit="save" class="card grid gap-3 p-5 md:grid-cols-6">
        <div>
            <label class="form-label">{{ __('Date') }}</label>
            <input wire:model="expense_date" type="date" class="form-input">
            @error('expense_date') <p class="form-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="form-label">{{ __('Category') }}</label>
            <select wire:model="expense_category_id" class="form-input">
                <option value="">{{ __('Choose...') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->localName() }}</option>
                @endforeach
            </select>
            @error('expense_category_id') <p class="form-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="form-label">{{ __('Amount') }}</label>
            <input wire:model="amount" type="number" step="0.01" min="0" class="form-input">
            @error('amount') <p class="form-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="form-label">{{ __('Paid by') }}</label>
            <select wire:model="payment_method_id" class="form-input">
                @foreach ($paymentMethods as $method)
                    <option value="{{ $method->id }}">{{ $method->localName() }}</option>
                @endforeach
            </select>
        </div>
        <div class="md:col-span-2">
            <label class="form-label">{{ __('Note') }}</label>
            <input wire:model="note" type="text" class="form-input">
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-600 md:col-span-3"><input wire:model="from_drawer" type="checkbox" class="rounded border-slate-300"> {{ __('Cash taken from the cash drawer') }}</label>
        <div class="flex justify-end md:col-span-3"><button type="submit" class="btn-primary">{{ __('Add expense') }}</button></div>
    </form>

    <div class="flex flex-wrap items-end gap-2">
        <input wire:model.live="from" type="date" class="form-input w-auto">
        <input wire:model.live="to" type="date" class="form-input w-auto">
        <select wire:model.live="categoryFilter" class="form-input w-auto">
            <option value="">{{ __('All categories') }}</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}">{{ $category->localName() }}</option>
            @endforeach
        </select>
        <div class="flex-1"></div>
        <form wire:submit="addCategory" class="flex gap-1">
            <input wire:model="newCategory" type="text" placeholder="{{ __('New category') }}" class="form-input w-44">
            <button type="submit" class="btn-secondary">+</button>
        </form>
    </div>
    @error('newCategory') <p class="form-error">{{ $message }}</p> @enderror

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="card overflow-x-auto lg:col-span-2">
            <table class="data-table">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Category') }}</th><th>{{ __('Note') }}</th><th>{{ __('Paid by') }}</th><th class="text-right">{{ __('Amount') }}</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($expenses as $expense)
                        <tr wire:key="exp-{{ $expense->id }}">
                            <td class="whitespace-nowrap">{{ shop_digits($expense->expense_date->format('d M Y')) }}</td>
                            <td>{{ $expense->category->localName() }}</td>
                            <td class="text-slate-600">{{ $expense->note }}<div class="text-xs text-slate-400">{{ $expense->user?->name }}</div></td>
                            <td class="text-xs">{{ $expense->payments->first()?->paymentMethod->localName() }}</td>
                            <td class="text-right tabular-nums">{{ money($expense->amount) }}</td>
                            <td class="text-right"><button type="button" wire:click="delete({{ $expense->id }})" wire:confirm="{{ __('Delete this expense?') }}" class="btn-secondary btn-sm">✕</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-slate-500">{{ __('No expenses in this period.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card p-5 text-sm">
            <h3 class="mb-2 font-semibold">{{ __('By category') }}</h3>
            @foreach ($byCategory as $row)
                <div class="flex justify-between py-1"><span>{{ $row->category?->localName() }}</span><span class="tabular-nums">{{ money($row->total) }}</span></div>
            @endforeach
            <div class="mt-2 flex justify-between border-t border-slate-200 pt-2 font-semibold"><span>{{ __('Total') }}</span><span class="tabular-nums">{{ money($total) }}</span></div>
        </div>
    </div>
    {{ $expenses->links() }}
</div>
