<div>
    <x-flash />
    <x-page-header :title="__('Suppliers')" :subtitle="__('Companies and people you buy stock from.')">
        <button type="button" wire:click="export" class="btn-secondary"><x-icon name="receipt" class="size-4" /> {{ __('Export') }}</button>
        <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> {{ __('New supplier') }}</button>
    </x-page-header>

    <div class="card overflow-hidden">
        <x-list-toolbar :placeholder="__('Search name, company or phone')" />
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <x-table.sort field="name" :sort="$sortField" :dir="$sortDirection">{{ __('Supplier') }}</x-table.sort>
                        <th>{{ __('Contact') }}</th>
                        <x-table.sort field="purchases" :sort="$sortField" :dir="$sortDirection" align="right">{{ __('Purchases') }}</x-table.sort>
                        <x-table.sort field="total" :sort="$sortField" :dir="$sortDirection" align="right">{{ __('Total bought') }}</x-table.sort>
                        <th>{{ __('Status') }}</th>
                        <th class="w-28"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($suppliers as $supplier)
                        <tr wire:key="supplier-{{ $supplier->id }}" x-on:click="Livewire.navigate('{{ route('purchases.index', ['supplierId' => $supplier->id]) }}')" class="cursor-pointer">
                            <td>
                                <div class="flex items-center gap-3">
                                    <x-avatar :name="$supplier->company ?: $supplier->name" />
                                    <div>
                                        <div class="font-medium text-slate-900">{{ $supplier->name }}</div>
                                        <div class="text-xs text-slate-500">{{ $supplier->company ?? '—' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-slate-600">{{ $supplier->phone ?? '—' }}@if ($supplier->email)<div class="text-xs text-slate-400">{{ $supplier->email }}</div>@endif</td>
                            <td class="text-right tabular-nums">{{ shop_digits($supplier->purchases_count) }}</td>
                            <td class="text-right font-medium tabular-nums">{{ money($supplier->purchases_sum_total) }}</td>
                            <td><x-badge :tone="$supplier->is_active ? 'green' : 'slate'">{{ $supplier->is_active ? __('Active') : __('Archived') }}</x-badge></td>
                            <td class="whitespace-nowrap text-right" x-on:click.stop>
                                <button type="button" wire:click="edit({{ $supplier->id }})" class="btn-ghost btn-sm" title="{{ __('Edit') }}"><x-icon name="pencil" class="size-4" /></button>
                                <button type="button" wire:click="toggleActive({{ $supplier->id }})" class="btn-ghost btn-sm" title="{{ $supplier->is_active ? __('Archive') : __('Restore') }}"><x-icon :name="$supplier->is_active ? 'eye-off' : 'eye'" class="size-4" /></button>
                            </td>
                        </tr>
                    @empty
                        <x-empty :colspan="6" icon="building" :title="__('No suppliers yet.')">
                            <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> {{ __('New supplier') }}</button>
                        </x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-table.footer :paginator="$suppliers" />
    </div>

    <x-drawer wire:model="showForm" :title="$editingId ? __('Edit supplier') : __('New supplier')">
        <form id="supplier-form" wire:submit="save" class="space-y-4">
            <x-form.field :label="__('Name')" required error="name" stacked><input wire:model="name" type="text" class="form-input"></x-form.field>
            <x-form.field :label="__('Company')" stacked><input wire:model="company" type="text" class="form-input"></x-form.field>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.field :label="__('Phone')" stacked><input wire:model="phone" type="text" class="form-input"></x-form.field>
                <x-form.field :label="__('Email')" error="email" stacked><input wire:model="email" type="email" class="form-input"></x-form.field>
            </div>
            <x-form.field :label="__('Address')" stacked><input wire:model="address" type="text" class="form-input"></x-form.field>
            <x-form.field :label="__('Notes')" stacked><textarea wire:model="notes" rows="3" class="form-input"></textarea></x-form.field>
        </form>
        @if ($editingId)
            <div class="mt-6 border-t border-slate-200 pt-5">
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('History') }}</h3>
                <x-activity-feed :subjects="\App\Models\Supplier::find($editingId)" :limit="10" />
            </div>
        @endif
        <x-slot:footer>
            <button type="button" wire:click="$set('showForm', false)" class="btn-secondary">{{ __('Cancel') }}</button>
            <button type="submit" form="supplier-form" class="btn-primary">{{ __('Save') }}</button>
        </x-slot:footer>
    </x-drawer>
</div>
