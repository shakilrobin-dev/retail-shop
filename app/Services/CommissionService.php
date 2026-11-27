<?php

namespace App\Services;

use App\Enums\PaymentDirection;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\Referrer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Paying commission to reference people.
 * A payment is saved as an expense in the "Commission" category, so it counts in
 * the profit & loss on the day it is paid ("commission given").
 */
class CommissionService
{
    public function __construct(private PaymentService $payments) {}

    public function pay(
        Branch $branch,
        Referrer $referrer,
        float $amount,
        PaymentMethod $method,
        string $date,
        ?string $note = null,
        bool $throughDrawer = true,
    ): Expense {
        $amount = round($amount, 2);
        $due = $referrer->commissionDue();

        if ($amount <= 0) {
            throw ValidationException::withMessages(['payAmount' => __('Enter the amount to pay.')]);
        }

        if ($amount > $due + 0.001) {
            throw ValidationException::withMessages(['payAmount' => __('Only :amount commission is due to :name.', ['amount' => money($due), 'name' => $referrer->name])]);
        }

        return DB::transaction(function () use ($branch, $referrer, $amount, $method, $date, $note, $throughDrawer): Expense {
            $expense = Expense::query()->create([
                'branch_id' => $branch->id,
                'expense_category_id' => self::category()->id,
                'referrer_id' => $referrer->id,
                'expense_date' => $date,
                'amount' => $amount,
                'note' => $note ?: __('Commission to :name', ['name' => $referrer->name]),
                'user_id' => Auth::id(),
            ]);

            $this->payments->record($branch, $expense, $method, PaymentDirection::Out, $amount, null, $throughDrawer);

            return $expense;
        });
    }

    /**
     * The expense category that commission payments are saved under.
     */
    public static function category(): ExpenseCategory
    {
        return ExpenseCategory::query()->firstOrCreate(['name' => 'Commission'], ['name_bn' => 'কমিশন']);
    }
}
