<?php

namespace App\Services;

use App\Enums\CashShiftStatus;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethodType;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Records money received or paid. Cash payments that go through the
 * drawer are linked to the branch's open cash shift.
 */
class PaymentService
{
    public function record(
        Branch $branch,
        Model $payable,
        PaymentMethod $method,
        PaymentDirection $direction,
        float $amount,
        ?string $transactionRef = null,
        bool $throughDrawer = true,
    ): Payment {
        $shift = $throughDrawer && $method->type === PaymentMethodType::Cash
            ? $this->openShift($branch)
            : null;

        return Payment::query()->create([
            'branch_id' => $branch->id,
            'payable_type' => $payable->getMorphClass(),
            'payable_id' => $payable->getKey(),
            'payment_method_id' => $method->id,
            'direction' => $direction,
            'amount' => round($amount, 2),
            'transaction_ref' => $transactionRef ?: null,
            'cash_shift_id' => $shift?->id,
            'user_id' => Auth::id(),
            'paid_at' => now(),
        ]);
    }

    public function openShift(Branch $branch): ?CashShift
    {
        return CashShift::query()
            ->where('branch_id', $branch->id)
            ->where('status', CashShiftStatus::Open)
            ->latest('opened_at')
            ->first();
    }
}
