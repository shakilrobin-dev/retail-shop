<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LanguageAndBranchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guest_can_switch_login_page_to_bangla(): void
    {
        $this->from(route('login'))
            ->post(route('locale.switch'), ['locale' => 'bn'])
            ->assertRedirect(route('login'));

        $this->get(route('login'))->assertSee('লগইন করুন');
    }

    public function test_language_choice_is_saved_on_the_user(): void
    {
        $user = User::factory()->create(['locale' => 'en', 'branch_id' => Branch::factory()->create()->id]);

        $this->actingAs($user)->post(route('locale.switch'), ['locale' => 'bn']);

        $this->assertSame('bn', $user->fresh()->locale);
        $this->get('/')->assertSee('আজকের বিক্রয়');
    }

    public function test_unsupported_language_is_rejected(): void
    {
        $this->post(route('locale.switch'), ['locale' => 'fr'])->assertSessionHasErrors('locale');
    }

    public function test_admin_can_switch_branch(): void
    {
        Branch::factory()->create(['name' => 'Main Branch']);
        $second = Branch::factory()->create(['name' => 'Second Branch']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->post(route('branch.switch'), ['branch_id' => $second->id]);

        $this->assertSame($second->id, session('current_branch_id'));
    }

    public function test_branch_staff_cannot_switch_to_another_branch(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $cashier = User::factory()->create(['branch_id' => $own->id]);
        $cashier->assignRole('cashier');

        $this->actingAs($cashier)
            ->post(route('branch.switch'), ['branch_id' => $other->id])
            ->assertForbidden();
    }
}
