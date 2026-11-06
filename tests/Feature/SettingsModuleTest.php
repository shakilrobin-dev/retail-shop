<?php

namespace Tests\Feature;

use App\Livewire\Settings\BackupManager;
use App\Livewire\Settings\BranchManager;
use App\Livewire\Settings\GeneralSettings;
use App\Livewire\Settings\PaymentMethodManager;
use App\Livewire\Settings\RolePermissions;
use App\Livewire\Settings\UserManager;
use App\Models\Branch;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\BackupService;
use App\Services\Settings;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SettingsModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_turns_receipt_printing_off_and_vat_on(): void
    {
        $this->actingAsRole('admin');

        Livewire::test(GeneralSettings::class)
            ->set('tab', 'receipt')
            ->set('values.receipt_print_mode', 'off')
            ->call('save')
            ->assertHasNoErrors()
            ->set('tab', 'pos')
            ->set('values.vat_enabled', true)
            ->set('values.vat_default_rate', '7.5')
            ->call('save')
            ->assertHasNoErrors();

        $settings = app(Settings::class);
        $this->assertSame('off', $settings->get('receipt_print_mode'));
        $this->assertTrue($settings->get('vat_enabled'));
        $this->assertSame(7.5, $settings->get('vat_default_rate'));
    }

    public function test_invalid_setting_is_rejected(): void
    {
        $this->actingAsRole('admin');

        Livewire::test(GeneralSettings::class)
            ->set('tab', 'receipt')
            ->set('values.receipt_print_mode', 'sometimes')
            ->call('save')
            ->assertHasErrors('values.receipt_print_mode');
    }

    public function test_cashier_cannot_open_settings(): void
    {
        $this->actingAsRole('cashier');

        $this->get(route('settings.general'))->assertForbidden();
        $this->get(route('settings.users'))->assertForbidden();
    }

    public function test_admin_adds_branch_and_cashier_user(): void
    {
        $this->actingAsRole('admin');

        Livewire::test(BranchManager::class)
            ->call('create')
            ->set('code', 'b2')
            ->set('name', 'Mirpur Branch')
            ->call('save')
            ->assertHasNoErrors();

        $branch = Branch::query()->where('code', 'B2')->sole();

        Livewire::test(UserManager::class)
            ->call('create')
            ->set('name', 'Sumon')
            ->set('username', 'sumon')
            ->set('password', 'secret123')
            ->set('role', 'cashier')
            ->set('branch_id', (string) $branch->id)
            ->call('save')
            ->assertHasNoErrors();

        $user = User::query()->where('username', 'sumon')->sole();
        $this->assertTrue($user->hasRole('cashier'));
        $this->assertSame($branch->id, $user->branch_id);
    }

    public function test_admin_cannot_remove_own_admin_role(): void
    {
        $admin = $this->actingAsRole('admin');

        Livewire::test(UserManager::class)
            ->call('edit', $admin->id)
            ->set('role', 'cashier')
            ->call('save')
            ->assertHasErrors('role');

        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }

    public function test_permissions_can_be_given_to_cashier(): void
    {
        $this->actingAsRole('admin');

        Livewire::test(RolePermissions::class)
            ->set('matrix.cashier', ['pos.sell', 'sales.return'])
            ->call('save');

        $this->assertTrue(Role::findByName('cashier')->hasPermissionTo('sales.return'));
        $this->assertFalse(Role::findByName('cashier')->hasPermissionTo('repairs.manage'));
    }

    public function test_payment_method_can_be_added(): void
    {
        $this->actingAsRole('admin');

        Livewire::test(PaymentMethodManager::class)
            ->set('name', 'Upay')
            ->set('type', 'mobile_banking')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('payment_methods', ['name' => 'Upay', 'type' => 'mobile_banking']);
    }

    public function test_backup_is_created_and_listed(): void
    {
        Storage::fake('local');
        $this->actingAsRole('admin');

        Livewire::test(BackupManager::class)->call('backupNow')->assertHasNoErrors();

        $backup = app(BackupService::class)->list()->sole();
        $content = gzdecode(file_get_contents(app(BackupService::class)->path($backup['name'])));
        $this->assertStringContainsString('INSERT INTO `users`', $content);
    }

    public function test_sms_uses_gateway_url_and_logs(): void
    {
        Http::fake(['sms.example.com/*' => Http::response('OK')]);
        $settings = app(Settings::class);
        $settings->set('sms_enabled', true);
        $settings->set('sms_api_url', 'https://sms.example.com/send?key={api_key}&to={phone}&msg={message}');
        $settings->set('sms_api_key', 'ABC');

        $log = app(SmsService::class)->send('01711000000', 'Hello', 'test');

        $this->assertSame('sent', $log->status);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'to=8801711000000') && str_contains($request->url(), 'key=ABC'));
    }

    public function test_sms_is_skipped_when_off(): void
    {
        Http::fake();

        app(SmsService::class)->send('01711000000', 'Hello', 'test');

        $this->assertSame('skipped', SmsLog::query()->sole()->status);
        Http::assertNothingSent();
    }

    public function test_settings_pages_render(): void
    {
        $this->actingAsRole('admin');

        foreach (GeneralSettings::TABS as $tab) {
            $this->get(route('settings.general', ['tab' => $tab]))->assertOk();
        }

        foreach (['settings.branches', 'settings.users', 'settings.roles', 'settings.payment-methods', 'settings.backup'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }
}
