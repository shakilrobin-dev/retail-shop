<?php

namespace App\Livewire\Settings;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class UserManager extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $username = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public string $role = 'cashier';

    /** Empty = all branches (owner) */
    public string $branch_id = '';

    public string $locale = 'en';

    public bool $is_active = true;

    public function mount(): void
    {
        $this->authorize('users.manage');
    }

    public function create(): void
    {
        $this->reset('editingId', 'name', 'username', 'email', 'phone', 'password');
        $this->role = 'cashier';
        $this->branch_id = (string) Branch::query()->where('is_active', true)->orderBy('id')->value('id');
        $this->is_active = true;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $user = User::query()->findOrFail($id);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->username = $user->username;
        $this->email = (string) $user->email;
        $this->phone = (string) $user->phone;
        $this->password = '';
        $this->role = $user->getRoleNames()->first() ?? 'cashier';
        $this->branch_id = (string) $user->branch_id;
        $this->locale = $user->locale;
        $this->is_active = $user->is_active;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('users.manage');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'alpha_dash', 'max:50', Rule::unique('users', 'username')->ignore($this->editingId)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => [$this->editingId ? 'nullable' : 'required', Password::min(6)],
            'role' => ['required', 'exists:roles,name'],
            'branch_id' => ['nullable', Rule::requiredIf($this->role !== 'admin'), 'exists:branches,id'],
            'locale' => ['required', 'in:en,bn'],
            'is_active' => ['boolean'],
        ], [], ['branch_id' => __('branch')]);

        if ($this->editingId === Auth::id() && (! $validated['is_active'] || $validated['role'] !== 'admin')) {
            $this->addError('role', __('You cannot remove your own admin access.'));

            return;
        }

        $attributes = [
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'branch_id' => $validated['branch_id'] ?: null,
            'locale' => $validated['locale'],
            'is_active' => $validated['is_active'],
        ];

        if ($validated['password']) {
            $attributes['password'] = $validated['password'];
        }

        $user = User::query()->updateOrCreate(['id' => $this->editingId], $attributes);
        $user->syncRoles([$validated['role']]);

        session()->flash('success', __('User saved.'));
        $this->reset('showForm', 'editingId', 'password');
    }

    public function render(): View
    {
        return view('livewire.settings.user-manager', [
            'users' => User::query()->with(['branch', 'roles'])->orderBy('name')->get(),
            'roles' => Role::query()->orderBy('id')->pluck('name'),
            'branches' => Branch::query()->orderBy('id')->get(),
        ])->title(__('Users'));
    }
}
