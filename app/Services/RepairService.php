<?php

namespace App\Services;

use App\Enums\PaymentDirection;
use App\Enums\RepairStatus;
use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\PaymentMethod;
use App\Models\ProductUnit;
use App\Models\RepairJob;
use App\Models\RepairJobPart;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Repair jobs: intake, status, parts from stock, charges, delivery, warranty claim.
 * The bill is paid in full at delivery (an advance can be taken at intake).
 */
class RepairService
{
    public function __construct(
        private StockService $stock,
        private NumberGenerator $numbers,
        private PaymentService $payments,
        private Settings $settings,
        private SmsService $sms,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  repair_jobs fields (customer_id, device_*, problem, ...)
     */
    public function create(Branch $branch, array $attributes, float $advance = 0, ?PaymentMethod $advanceMethod = null, ?string $transactionRef = null): RepairJob
    {
        $job = DB::transaction(function () use ($branch, $attributes, $advance, $advanceMethod, $transactionRef): RepairJob {
            $job = RepairJob::query()->create(array_merge($attributes, [
                'branch_id' => $branch->id,
                'job_no' => $this->numbers->next($branch, 'repair'),
                'status' => RepairStatus::Received,
                'received_by' => Auth::id(),
                'warranty_days' => $attributes['warranty_days'] ?? $this->settings->get('repair_default_warranty_days', $branch->id),
            ]));

            $this->logStatus($job, RepairStatus::Received);

            if ($advance > 0 && $advanceMethod) {
                $this->payments->record($branch, $job, $advanceMethod, PaymentDirection::In, $advance, $transactionRef);
                $this->recalculate($job);
            }

            return $job;
        });

        $this->sms->repairReceived($job);

        return $job;
    }

    public function changeStatus(RepairJob $job, RepairStatus $status, ?string $note = null): RepairJob
    {
        if (in_array($job->status, [RepairStatus::Delivered, RepairStatus::Cancelled], true)) {
            throw ValidationException::withMessages(['status' => __('This job is closed.')]);
        }

        if (in_array($status, [RepairStatus::Delivered, RepairStatus::Cancelled], true)) {
            throw ValidationException::withMessages(['status' => __('Use the Deliver or Cancel button.')]);
        }

        $job->update(['status' => $status]);
        $this->logStatus($job, $status, $note);

        if ($status === RepairStatus::Ready) {
            $this->sms->repairReady($job);
        }

        return $job;
    }

    /**
     * Use a spare part from stock in this repair.
     */
    public function addPart(RepairJob $job, int $productUnitId, float $quantity, ?float $unitPrice = null): RepairJobPart
    {
        $this->ensureOpen($job);

        return DB::transaction(function () use ($job, $productUnitId, $quantity, $unitPrice): RepairJobPart {
            $unit = ProductUnit::query()->with('product')->findOrFail($productUnitId);
            $baseQuantity = round($quantity * (float) $unit->quantity, 3);
            $price = $unitPrice ?? (float) $unit->selling_price;

            $part = $job->parts()->create([
                'product_id' => $unit->product_id,
                'product_unit_id' => $unit->id,
                'product_name' => $unit->product->name,
                'quantity' => $quantity,
                'unit_quantity' => $unit->quantity,
                'base_quantity' => $baseQuantity,
                'unit_price' => round($price, 2),
                'unit_cost' => round((float) $unit->product->cost_price * (float) $unit->quantity, 2),
                'line_total' => round($price * $quantity, 2),
            ]);

            $this->stock->move($job->branch, $unit->product, -$baseQuantity, StockMovementType::RepairUsage, $job);
            $this->recalculate($job);

            return $part;
        });
    }

    /**
     * Part was not used after all: put it back in stock.
     */
    public function removePart(RepairJobPart $part): void
    {
        $job = $part->repairJob;
        $this->ensureOpen($job);

        DB::transaction(function () use ($part, $job): void {
            $this->stock->move($job->branch, $part->product, (float) $part->base_quantity, StockMovementType::RepairReturn, $job, (float) $part->unit_cost / max(1, (float) $part->unit_quantity));
            $part->delete();
            $this->recalculate($job);
        });
    }

    public function updateCharges(RepairJob $job, float $serviceCharge, float $discount): RepairJob
    {
        $this->ensureOpen($job);
        $job->update(['service_charge' => round($serviceCharge, 2), 'discount' => round($discount, 2)]);

        return $this->recalculate($job);
    }

    public function takePayment(RepairJob $job, PaymentMethod $method, float $amount, ?string $transactionRef = null): RepairJob
    {
        $this->ensureOpen($job);

        if ($amount <= 0) {
            throw ValidationException::withMessages(['payment_amount' => __('Enter an amount.')]);
        }

        $this->payments->record($job->branch, $job, $method, PaymentDirection::In, $amount, $transactionRef);

        return $this->recalculate($job);
    }

    /**
     * Hand the device back. The rest of the bill must be paid now.
     *
     * @param  list<array{payment_method_id: int, amount: float|string, transaction_ref?: string|null}>  $payments
     */
    public function deliver(RepairJob $job, array $payments = []): RepairJob
    {
        $this->ensureOpen($job);

        return DB::transaction(function () use ($job, $payments): RepairJob {
            $job = $this->recalculate($job);
            $due = round((float) $job->total - (float) $job->paid_amount, 2);
            $paying = round(collect($payments)->sum(fn (array $row): float => (float) $row['amount']), 2);

            if ($paying + 0.009 < $due) {
                throw ValidationException::withMessages(['payments' => __('Payment is short by :amount. Full payment is required.', ['amount' => money($due - $paying)])]);
            }

            if ($paying - $due > 0.009) {
                throw ValidationException::withMessages(['payments' => __('Payment is more than the amount due (:amount).', ['amount' => money($due)])]);
            }

            $methods = PaymentMethod::query()->whereIn('id', array_column($payments, 'payment_method_id'))->get()->keyBy('id');

            foreach ($payments as $row) {
                if ((float) $row['amount'] > 0) {
                    $this->payments->record($job->branch, $job, $methods->get((int) $row['payment_method_id']), PaymentDirection::In, (float) $row['amount'], $row['transaction_ref'] ?? null);
                }
            }

            $job->update([
                'status' => RepairStatus::Delivered,
                'delivered_at' => now(),
                'delivered_by' => Auth::id(),
                'warranty_until' => $job->warranty_days ? today()->addDays($job->warranty_days) : null,
            ]);
            $this->logStatus($job, RepairStatus::Delivered);

            return $this->recalculate($job);
        });
    }

    /**
     * Customer takes the device back without repair. Parts go back to stock;
     * the advance is refunded unless a checking fee is kept.
     */
    public function cancel(RepairJob $job, string $reason, float $keepFee = 0, ?PaymentMethod $refundMethod = null): RepairJob
    {
        $this->ensureOpen($job);

        return DB::transaction(function () use ($job, $reason, $keepFee, $refundMethod): RepairJob {
            foreach ($job->parts as $part) {
                $this->removePart($part);
            }

            $job->update(['service_charge' => round($keepFee, 2), 'discount' => 0]);
            $job = $this->recalculate($job);
            $refund = round((float) $job->paid_amount - (float) $job->total, 2);

            if ($refund > 0) {
                if (! $refundMethod) {
                    throw ValidationException::withMessages(['refund_method_id' => __('Choose how to refund the advance.')]);
                }

                $this->payments->record($job->branch, $job, $refundMethod, PaymentDirection::Out, $refund);
            }

            $job->update(['status' => RepairStatus::Cancelled, 'delivered_at' => now(), 'delivered_by' => Auth::id()]);
            $this->logStatus($job, RepairStatus::Cancelled, $reason);

            return $this->recalculate($job);
        });
    }

    /**
     * Same device came back under repair warranty: open a free job linked to the old one.
     */
    public function warrantyClaim(RepairJob $job, string $problem): RepairJob
    {
        if ($job->status !== RepairStatus::Delivered) {
            throw ValidationException::withMessages(['problem' => __('Only delivered jobs can have a warranty claim.')]);
        }

        return $this->create($job->branch, [
            'customer_id' => $job->customer_id,
            'technician_id' => $job->technician_id,
            'warranty_of_job_id' => $job->id,
            'device_type' => $job->device_type,
            'device_brand' => $job->device_brand,
            'device_model' => $job->device_model,
            'device_color' => $job->device_color,
            'imei_serial' => $job->imei_serial,
            'problem' => $problem,
            'note' => __('Warranty claim of :job', ['job' => $job->job_no]),
            'warranty_days' => 0,
        ]);
    }

    public function recalculate(RepairJob $job): RepairJob
    {
        $partsTotal = round((float) $job->parts()->sum('line_total'), 2);
        $total = max(0, round($partsTotal + (float) $job->service_charge - (float) $job->discount, 2));
        $paidIn = (float) $job->payments()->where('direction', PaymentDirection::In)->sum('amount');
        $paidOut = (float) $job->payments()->where('direction', PaymentDirection::Out)->sum('amount');

        $job->update([
            'parts_total' => $partsTotal,
            'total' => $total,
            'paid_amount' => round($paidIn - $paidOut, 2),
        ]);

        return $job;
    }

    private function logStatus(RepairJob $job, RepairStatus $status, ?string $note = null): void
    {
        $job->statusHistories()->create([
            'status' => $status,
            'note' => $note,
            'user_id' => Auth::id(),
            'created_at' => now(),
        ]);
    }

    private function ensureOpen(RepairJob $job): void
    {
        if (in_array($job->status, [RepairStatus::Delivered, RepairStatus::Cancelled], true)) {
            throw ValidationException::withMessages(['status' => __('This job is closed.')]);
        }
    }
}
