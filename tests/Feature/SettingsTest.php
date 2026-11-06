<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_comes_from_config(): void
    {
        $this->assertSame('ask', app(Settings::class)->get('receipt_print_mode'));
        $this->assertFalse(app(Settings::class)->bool('vat_enabled'));
    }

    public function test_branch_value_overrides_global_value(): void
    {
        $branch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $settings = app(Settings::class);

        $settings->set('receipt_print_mode', 'on');
        $settings->set('receipt_print_mode', 'off', $branch->id);

        $this->assertSame('off', $settings->get('receipt_print_mode', $branch->id));
        $this->assertSame('on', $settings->get('receipt_print_mode', $otherBranch->id));
        $this->assertSame('on', $settings->get('receipt_print_mode'));

        $settings->forgetBranchValue('receipt_print_mode', $branch->id);

        $this->assertSame('on', $settings->get('receipt_print_mode', $branch->id));
    }

    public function test_values_keep_their_type_and_bangla_text(): void
    {
        $settings = app(Settings::class);

        $settings->set('vat_enabled', true);
        $settings->set('sms_events', ['repair_ready']);
        $settings->set('receipt_footer_bn', 'ধন্যবাদ');

        $this->assertTrue($settings->get('vat_enabled'));
        $this->assertSame(['repair_ready'], $settings->get('sms_events'));
        $this->assertSame('ধন্যবাদ', $settings->get('receipt_footer_bn'));
    }
}
