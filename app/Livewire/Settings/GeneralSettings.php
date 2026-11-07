<?php

namespace App\Livewire\Settings;

use App\Models\Technician;
use App\Services\CurrentBranch;
use App\Services\Settings;
use App\Services\SmsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Shop-wide settings in tabs. Values are saved globally.
 */
class GeneralSettings extends Component
{
    use WithFileUploads;

    public const TABS = ['shop', 'receipt', 'labels', 'pos', 'repair', 'reports', 'sms'];

    /** Which keys belong to which tab, and their type. */
    public const FIELDS = [
        'shop' => [
            'shop_name' => 'string', 'shop_name_bn' => 'string', 'shop_phone' => 'string',
            'currency_symbol' => 'string', 'default_locale' => 'string', 'digits' => 'string',
        ],
        'receipt' => [
            'receipt_print_mode' => 'string', 'receipt_paper' => 'string', 'receipt_language' => 'string',
            'receipt_show_logo' => 'bool', 'receipt_header' => 'string', 'receipt_header_bn' => 'string',
            'receipt_footer' => 'string', 'receipt_footer_bn' => 'string', 'return_policy_days' => 'int',
            'cash_drawer_enabled' => 'bool',
        ],
        'labels' => [
            'barcode_prefix' => 'string', 'label_output' => 'string', 'label_size' => 'string',
            'label_a4_layout' => 'string', 'label_show_shop_name' => 'bool', 'label_show_price' => 'bool',
        ],
        'pos' => [
            'vat_enabled' => 'bool', 'vat_default_rate' => 'float', 'vat_inclusive' => 'bool', 'vat_bin' => 'string',
            'pos_allow_price_edit' => 'bool', 'pos_max_discount_percent' => 'float', 'pos_allow_negative_stock' => 'bool',
        ],
        'repair' => [
            'repair_default_warranty_days' => 'int', 'repair_terms' => 'string', 'repair_terms_bn' => 'string',
            'technicians_enabled' => 'bool',
        ],
        'reports' => [
            'report_tagline' => 'string', 'report_footer_note' => 'string', 'report_show_signatures' => 'bool',
        ],
        'sms' => [
            'sms_enabled' => 'bool', 'sms_api_url' => 'string', 'sms_api_key' => 'string', 'sms_sender_id' => 'string',
            'sms_events' => 'array',
        ],
    ];

    #[Url]
    public string $tab = 'shop';

    /** @var array<string, mixed> */
    public array $values = [];

    public $logo;

    public string $technicianName = '';

    public string $technicianPhone = '';

    public string $testPhone = '';

    public function mount(Settings $settings): void
    {
        $this->authorize('settings.manage');

        foreach (self::FIELDS as $fields) {
            foreach ($fields as $key => $type) {
                $value = $settings->get($key);
                $this->values[$key] = match ($type) {
                    'bool' => (bool) $value,
                    'array' => (array) $value,
                    default => (string) $value,
                };
            }
        }
    }

    public function save(Settings $settings): void
    {
        $this->authorize('settings.manage');
        $tab = in_array($this->tab, self::TABS, true) ? $this->tab : 'shop';

        $this->validate($this->rulesFor($tab));

        foreach (self::FIELDS[$tab] as $key => $type) {
            $value = $this->values[$key] ?? null;
            $settings->set($key, match ($type) {
                'bool' => (bool) $value,
                'int' => (int) $value,
                'float' => (float) $value,
                'array' => array_values((array) $value),
                default => $value === '' ? null : $value,
            });
        }

        if ($tab === 'shop' && $this->logo) {
            $this->validate(['logo' => ['image', 'max:1024']]);
            $old = $settings->get('shop_logo');
            $path = $this->logo->store('logo', 'public');
            $settings->set('shop_logo', $path);

            if ($old) {
                Storage::disk('public')->delete($old);
            }

            $this->logo = null;
        }

        if ($tab === 'shop') {
            app()->setLocale($this->values['default_locale']);
        }

        session()->flash('success', __('Settings saved.'));
    }

    public function removeLogo(Settings $settings): void
    {
        $this->authorize('settings.manage');
        $old = $settings->get('shop_logo');

        if ($old) {
            Storage::disk('public')->delete($old);
        }

        $settings->set('shop_logo', null);
    }

