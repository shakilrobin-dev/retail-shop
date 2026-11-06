<div class="mx-auto max-w-6xl space-y-5">
    @include('settings.nav')
    <x-flash />

    <form wire:submit="save" class="card overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('Permission') }}</th>
                    <th class="text-center">{{ __('Admin') }}</th>
                    @foreach (array_keys($matrix) as $roleName)
                        <th class="text-center">{{ __(ucfirst($roleName)) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($permissions as $permission)
                    <tr wire:key="perm-{{ $permission }}">
                        <td>{{ __('permission_labels.'.$permission) }}</td>
                        <td class="text-center text-emerald-600">✓</td>
                        @foreach (array_keys($matrix) as $roleName)
                            <td class="text-center"><input wire:model="matrix.{{ $roleName }}" type="checkbox" value="{{ $permission }}" class="rounded border-layer-line"></td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="flex justify-end border-t border-card-line p-4"><button type="submit" class="btn-primary">{{ __('Save permissions') }}</button></div>
    </form>
</div>
