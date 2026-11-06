<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopSetupSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_branch_admin_and_roles(): void
    {
        $this->seed();

        $this->assertSame('B1', Branch::query()->sole()->code);

        $admin = User::query()->where('username', 'admin')->sole();
        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue($admin->can('settings.manage'));
        $this->assertTrue($admin->canAccessAllBranches());

        $this->assertTrue(PaymentMethod::query()->where('name', 'bKash')->exists());
    }

    public function test_seeder_can_run_twice_without_duplicates(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(1, Branch::query()->count());
        $this->assertSame(1, User::query()->count());
    }
}
