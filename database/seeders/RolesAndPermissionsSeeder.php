<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * All permissions in the system. Admin can change which role has which.
     *
     * @var list<string>
     */
    public const PERMISSIONS = [
        'settings.manage',
        'branches.manage',
        'branches.view-all',
        'users.manage',
        'products.manage',
        'products.view-cost',
        'purchases.manage',
        'stock.adjust',
        'stock.transfer',
        'pos.sell',
        'pos.credit-sale',
        'pos.discount',
        'pos.edit-price',
        'pos.open-drawer',
        'sales.return',
        'sales.void',
        'customers.collect-due',
        'commissions.manage',
        'repairs.manage',
        'expenses.manage',
        'cash-shifts.manage',
        'reports.view',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $existing = Permission::query()->pluck('name')->all();
        $added = array_values(array_diff(self::PERMISSIONS, $existing));

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission);
        }

        /** Seeders run without model events, so the permission cache must be cleared by hand. */
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('admin')->syncPermissions(self::PERMISSIONS);

        foreach (self::defaultRoles() as $name => $permissions) {
            $this->createWithPermissions($name, $permissions);
            $this->giveNewPermissions($name, array_intersect($permissions, $added));
        }
    }

    /**
     * A permission added in a newer version goes to the existing default roles
     * that should have it. Nothing the admin set before is changed.
     *
     * @param  array<int, string>  $permissions
     */
    private function giveNewPermissions(string $role, array $permissions): void
    {
        if ($permissions === []) {
            return;
        }

        Role::findByName($role)->givePermissionTo($permissions);
    }

    /**
     * Ready-made roles. Admin can change them later in Settings → Permissions.
     *
     * @return array<string, list<string>>
     */
    public static function defaultRoles(): array
    {
        return [
            'manager' => [
                'products.manage', 'products.view-cost', 'purchases.manage', 'stock.adjust', 'stock.transfer',
                'pos.sell', 'pos.credit-sale', 'pos.discount', 'pos.open-drawer', 'sales.return', 'customers.collect-due', 'repairs.manage',
                'expenses.manage', 'cash-shifts.manage', 'reports.view', 'commissions.manage',
            ],
            'cashier' => ['pos.sell', 'pos.credit-sale', 'pos.discount', 'customers.collect-due', 'repairs.manage', 'cash-shifts.manage'],
            'technician' => ['repairs.manage'],
            'storekeeper' => ['products.manage', 'products.view-cost', 'purchases.manage', 'stock.adjust', 'stock.transfer'],
            'accountant' => ['reports.view', 'expenses.manage', 'products.view-cost', 'cash-shifts.manage', 'purchases.manage', 'commissions.manage'],
        ];
    }

    /**
     * Create a role with its default permissions. An existing role is left as it is,
     * so changes made by the admin are not overwritten.
     *
     * @param  list<string>  $permissions
     */
    private function createWithPermissions(string $name, array $permissions): void
    {
        if (Role::query()->where('name', $name)->where('guard_name', 'web')->exists()) {
            return;
        }

        Role::create(['name' => $name, 'guard_name' => 'web'])->syncPermissions($permissions);
    }
}
