<div class="mx-auto max-w-6xl space-y-5">
    @include('settings.nav')
    <x-flash />

    <div class="flex justify-end"><button type="button" wire:click="create" class="btn-primary">+ {{ __('New branch') }}</button></div>

    @if ($showForm)
        <form wire:submit="save" class="card grid gap-3 p-5 md:grid-cols-3">
            <div><label class="form-label">{{ __('Code (used in invoice no)') }} *</label><input wire:model="code" type="text" class="form-input uppercase">@error('code') <p class="form-error">{{ $message }}</p> @enderror</div>
            <div><label class="form-label">{{ __('Name (English)') }} *</label><input wire:model="name" type="text" class="form-input">@error('name') <p class="form-error">{{ $message }}</p> @enderror</div>
            <div><label class="form-label">{{ __('Name (Bangla)') }}</label><input wire:model="name_bn" type="text" class="form-input"></div>
            <div><label class="form-label">{{ __('Address (English)') }}</label><input wire:model="address" type="text" class="form-input"></div>
            <div><label class="form-label">{{ __('Address (Bangla)') }}</label><input wire:model="address_bn" type="text" class="form-input"></div>
            <div><label class="form-label">{{ __('Phone') }}</label><input wire:model="phone" type="text" class="form-input"></div>
            <label class="flex items-center gap-2 text-sm"><input wire:model="is_active" type="checkbox" class="rounded border-slate-300"> {{ __('Active') }}</label>
            @error('is_active') <p class="form-error md:col-span-2">{{ $message }}</p> @enderror
            <div class="flex justify-end gap-2 md:col-span-3">
                <button type="button" wire:click="$set('showForm', false)" class="btn-secondary">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-primary">{{ __('Save') }}</button>
            </div>
        </form>
    @endif

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Address') }}</th><th>{{ __('Phone') }}</th><th class="text-right">{{ __('Users') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($branches as $branch)
                    <tr wire:key="branch-{{ $branch->id }}" @class(['opacity-50' => ! $branch->is_active])>
                        <td class="font-mono">{{ $branch->code }}</td>
                        <td>{{ $branch->name }}@if ($branch->name_bn)<div class="text-xs text-slate-500">{{ $branch->name_bn }}</div>@endif</td>
                        <td class="text-slate-600">{{ $branch->address }}</td>
                        <td>{{ $branch->phone }}</td>
                        <td class="text-right">{{ shop_digits($branch->users_count) }}</td>
                        <td class="text-right"><button type="button" wire:click="edit({{ $branch->id }})" class="btn-secondary btn-sm">{{ __('Edit') }}</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
