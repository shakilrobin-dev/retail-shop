<div class="mx-auto max-w-6xl space-y-5">
    @include('settings.nav')
    <x-flash />

    <form wire:submit="save" class="card flex flex-wrap items-end gap-2 p-5">
        <div><label class="form-label">{{ __('Name') }}</label><input wire:model="name" type="text" class="form-input" placeholder="{{ __('e.g. Upay') }}">@error('name') <p class="form-error">{{ $message }}</p> @enderror</div>
        <div><label class="form-label">{{ __('Name (Bangla)') }}</label><input wire:model="name_bn" type="text" class="form-input"></div>
        <div>
            <label class="form-label">{{ __('Type') }}</label>
            <select wire:model="type" class="form-input">
                @foreach (\App\Enums\PaymentMethodType::cases() as $case)
                    <option value="{{ $case->value }}">{{ $case->label() }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-primary">{{ $editingId ? __('Update') : __('Add') }}</button>
        @if ($editingId)<button type="button" wire:click="$set('editingId', null)" class="btn-secondary">{{ __('Cancel') }}</button>@endif
    </form>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>{{ __('Order') }}</th><th>{{ __('Name') }}</th><th>{{ __('Type') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($methods as $method)
                    <tr wire:key="pm-{{ $method->id }}" @class(['opacity-50' => ! $method->is_active])>
                        <td class="whitespace-nowrap">
                            <button type="button" wire:click="move({{ $method->id }}, -1)" class="btn-secondary btn-sm">↑</button>
                            <button type="button" wire:click="move({{ $method->id }}, 1)" class="btn-secondary btn-sm">↓</button>
                        </td>
                        <td>{{ $method->name }} @if ($method->name_bn)<span class="text-muted-foreground-1">· {{ $method->name_bn }}</span>@endif</td>
                        <td>{{ $method->type->label() }}</td>
                        <td class="whitespace-nowrap text-right">
                            <button type="button" wire:click="edit({{ $method->id }})" class="btn-secondary btn-sm">{{ __('Edit') }}</button>
                            <button type="button" wire:click="toggle({{ $method->id }})" class="btn-secondary btn-sm">{{ $method->is_active ? __('Deactivate') : __('Activate') }}</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
