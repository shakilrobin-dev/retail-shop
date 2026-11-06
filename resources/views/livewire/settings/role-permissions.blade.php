<div class="space-y-5">
    @include('settings.nav')
    <x-flash />

    <div class="card">
        <form wire:submit="createRole" class="flex flex-wrap items-end gap-3 p-4">
            <div>
                <label class="form-label">{{ __('New role') }}</label>
                <input wire:model="newRole" type="text" placeholder="{{ __('e.g. senior cashier') }}" class="form-input w-56">
                @error('newRole') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">{{ __('Copy permissions from') }}</label>
                <select wire:model="copyFrom" class="form-input w-48">
                    <option value="">{{ __('Start empty') }}</option>
                    @foreach (array_keys($matrix) as $roleName)
                        <option value="{{ $roleName }}">{{ __(ucfirst($roleName)) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-secondary"><x-icon name="plus" class="size-4" /> {{ __('Create role') }}</button>
            <p class="ms-auto max-w-md text-xs text-muted-foreground-1">{{ __('Tick what each role can do, then save. Admin always has every permission.') }}</p>
        </form>
    </div>

    <form wire:submit="save" class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="min-w-64">{{ __('Permission') }}</th>
                        <th class="text-center">{{ __('Admin') }}<div class="font-normal text-muted-foreground">{{ trans_choice(':count user|:count users', $userCounts['admin'] ?? 0, ['count' => shop_digits($userCounts['admin'] ?? 0)]) }}</div></th>
                        @foreach (array_keys($matrix) as $roleName)
                            <th class="text-center" wire:key="role-head-{{ $roleName }}">
                                <div class="flex items-center justify-center gap-1">
                                    {{ __(ucfirst($roleName)) }}
                                    <button type="button" wire:click="deleteRole('{{ $roleName }}')" data-confirm="{{ __('Delete the role ":role"?', ['role' => $roleName]) }}" data-confirm-button="{{ __('Delete') }}" class="rounded p-0.5 text-muted-foreground hover:bg-red-50 hover:text-red-600" title="{{ __('Delete role') }}"><x-icon name="close" class="size-3" /></button>
                                </div>
                                <div class="font-normal text-muted-foreground">{{ trans_choice(':count user|:count users', $userCounts[$roleName] ?? 0, ['count' => shop_digits($userCounts[$roleName] ?? 0)]) }}</div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($permissions as $permission)
                        <tr wire:key="perm-{{ $permission }}">
                            <td>{{ __('permission_labels.'.$permission) }}</td>
                            <td class="text-center text-emerald-600">✓</td>
                            @foreach (array_keys($matrix) as $roleName)
                                <td class="text-center"><input wire:model="matrix.{{ $roleName }}" type="checkbox" value="{{ $permission }}" class="rounded border-layer-line text-primary"></td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="flex justify-end border-t border-card-line p-3"><button type="submit" class="btn-primary">{{ __('Save permissions') }}</button></div>
    </form>
</div>
