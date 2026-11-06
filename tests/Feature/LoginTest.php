<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guest_is_sent_to_login_page(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertSee(__('Log in'));
    }

    public function test_user_can_log_in_with_username(): void
    {
        $user = User::factory()->create(['username' => 'cashier1', 'password' => 'secret123']);

        Livewire::test(Login::class)
            ->set('username', 'cashier1')
            ->set('password', 'secret123')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_user_can_log_in_with_email(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com', 'password' => 'secret123']);

        Livewire::test(Login::class)
            ->set('username', 'owner@example.com')
            ->set('password', 'secret123')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['username' => 'cashier1', 'password' => 'secret123']);

        Livewire::test(Login::class)
            ->set('username', 'cashier1')
            ->set('password', 'wrong')
            ->call('login')
            ->assertHasErrors('username');

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        User::factory()->create(['username' => 'oldstaff', 'password' => 'secret123', 'is_active' => false]);

        Livewire::test(Login::class)
            ->set('username', 'oldstaff')
            ->set('password', 'secret123')
            ->call('login')
            ->assertHasErrors('username');

        $this->assertGuest();
    }

    public function test_logged_in_user_sees_dashboard_and_can_log_out(): void
    {
        $user = User::factory()->create(['branch_id' => Branch::factory()->create()->id]);
        $user->assignRole('cashier');

        $this->actingAs($user)->get('/')->assertOk()->assertSee(__('Today\'s sales'));

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
