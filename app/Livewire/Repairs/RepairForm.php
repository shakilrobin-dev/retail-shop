<?php

namespace App\Livewire\Repairs;

use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Technician;
use App\Services\CurrentBranch;
use App\Services\RepairService;
use App\Services\Settings;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class RepairForm extends Component
{
    public const ACCESSORIES = ['charger', 'battery', 'sim', 'memory_card', 'cover', 'box', 'back_cover'];

    public const DEVICE_TYPES = ['phone', 'tablet', 'laptop', 'smart_watch', 'earphone', 'other'];

    public string $phone = '';

    public string $customerName = '';

    public ?int $customerId = null;

    public string $device_type = 'phone';

    public string $device_brand = '';

    public string $device_model = '';

    public string $device_color = '';

    public string $imei_serial = '';

    public string $device_password = '';

    /** @var list<string> */
    public array $accessories = [];

    public string $condition_notes = '';

    public string $problem = '';

    public string $estimated_cost = '';

    public string $estimated_delivery_at = '';

    public ?int $technician_id = null;

    public string $advance = '0';

    public ?int $advance_method_id = null;

    public string $transaction_ref = '';

    public function mount(): void
    {
        $this->authorize('repairs.manage');
        $this->advance_method_id = PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->value('id');
        $this->estimated_delivery_at = now()->addDay()->setTime(18, 0)->format('Y-m-d\TH:i');
    }

    /**
     * Fill in the customer when a known phone number is typed.
     */
    public function updatedPhone(): void
    {
        $customer = mb_strlen(trim($this->phone)) >= 6
            ? Customer::query()->where('phone', trim($this->phone))->first()
            : null;

        $this->customerId = $customer?->id;

        if ($customer) {
            $this->customerName = $customer->name;
        }
    }

    public function save(RepairService $repairs): void
    {
        $this->authorize('repairs.manage');

        $validated = $this->validate([
            'phone' => ['required', 'string', 'max:30'],
            'customerName' => ['required', 'string', 'max:255'],
            'device_type' => ['required', 'string', 'max:30'],
            'device_brand' => ['nullable', 'string', 'max:50'],
            'device_model' => ['nullable', 'string', 'max:100'],
            'device_color' => ['nullable', 'string', 'max:30'],
            'imei_serial' => ['nullable', 'string', 'max:50'],
            'device_password' => ['nullable', 'string', 'max:100'],
            'accessories' => ['array'],
            'accessories.*' => ['string', 'in:'.implode(',', self::ACCESSORIES)],
            'condition_notes' => ['nullable', 'string', 'max:2000'],
            'problem' => ['required', 'string', 'max:2000'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'estimated_delivery_at' => ['nullable', 'date'],
            'technician_id' => ['nullable', 'exists:technicians,id'],
            'advance' => ['nullable', 'numeric', 'min:0'],
            'advance_method_id' => ['nullable', 'exists:payment_methods,id'],
            'transaction_ref' => ['nullable', 'string', 'max:100'],
        ], [], ['customerName' => __('customer name')]);

        $customer = Customer::query()->firstOrCreate(['phone' => trim($validated['phone'])], ['name' => $validated['customerName']]);

        $job = $repairs->create(
            app(CurrentBranch::class)->get(),
            [
                'customer_id' => $customer->id,
                'technician_id' => $validated['technician_id'],
                'device_type' => $validated['device_type'],
                'device_brand' => $validated['device_brand'] ?: null,
                'device_model' => $validated['device_model'] ?: null,
                'device_color' => $validated['device_color'] ?: null,
                'imei_serial' => $validated['imei_serial'] ?: null,
                'device_password' => $validated['device_password'] ?: null,
                'accessories' => $validated['accessories'],
                'condition_notes' => $validated['condition_notes'] ?: null,
                'problem' => $validated['problem'],
                'estimated_cost' => $validated['estimated_cost'] !== '' ? $validated['estimated_cost'] : null,
                'estimated_delivery_at' => $validated['estimated_delivery_at'] ?: null,
            ],
            (float) $validated['advance'],
            $validated['advance_method_id'] ? PaymentMethod::query()->find($validated['advance_method_id']) : null,
            $validated['transaction_ref'] ?: null,
        );

        session()->flash('success', __('Repair job :no created.', ['no' => $job->job_no]));
        session()->flash('print_slip', true);
        $this->redirectRoute('repairs.show', $job, navigate: true);
    }

    public function render(): View
    {
        $branchId = app(CurrentBranch::class)->get()?->id;

        return view('livewire.repairs.repair-form', [
            'techniciansEnabled' => app(Settings::class)->bool('technicians_enabled', $branchId),
            'technicians' => Technician::query()->where('is_active', true)->orderBy('name')->get(),
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'knownCustomer' => $this->customerId ? Customer::query()->withCount('repairJobs')->find($this->customerId) : null,
        ])->title(__('New repair job'));
    }
}
