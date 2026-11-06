<?php

namespace App\Livewire\Repairs;

use App\Enums\RepairStatus;
use App\Livewire\Concerns\SearchesProducts;
use App\Livewire\Concerns\ShowsStockErrors;
use App\Models\PaymentMethod;
use App\Models\RepairJob;
use App\Models\RepairJobPart;
use App\Models\Technician;
use App\Services\CurrentBranch;
use App\Services\RepairService;
use App\Services\Settings;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class RepairShow extends Component
{
    use SearchesProducts, ShowsStockErrors;

    public RepairJob $job;

    public string $statusNote = '';

    public string $service_charge = '0';

    public string $discount = '0';

    public ?int $technician_id = null;

    public string $payment_amount = '';

    public ?int $payment_method_id = null;

    /** @var list<array{payment_method_id: int, amount: string, transaction_ref: string}> */
    public array $deliveryPayments = [];

    public string $cancel_reason = '';

    public string $cancel_fee = '0';

    public ?int $refund_method_id = null;

    public string $warranty_problem = '';

    public string $panel = '';

    public function mount(RepairJob $job): void
    {
        $this->authorize('repairs.manage');
        abort_unless($job->branch_id === app(CurrentBranch::class)->get()?->id, 404);

        $this->job = $job;
        $this->service_charge = (string) $job->service_charge;
        $this->discount = (string) $job->discount;
        $this->technician_id = $job->technician_id;
        $this->payment_method_id = $this->refund_method_id = PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->value('id');
    }

    public function pickUnit(int $productUnitId): void
    {
        app(RepairService::class)->addPart($this->job, $productUnitId, 1);
    }

    public function removePart(int $partId, RepairService $repairs): void
    {
        $repairs->removePart(RepairJobPart::query()->where('repair_job_id', $this->job->id)->findOrFail($partId));
    }

    public function setStatus(string $status, RepairService $repairs): void
    {
        $repairs->changeStatus($this->job, RepairStatus::from($status), $this->statusNote ?: null);
        $this->statusNote = '';
    }

    /**
     * Click on the status bar. "Delivered" opens the delivery panel.
     */
    public function pipelineTo(string $status, RepairService $repairs): void
    {
        if ($status === RepairStatus::Delivered->value) {
            $this->openDelivery();

            return;
        }

        $this->setStatus($status, $repairs);
    }

    public function saveCharges(RepairService $repairs): void
    {
        $this->validate([
            'service_charge' => ['required', 'numeric', 'min:0'],
            'discount' => ['required', 'numeric', 'min:0'],
            'technician_id' => ['nullable', 'exists:technicians,id'],
        ]);

        $this->job->update(['technician_id' => $this->technician_id]);
        $repairs->updateCharges($this->job, (float) $this->service_charge, (float) $this->discount);
        session()->flash('success', __('Charges updated.'));
    }

    public function addPayment(RepairService $repairs): void
    {
        $this->validate([
            'payment_amount' => ['required', 'numeric', 'gt:0'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
        ], [], ['payment_amount' => __('amount')]);

        $repairs->takePayment($this->job, PaymentMethod::query()->findOrFail($this->payment_method_id), (float) $this->payment_amount);
        $this->payment_amount = '';
    }

    public function openDelivery(): void
    {
        $this->panel = 'deliver';
        $due = max(0, round((float) $this->job->total - (float) $this->job->paid_amount, 2));
        $this->deliveryPayments = [['payment_method_id' => $this->payment_method_id, 'amount' => (string) $due, 'transaction_ref' => '']];
    }

    public function addDeliveryPayment(): void
    {
        $this->deliveryPayments[] = ['payment_method_id' => $this->payment_method_id, 'amount' => '', 'transaction_ref' => ''];
    }

    public function deliver(RepairService $repairs): void
    {
        $this->validate([
            'deliveryPayments.*.amount' => ['nullable', 'numeric', 'min:0'],
            'deliveryPayments.*.payment_method_id' => ['required', 'exists:payment_methods,id'],
        ]);

        $repairs->deliver($this->job, array_map(fn (array $row): array => [
            'payment_method_id' => (int) $row['payment_method_id'],
            'amount' => $row['amount'] === '' ? 0 : $row['amount'],
            'transaction_ref' => $row['transaction_ref'] ?: null,
        ], $this->deliveryPayments));

        session()->flash('success', __('Device delivered. Print the bill for the customer.'));
        session()->flash('print_bill', true);
        $this->redirectRoute('repairs.show', $this->job, navigate: true);
    }

    public function cancelJob(RepairService $repairs): void
    {
        $this->validate([
            'cancel_reason' => ['required', 'string', 'max:255'],
            'cancel_fee' => ['required', 'numeric', 'min:0'],
            'refund_method_id' => ['nullable', 'exists:payment_methods,id'],
        ], [], ['cancel_reason' => __('reason')]);

        $repairs->cancel($this->job, $this->cancel_reason, (float) $this->cancel_fee, PaymentMethod::query()->find($this->refund_method_id));
        session()->flash('success', __('Job cancelled. Device returned to customer.'));
        $this->redirectRoute('repairs.show', $this->job, navigate: true);
    }

    public function createWarrantyClaim(RepairService $repairs): void
    {
        $this->validate(['warranty_problem' => ['required', 'string', 'max:2000']], [], ['warranty_problem' => __('problem')]);

        $claim = $repairs->warrantyClaim($this->job, $this->warranty_problem);
        session()->flash('success', __('Warranty job :no created.', ['no' => $claim->job_no]));
        session()->flash('print_slip', true);
        $this->redirectRoute('repairs.show', $claim, navigate: true);
    }

    public function render(): View
    {
        $this->job->refresh()->load(['customer', 'technician', 'parts', 'payments.paymentMethod', 'statusHistories.user', 'receiver', 'deliverer', 'warrantyOf']);
        $isClosed = in_array($this->job->status, [RepairStatus::Delivered, RepairStatus::Cancelled], true);

        return view('livewire.repairs.repair-show', [
            'isClosed' => $isClosed,
            'due' => round((float) $this->job->total - (float) $this->job->paid_amount, 2),
            'results' => $isClosed ? collect() : $this->productResults(),
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'techniciansEnabled' => app(Settings::class)->bool('technicians_enabled', $this->job->branch_id),
            'technicians' => Technician::query()->where('is_active', true)->orderBy('name')->get(),
            'nextStatuses' => [RepairStatus::Diagnosing, RepairStatus::WaitingForParts, RepairStatus::Repairing, RepairStatus::Ready],
            'pipeline' => collect([RepairStatus::Received, RepairStatus::Diagnosing, RepairStatus::WaitingForParts, RepairStatus::Repairing, RepairStatus::Ready, RepairStatus::Delivered])
                ->mapWithKeys(fn (RepairStatus $status): array => [$status->value => $status->label()])->all(),
            'customerJobs' => RepairJob::query()->where('customer_id', $this->job->customer_id)->count(),
        ])->title(__('Repair').' '.$this->job->job_no);
    }
}
