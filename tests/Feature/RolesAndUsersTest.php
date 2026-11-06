<?php

namespace Tests\Feature;

use App\Livewire\Settings\RolePermissions;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesAndUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_users_seeder_creates_a_user_for_every_role_and_a_second_branch(): void
    {
        $this->seed([DatabaseSeeder::class, DemoUsersSeeder::class]);

        foreach (['manager', 'cashier', 'cashier2', 'technician', 'storekeeper', 'accountant'] as $username) {
            $this->assertTrue(User::query()->where('username', $username)->exists(), $username);
        }

        $this->assertTrue(User::query()->where('username', 'technician')->sole()->hasRole('technician'));
        $this->assertSame(2, Branch::query()->count());

        $this->seed(DemoUsersSeeder::class);
        $this->assertSame(7, User::query()->count());
    }

    public function test_each_role_only_sees_its_own_pages(): void
    {
        $this->seed([DatabaseSeeder::class, DemoUsersSeeder::class]);

        $this->actingAs(User::query()->where('username', 'technician')->sole());
        $this->get(route('repairs.index'))->assertOk();
        $this->get(route('pos'))->assertForbidden();
        $this->get(route('dashboard'))->assertOk()->assertDontSee(__('Today\'s sales'))->assertSee(__('Repairs in shop'));

        $this->actingAs(User::query()->where('username', 'storekeeper')->sole());
        $this->get(route('products.index'))->assertOk();
        $this->get(route('purchases.create'))->assertOk();
        $this->get(route('reports.index'))->assertForbidden();

        $this->actingAs(User::query()->where('username', 'accountant')->sole());
        $this->get(route('reports.index'))->assertOk();
        $this->get(route('expenses.index'))->assertOk();
        $this->get(route('settings.users'))->assertForbidden();
    }

    public function test_admin_creates_role_copying_permissions_and_deletes_unused_role(): void
    {
        $this->actingAsRole('admin');

        Livewire::test(RolePermissions::class)
            ->set('newRole', 'Senior Cashier')
            ->set('copyFrom', 'cashier')
            ->call('createRole')
            ->assertHasNoErrors()
            ->assertSet('matrix.senior cashier', Role::findByName('cashier')->permissions->pluck('name')->all());

        $this->assertTrue(Role::findByName('senior cashier')->hasPermissionTo('pos.sell'));

        Livewire::test(RolePermissions::class)->call('deleteRole', 'senior cashier');
        $this->assertFalse(Role::query()->where('name', 'senior cashier')->exists());
    }

    public function test_role_in_use_and_admin_role_cannot_be_deleted(): void
    {
        $this->actingAsRole('admin');
        User::factory()->create()->assignRole('cashier');

        Livewire::test(RolePermissions::class)
            ->call('deleteRole', 'cashier')
            ->assertDispatched('toast')
            ->call('deleteRole', 'admin');

        $this->assertTrue(Role::query()->where('name', 'cashier')->exists());
        $this->assertTrue(Role::query()->where('name', 'admin')->exists());
    }

    public function test_delete_buttons_ask_for_confirmation(): void
    {
        $this->actingAsRole('admin');

        $this->get(route('settings.roles'))->assertOk()->assertSee('data-confirm=', false);
        $this->get(route('settings.users'))->assertOk()->assertSee('confirm-dialog', false);
    }
}
