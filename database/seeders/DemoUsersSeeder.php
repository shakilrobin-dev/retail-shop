<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Test users for each role plus a second branch (for trying transfers and branch rules).
 * Not run by default:
 *
 *   php artisan db:seed --class=DemoUsersSeeder
 *
 * All test users use the password below. Change or delete them before going live.
 */
class DemoUsersSeeder extends Seeder
{
    public const PASSWORD = 'password';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $main = Branch::query()->orderBy('id')->firstOrFail();
        $main->update([
            'address' => $main->address ?? 'Shop 12, Level 3, Bashundhara City, Dhaka',
            'phone' => $main->phone ?? '01711-000000',
        ]);

        $second = Branch::query()->firstOrCreate(['code' => 'B2'], [
            'name' => 'Mirpur Branch',
            'name_bn' => 'মিরপুর শাখা',
            'address' => 'Mirpur 10 Circle, Dhaka',
            'phone' => '01811-000000',
        ]);

        $users = [
            ['manager', 'Manager Hasan', 'manager', $main, 'en'],
            ['cashier', 'Cashier Rina', 'cashier', $main, 'bn'],
            ['cashier2', 'Cashier Sohel (Mirpur)', 'cashier', $second, 'bn'],
            ['technician', 'Technician Rafiq', 'technician', $main, 'bn'],
            ['storekeeper', 'Store keeper Kamal', 'storekeeper', $main, 'en'],
            ['accountant', 'Accountant Nasrin', 'accountant', $main, 'en'],
        ];

        foreach ($users as [$username, $name, $role, $branch, $locale]) {
            $user = User::query()->firstOrCreate(['username' => $username], [
                'name' => $name,
                'password' => self::PASSWORD,
                'branch_id' => $branch->id,
                'locale' => $locale,
            ]);
            $user->syncRoles([$role]);

            if ($role === 'technician') {
                Technician::query()->firstOrCreate(['user_id' => $user->id], [
                    'branch_id' => $branch->id,
                    'name' => $name,
                ]);
            }
        }
    }
}