    public function addTechnician(): void
    {
        $this->authorize('settings.manage');
        $this->validate([
            'technicianName' => ['required', 'string', 'max:255'],
            'technicianPhone' => ['nullable', 'string', 'max:30'],
        ], [], ['technicianName' => __('name')]);

        Technician::query()->create([
            'branch_id' => app(CurrentBranch::class)->get()?->id,
            'name' => $this->technicianName,
            'phone' => $this->technicianPhone ?: null,
        ]);

        $this->reset('technicianName', 'technicianPhone');
    }

    public function toggleTechnician(int $id): void
    {
        $this->authorize('settings.manage');
        $technician = Technician::query()->findOrFail($id);
        $technician->update(['is_active' => ! $technician->is_active]);
    }

    public function sendTestSms(SmsService $sms): void
    {
        $this->authorize('settings.manage');
        $this->validate(['testPhone' => ['required', 'string', 'max:30']], [], ['testPhone' => __('phone')]);

        $log = $sms->send($this->testPhone, __('Test SMS from :shop', ['shop' => $this->values['shop_name'] ?? '']), 'test', force: true);

        session()->flash($log->status === 'sent' ? 'success' : 'error', $log->status === 'sent'
            ? __('Test SMS sent.')
            : __('SMS failed: :reason', ['reason' => str($log->response)->limit(200)]));
    }

    /**
     * @return array<string, mixed>
     */
    private function rulesFor(string $tab): array
    {
        return match ($tab) {
            'shop' => [
                'values.shop_name' => ['required', 'string', 'max:255'],
                'values.shop_name_bn' => ['nullable', 'string', 'max:255'],
                'values.shop_phone' => ['nullable', 'string', 'max:50'],
                'values.currency_symbol' => ['required', 'string', 'max:5'],
                'values.default_locale' => ['required', 'in:en,bn'],
                'values.digits' => ['required', 'in:en,bn'],
            ],
            'receipt' => [
                'values.receipt_print_mode' => ['required', 'in:on,off,ask'],
                'values.receipt_paper' => ['required', 'in:80,58,a4'],
                'values.receipt_language' => ['required', 'in:en,bn'],
                'values.receipt_header' => ['nullable', 'string', 'max:500'],
                'values.receipt_header_bn' => ['nullable', 'string', 'max:500'],
                'values.receipt_footer' => ['nullable', 'string', 'max:500'],
                'values.receipt_footer_bn' => ['nullable', 'string', 'max:500'],
                'values.return_policy_days' => ['required', 'integer', 'min:0', 'max:365'],
            ],
            'labels' => [
                'values.barcode_prefix' => ['required', 'digits_between:1,6'],
                'values.label_output' => ['required', 'in:a4,thermal'],
                'values.label_size' => ['required', 'regex:/^\d{2,3}x\d{2,3}$/'],
                'values.label_a4_layout' => ['required', 'regex:/^\d{1,2}x\d{1,2}$/'],
            ],
            'pos' => [
                'values.vat_default_rate' => ['required', 'numeric', 'min:0', 'max:100'],
                'values.vat_bin' => ['nullable', 'string', 'max:50'],
                'values.pos_max_discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            ],
            'repair' => [
                'values.repair_default_warranty_days' => ['required', 'integer', 'min:0', 'max:3650'],
                'values.repair_terms' => ['nullable', 'string', 'max:2000'],
                'values.repair_terms_bn' => ['nullable', 'string', 'max:2000'],
            ],
            'reports' => [
                'values.report_tagline' => ['nullable', 'string', 'max:255'],
                'values.report_footer_note' => ['nullable', 'string', 'max:500'],
            ],
            'sms' => [
                'values.sms_api_url' => ['nullable', 'required_if:values.sms_enabled,true', 'string', 'max:500'],
                'values.sms_api_key' => ['nullable', 'string', 'max:255'],
                'values.sms_sender_id' => ['nullable', 'string', 'max:50'],
                'values.sms_events' => ['array'],
                'values.sms_events.*' => ['in:'.implode(',', array_keys(SmsService::EVENTS))],
            ],
        };
    }

    public function render(): View
    {
        $settings = app(Settings::class);

        return view('livewire.settings.general-settings', [
            'logoPath' => $settings->get('shop_logo'),
            'technicians' => Technician::query()->orderBy('name')->get(),
            'smsEvents' => SmsService::EVENTS,
        ])->title(__('Settings'));
    }
}
