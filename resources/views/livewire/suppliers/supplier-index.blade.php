<div class="space-y-4">
    <x-flash />

    <div class="flex flex-wrap gap-2">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('Search name, company or phone') }}" class="form-input max-w-sm">
        <button type="button" wire:click="create" class="btn-primary">+ {{ __('New supplier') }}</button>
    </div>

    @if ($showForm)
        <form wire:submit="save" class="card grid gap-3 p-5 md:grid-cols-3">
            <div>
                <label class="form-label">{{ __('Name') }} *</label>
                <input wire:model="name" type="text" class="form-input" autofocus>
                @error('name') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">{{ __('Company') }}</label>
                <input wire:model="company" type="text" class="form-input">
            </div>
            <div>
                <label class="form-label">{{ __('Phone') }}</label>
                <input wire:model="phone" type="text" class="form-input">
            </div>
            <div>
                <label class="form-label">{{ __('Email') }}</label>
                <input wire:model="email" type="email" class="form-input">
                @error('email') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div class="md:col-span-2">
                <label class="form-label">{{ __('Address') }}</label>
                <input wire:model="address" type="text" class="form-input">
            </div>
            <div class="md:col-span-3">
                <label class="form-label">{{ __('Notes') }}</label>
                <textarea wire:model="notes" rows="2" class="form-input"></textarea>
            </div>
            <div class="flex justify-end gap-2 md:col-span-3">
                <button type="button" wire:click="$set('showForm', false)" class="btn-secondary">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-primary">{{ __('Save') }}</button>
            </div>
        </form>
    @endif

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Phone') }}</th><th class="text-right">{{ __('Purchases') }}</th><th class="text-right">{{ __('Total bought') }}</th><th></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($suppliers as $supplier)
                    <tr wire:key="supplier-{{ $supplier->id }}" @class(['opacity-50' => ! $supplier->is_active])>
                        <td><div class="font-medium">{{ $supplier->name }}</div><div class="text-xs text-slate-500">{{ $supplier->company }}</div></td>
                        <td>{{ $supplier->phone }}</td>
                        <td class="text-right">{{ shop_digits($supplier->purchases_count) }}</td>
                        <td class="text-right tabular-nums">{{ money($supplier->purchases_sum_total) }}</td>
                        <td class="whitespace-nowrap text-right">
                            <a href="{{ route('purchases.index', ['supplierId' => $supplier->id]) }}" class="btn-secondary btn-sm" wire:navigate>{{ __('Purchases') }}</a>
                            <button type="button" wire:click="edit({{ $supplier->id }})" class="btn-secondary btn-sm">{{ __('Edit') }}</button>
                            <button type="button" wire:click="toggleActive({{ $supplier->id }})" class="btn-secondary btn-sm">{{ $supplier->is_active ? __('Deactivate') : __('Activate') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-slate-500">{{ __('No suppliers yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $suppliers->links() }}
</div>
