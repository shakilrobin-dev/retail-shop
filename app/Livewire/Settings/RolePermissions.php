<?php

namespace App\Livewire\Settings;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tick which role can do what. Admin always has everything.
 */
class RolePermissions extends Component
{
    /** @var array<string, list<string>> role name => permission names */
    public array $matrix = [];

    public string $newRole = '';

    public string $copyFrom = '';

    public function mount(): void
    {
        $this->authorize('users.manage');

        foreach (Role::query()->with('permissions')->where('name', '!=', 'admin')->get() as $role) {
            $this->matrix[$role->name] = $role->permissions->pluck('name')->values()->all();
        }
    }

    public function createRole(): void
    {
        $this->authorize('users.manage');
        $this->newRole = mb_strtolower(trim($this->newRole));

        $this->validate([
            'newRole' => ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9 _-]*$/', 'unique:roles,name'],
            'copyFrom' => ['nullable', 'exists:roles,name'],
        ], [], ['newRole' => __('role name')]);

        $permissions = $this->copyFrom ? Role::findByName($this->copyFrom)->permissions->pluck('name')->all() : [];
        Role::create(['name' => $this->newRole, 'guard_name' => 'web'])->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->matrix[$this->newRole] = $permissions;
        $this->dispatch('toast', message: __('Role ":role" created. Tick its permissions and save.', ['role' => $this->newRole]));
        $this->reset('newRole', 'copyFrom');
    }

    public function deleteRole(string $name): void
    {
        $this->authorize('users.manage');
        $role = Role::findByName($name);

        if ($name === 'admin') {
            $this->dispatch('toast', type: 'error', message: __('The admin role cannot be deleted.'));

            return;
        }

        if ($role->users()->exists()) {
            $this->dispatch('toast', type: 'error', message: __('Some users have this role. Give them another role first.'));

            return;
        }

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        unset($this->matrix[$name]);
        $this->dispatch('toast', message: __('Role ":role" deleted.', ['role' => $name]));
    }

    public function save(): void
    {
        $this->authorize('users.manage');

        foreach ($this->matrix as $roleName => $permissions) {
            $allowed = array_values(array_intersect($permissions, RolesAndPermissionsSeeder::PERMISSIONS));
            Role::findByName($roleName)->syncPermissions($allowed);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->dispatch('toast', message: __('Permissions saved.'));
    }

    public function render(): View
    {
        return view('livewire.settings.role-permissions', [
            'permissions' => RolesAndPermissionsSeeder::PERMISSIONS,
            'userCounts' => Role::query()->withCount('users')->pluck('users_count', 'name'),
        ])->title(__('Permissions'));
    }
}
