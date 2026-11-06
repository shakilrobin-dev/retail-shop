<div class="space-y-5">
    @include('settings.nav')
    <x-flash />

    <div class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-card-divider px-4 py-3">
            <p class="text-sm text-muted-foreground-1">{{ __('Each branch has its own stock, cash register and invoice numbers.') }}</p>
            <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> {{ __('New branch') }}</button>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>{{ __('Branch') }}</th><th>{{ __('Code') }}</th><th>{{ __('Address') }}</th><th>{{ __('Phone') }}</th><th class="text-right">{{ __('Users') }}</th><th>{{ __('Status') }}</th><th class="w-12"></th></tr></thead>
                <tbody>
                    @foreach ($branches as $branch)
                        <tr wire:key="branch-{{ $branch->id }}" wire:click="edit({{ $branch->id }})" class="cursor-pointer">
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="flex size-8 items-center justify-center rounded-lg bg-primary-50 text-primary-600"><x-icon name="building" class="size-4" /></span>
                                    <div><div class="font-medium text-foreground">{{ $branch->name }}</div>@if ($branch->name_bn)<div class="text-xs text-muted-foreground-1">{{ $branch->name_bn }}</div>@endif</div>
                                </div>
                            </td>
                            <td class="font-mono text-xs">{{ $branch->code }}</td>
                            <td class="max-w-64 truncate text-muted-foreground-2">{{ $branch->address ?? '—' }}</td>
                            <td class="text-muted-foreground-2">{{ $branch->phone ?? '—' }}</td>
                            <td class="text-right tabular-nums">{{ shop_digits($branch->users_count) }}</td>
                            <td><x-badge :tone="$branch->is_active ? 'green' : 'slate'">{{ $branch->is_active ? __('Active') : __('Closed') }}</x-badge></td>
                            <td class="text-right"><x-icon name="chevron-right" class="size-4 text-muted-foreground" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <x-drawer wire:model="showForm" :title="$editingId ? __('Edit branch') : __('New branch')" :subtitle="__('Address and phone are printed on receipts.')">
        <form id="branch-form" wire:submit="save" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-[8rem_1fr]">
                <x-form.field :label="__('Code')" required error="code" :hint="__('Used in invoice no')" stacked><input wire:model="code" type="text" class="form-input font-mono uppercase"></x-form.field>
                <x-form.field :label="__('Name (English)')" required error="name" stacked><input wire:model="name" type="text" class="form-input"></x-form.field>
            </div>
            <x-form.field :label="__('Name (Bangla)')" stacked><input wire:model="name_bn" type="text" class="form-input"></x-form.field>
            <x-form.field :label="__('Address (English)')" stacked><input wire:model="address" type="text" class="form-input"></x-form.field>
            <x-form.field :label="__('Address (Bangla)')" stacked><input wire:model="address_bn" type="text" class="form-input"></x-form.field>
            <x-form.field :label="__('Phone')" stacked><input wire:model="phone" type="text" class="form-input"></x-form.field>
            <label class="flex items-center gap-2 text-sm"><input wire:model="is_active" type="checkbox" class="rounded border-layer-line"> {{ __('Active') }}</label>
            @error('is_active') <p class="form-error">{{ $message }}</p> @enderror
        </form>
        <x-slot:footer>
            <button type="button" wire:click="$set('showForm', false)" class="btn-secondary">{{ __('Cancel') }}</button>
            <button type="submit" form="branch-form" class="btn-primary">{{ __('Save') }}</button>
        </x-slot:footer>
    </x-drawer>
</div>
