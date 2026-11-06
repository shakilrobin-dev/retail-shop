<?php

namespace App\Services;

use App\Enums\CashShiftStatus;
use App\Enums\PaymentDirection;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Cash register: open with starting cash, close by counting the drawer.
 * One open register per branch.
 */
class CashShiftService
{
    public function __construct(private PaymentService $payments) {}

    public function open(Branch $branch, float $openingCash, ?string $note = null): CashShift
    {
        if ($this->payments->openShift($branch)) {
            throw ValidationException::withMessages(['opening_cash' => __('The cash register is already open.')]);
        }

        return CashShift::query()->create([
            'branch_id' => $branch->id,
            'user_id' => Auth::id(),
            'status' => CashShiftStatus::Open,
            'opened_at' => now(),
            'opening_cash' => round($openingCash, 2),
            'note' => $note,
        ]);
    }

    /**
     * @return array{opening: float, cash_in: float, cash_out: float, expected: float, by_method: array<string, float>}
     */
    public function summary(CashShift $shift): array
    {
        $payments = Payment::query()->with('paymentMethod')
            ->where('branch_id', $shift->branch_id)
            ->where('paid_at', '>=', $shift->opened_at)
            ->when($shift->closed_at, fn ($query) => $query->where('paid_at', '<=', $shift->closed_at))
            ->get();

        $drawer = $payments->where('cash_shift_id', $shift->id);
        $cashIn = (float) $drawer->where('direction', PaymentDirection::In)->sum('amount');
        $cashOut = (float) $drawer->where('direction', PaymentDirection::Out)->sum('amount');

        $byMethod = $payments->groupBy(fn (Payment $payment): string => $payment->paymentMethod->localName())
            ->map(fn ($group): float => round((float) $group->sum(fn (Payment $payment): float => $payment->direction === PaymentDirection::In ? (float) $payment->amount : -(float) $payment->amount), 2))
            ->all();

        return [
            'opening' => (float) $shift->opening_cash,
            'cash_in' => round($cashIn, 2),
            'cash_out' => round($cashOut, 2),
            'expected' => round((float) $shift->opening_cash + $cashIn - $cashOut, 2),
            'by_method' => $byMethod,
        ];
    }

    public function close(CashShift $shift, float $countedCash, ?string $note = null): CashShift
    {
        if ($shift->status !== CashShiftStatus::Open) {
            throw ValidationException::withMessages(['counted_cash' => __('This register is already closed.')]);
        }

        $expected = $this->summary($shift)['expected'];

        $shift->update([
            'status' => CashShiftStatus::Closed,
            'closed_at' => now(),
            'expected_cash' => $expected,
            'counted_cash' => round($countedCash, 2),
            'difference' => round($countedCash - $expected, 2),
            'note' => trim(($shift->note ? $shift->note."\n" : '').($note ?? '')) ?: null,
        ]);

        return $shift;
    }
}
