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
        'pos.discount',
        'pos.edit-price',
        'pos.open-drawer',
        'sales.return',
        'sales.void',
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

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission);
        }

        /** Seeders run without model events, so the permission cache must be cleared by hand. */
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('admin')->syncPermissions(self::PERMISSIONS);

        Role::findOrCreate('manager')->syncPermissions([
            'products.manage',
            'products.view-cost',
            'purchases.manage',
            'stock.adjust',
            'stock.transfer',
            'pos.sell',
            'pos.discount',
            'pos.open-drawer',
            'sales.return',
            'repairs.manage',
            'expenses.manage',
            'cash-shifts.manage',
            'reports.view',
        ]);

        Role::findOrCreate('cashier')->syncPermissions([
            'pos.sell',
            'pos.discount',
            'repairs.manage',
            'cash-shifts.manage',
        ]);
    }
}
