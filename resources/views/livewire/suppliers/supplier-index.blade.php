<div class="space-y-5">
    <x-flash />
    <x-page-header class="mb-0" :title="__('Suppliers')" :subtitle="__('Companies and people you buy stock from.')">
        <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> {{ __('New supplier') }}</button>
    </x-page-header>

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

    <div class="card">
        <div class="border-b border-slate-100 p-3">
            <div class="relative max-w-md">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('Search name, company or phone') }}" class="form-input pl-9">
            </div>
        </div>
        <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Phone') }}</th><th class="text-right">{{ __('Purchases') }}</th><th class="text-right">{{ __('Total bought') }}</th><th></th></tr></thead>
            <tbody>
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
                    <x-empty :colspan="5" icon="building" :title="__('No suppliers yet.')" />
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
    {{ $suppliers->links() }}
</div>
