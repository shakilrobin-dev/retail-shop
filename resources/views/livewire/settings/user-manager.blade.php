<div class="space-y-5">
    @include('settings.nav')
    <x-flash />

    <div class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <p class="text-sm text-slate-500">{{ __('People who can log in. Each user works in one branch; admins can see all branches.') }}</p>
            <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> {{ __('New user') }}</button>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>{{ __('User') }}</th><th>{{ __('Role') }}</th><th>{{ __('Branch') }}</th><th>{{ __('Language') }}</th><th>{{ __('Last login') }}</th><th>{{ __('Status') }}</th><th class="w-12"></th></tr></thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr wire:key="user-{{ $user->id }}" wire:click="edit({{ $user->id }})" class="cursor-pointer">
                            <td>
                                <div class="flex items-center gap-3">
                                    <x-avatar :name="$user->name" />
                                    <div><div class="font-medium text-slate-900">{{ $user->name }}</div><div class="font-mono text-xs text-slate-500">{{ $user->username }}</div></div>
                                </div>
                            </td>
                            <td><x-badge :tone="$user->roles->first()?->name === 'admin' ? 'brand' : 'slate'">{{ __(ucfirst($user->roles->first()?->name ?? '')) }}</x-badge></td>
                            <td class="text-slate-600">{{ $user->branch?->localName() ?? __('All branches') }}</td>
                            <td class="text-slate-600">{{ $user->locale === 'bn' ? 'বাংলা' : 'English' }}</td>
                            <td class="text-xs text-slate-500">{{ $user->last_login_at ? shop_digits($user->last_login_at->diffForHumans()) : __('Never') }}</td>
                            <td><x-badge :tone="$user->is_active ? 'green' : 'slate'">{{ $user->is_active ? __('Active') : __('Disabled') }}</x-badge></td>
                            <td class="text-right"><x-icon name="chevron-right" class="size-4 text-slate-300" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <x-drawer wire:model="showForm" :title="$editingId ? __('Edit user') : __('New user')" :subtitle="__('Login details, role and branch.')">
        <form id="user-form" wire:submit="save" class="space-y-4">
            <x-form.field :label="__('Full name')" required error="name" stacked><input wire:model="name" type="text" class="form-input"></x-form.field>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.field :label="__('Username (for login)')" required error="username" stacked><input wire:model="username" type="text" class="form-input font-mono" autocomplete="off"></x-form.field>
                <x-form.field :label="$editingId ? __('New password') : __('Password')" :required="! $editingId" error="password" :hint="$editingId ? __('Leave empty to keep the current password.') : null" stacked>
                    <input wire:model="password" type="password" class="form-input" autocomplete="new-password">
                </x-form.field>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.field :label="__('Role')" error="role" stacked>
                    <select wire:model.live="role" class="form-input">
                        @foreach ($roles as $roleName)
                            <option value="{{ $roleName }}">{{ __(ucfirst($roleName)) }}</option>
                        @endforeach
                    </select>
                </x-form.field>
                <x-form.field :label="__('Branch')" error="branch_id" stacked>
                    <select wire:model="branch_id" class="form-input">
                        @if ($role === 'admin')<option value="">{{ __('All branches') }}</option>@endif
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->localName() }}</option>
                        @endforeach
                    </select>
                </x-form.field>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.field :label="__('Email')" error="email" stacked><input wire:model="email" type="email" class="form-input"></x-form.field>
                <x-form.field :label="__('Phone')" stacked><input wire:model="phone" type="text" class="form-input"></x-form.field>
            </div>
            <x-form.field :label="__('Language')" stacked>
                <select wire:model="locale" class="form-input"><option value="en">English</option><option value="bn">বাংলা</option></select>
            </x-form.field>
            <label class="flex items-center gap-2 text-sm"><input wire:model="is_active" type="checkbox" class="rounded border-slate-300"> {{ __('Active (can log in)') }}</label>
        </form>
        @if ($editingId)
            <div class="mt-6 border-t border-slate-200 pt-5">
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('History') }}</h3>
                <x-activity-feed :subjects="\App\Models\User::find($editingId)" :limit="10" />
            </div>
        @endif
        <x-slot:footer>
            <button type="button" wire:click="$set('showForm', false)" class="btn-secondary">{{ __('Cancel') }}</button>
            <button type="submit" form="user-form" class="btn-primary">{{ __('Save') }}</button>
        </x-slot:footer>
    </x-drawer>
</div>
