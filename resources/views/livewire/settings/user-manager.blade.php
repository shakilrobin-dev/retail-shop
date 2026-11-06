<div class="mx-auto max-w-6xl space-y-5">
    @include('settings.nav')
    <x-flash />

    <div class="flex justify-end"><button type="button" wire:click="create" class="btn-primary">+ {{ __('New user') }}</button></div>

    @if ($showForm)
        <form wire:submit="save" class="card grid gap-3 p-5 md:grid-cols-3">
            <div><label class="form-label">{{ __('Full name') }} *</label><input wire:model="name" type="text" class="form-input">@error('name') <p class="form-error">{{ $message }}</p> @enderror</div>
            <div><label class="form-label">{{ __('Username (for login)') }} *</label><input wire:model="username" type="text" class="form-input" autocomplete="off">@error('username') <p class="form-error">{{ $message }}</p> @enderror</div>
            <div><label class="form-label">{{ $editingId ? __('New password (leave empty to keep)') : __('Password') }}{{ $editingId ? '' : ' *' }}</label><input wire:model="password" type="password" class="form-input" autocomplete="new-password">@error('password') <p class="form-error">{{ $message }}</p> @enderror</div>
            <div>
                <label class="form-label">{{ __('Role') }}</label>
                <select wire:model.live="role" class="form-input">
                    @foreach ($roles as $roleName)
                        <option value="{{ $roleName }}">{{ __(ucfirst($roleName)) }}</option>
                    @endforeach
                </select>
                @error('role') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">{{ __('Branch') }}</label>
                <select wire:model="branch_id" class="form-input">
                    @if ($role === 'admin')<option value="">{{ __('All branches') }}</option>@endif
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
                @error('branch_id') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">{{ __('Language') }}</label>
                <select wire:model="locale" class="form-input"><option value="en">English</option><option value="bn">বাংলা</option></select>
            </div>
            <div><label class="form-label">{{ __('Email') }}</label><input wire:model="email" type="email" class="form-input">@error('email') <p class="form-error">{{ $message }}</p> @enderror</div>
            <div><label class="form-label">{{ __('Phone') }}</label><input wire:model="phone" type="text" class="form-input"></div>
            <label class="flex items-center gap-2 self-end pb-2 text-sm"><input wire:model="is_active" type="checkbox" class="rounded border-slate-300"> {{ __('Active (can log in)') }}</label>
            <div class="flex justify-end gap-2 md:col-span-3">
                <button type="button" wire:click="$set('showForm', false)" class="btn-secondary">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-primary">{{ __('Save') }}</button>
            </div>
        </form>
    @endif

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Username') }}</th><th>{{ __('Role') }}</th><th>{{ __('Branch') }}</th><th>{{ __('Last login') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($users as $user)
                    <tr wire:key="user-{{ $user->id }}" @class(['opacity-50' => ! $user->is_active])>
                        <td class="font-medium">{{ $user->name }}</td>
                        <td class="font-mono text-xs">{{ $user->username }}</td>
                        <td>{{ __(ucfirst($user->roles->first()?->name ?? '')) }}</td>
                        <td>{{ $user->branch?->name ?? __('All branches') }}</td>
                        <td class="text-xs text-slate-500">{{ $user->last_login_at ? shop_digits($user->last_login_at->format('d M Y h:i A')) : '—' }}</td>
                        <td class="text-right"><button type="button" wire:click="edit({{ $user->id }})" class="btn-secondary btn-sm">{{ __('Edit') }}</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
