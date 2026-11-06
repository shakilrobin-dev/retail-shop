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

    public function mount(): void
    {
        $this->authorize('users.manage');

        foreach (Role::query()->with('permissions')->where('name', '!=', 'admin')->get() as $role) {
            $this->matrix[$role->name] = $role->permissions->pluck('name')->values()->all();
        }
    }

    public function save(): void
    {
        $this->authorize('users.manage');

        foreach ($this->matrix as $roleName => $permissions) {
            $allowed = array_values(array_intersect($permissions, RolesAndPermissionsSeeder::PERMISSIONS));
            Role::findByName($roleName)->syncPermissions($allowed);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        session()->flash('success', __('Permissions saved.'));
    }

    public function render(): View
    {
        return view('livewire.settings.role-permissions', [
            'permissions' => RolesAndPermissionsSeeder::PERMISSIONS,
        ])->title(__('Permissions'));
    }
}
