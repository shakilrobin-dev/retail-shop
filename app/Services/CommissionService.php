<?php

namespace App\Services;

use App\Enums\CommissionType;
use App\Enums\PaymentDirection;
use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\CommissionSettlement;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\Referrer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Commission for reference people.
 *
 * The invoice only records who referred the customer. Later the commission is worked out
 * on the person's total profit for a month or date range (a "settlement"), as a % of that
 * profit or a fixed amount. Paying it is saved as an expense in the "Commission" category,
 * so it counts in the profit & loss on the day it is paid ("commission given").
 */
class CommissionService
{
    public function __construct(private PaymentService $payments) {}

    /**
     * Referred sales of a person in a period (all branches), less anything returned from them.
     *
     * @return array{invoices: int, sales: float, cost: float, returns: float, profit: float}
     */
    public function summary(Referrer $referrer, CarbonInterface $from, CarbonInterface $to): array
    {
        $sales = Sale::query()
            ->where('referrer_id', $referrer->id)
            ->where('status', SaleStatus::Completed)
            ->whereBetween('sold_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        $saleIds = (clone $sales)->select('id');
        $netSales = (float) (clone $sales)->sum('total') - (float) (clone $sales)->sum('vat_amount');
        $cost = (float) SaleItem::query()->whereIn('sale_id', $saleIds)->sum(DB::raw('unit_cost * quantity'));

        $returnIds = SaleReturn::query()->whereIn('sale_id', $saleIds)->select('id');
        $returns = (float) SaleReturn::query()->whereIn('sale_id', $saleIds)->sum('total');
        $returnedCost = (float) SaleReturnItem::query()
            ->join('sale_items', 'sale_items.id', '=', 'sale_return_items.sale_item_id')
            ->whereIn('sale_return_items.sale_return_id', $returnIds)
            ->where('sale_return_items.restock', true)
            ->sum(DB::raw('sale_items.unit_cost * sale_return_items.quantity'));

        return [
            'invoices' => (clone $sales)->count(),
            'sales' => round($netSales - $returns, 2),
            'cost' => round($cost - $returnedCost, 2),
            'returns' => round($returns, 2),
            'profit' => round($netSales - $returns - ($cost - $returnedCost), 2),
        ];
    }

    /**
     * Commission for a profit: a % of it, or a fixed amount.
     */
    public function calculate(float $profit, CommissionType $type, float $value): float
    {
        return match ($type) {
            CommissionType::ProfitPercent => round(max(0, $profit) * max(0, $value) / 100, 2),
            CommissionType::Amount => round(max(0, $value), 2),
        };
    }

    /**
     * An earlier settlement of this person that overlaps the period, if any.
     */
    public function overlapping(Referrer $referrer, CarbonInterface $from, CarbonInterface $to): ?CommissionSettlement
    {
        return CommissionSettlement::query()
            ->where('referrer_id', $referrer->id)
            ->whereDate('period_from', '<=', $to->toDateString())
            ->whereDate('period_to', '>=', $from->toDateString())
            ->first();
    }

    /**
     * Save the commission for a period, and pay part or all of it now if asked.
     */
    public function settle(
        Branch $branch,
        Referrer $referrer,
        CarbonInterface $from,
        CarbonInterface $to,
        CommissionType $type,
        float $value,
        ?string $note = null,
        float $payNow = 0,
        ?PaymentMethod $method = null,
        bool $throughDrawer = true,
    ): CommissionSettlement {
        if ($to->lt($from)) {
            throw ValidationException::withMessages(['settleTo' => __('The end date must be on or after the start date.')]);
        }

        if ($type === CommissionType::ProfitPercent && $value > 100) {
            throw ValidationException::withMessages(['settleValue' => __('Commission cannot be more than 100% of the profit.')]);
        }

        $existing = $this->overlapping($referrer, $from, $to);

        if ($existing) {
            throw ValidationException::withMessages(['settleFrom' => __('Commission for :from – :to is already worked out. Choose dates after it.', [
                'from' => $existing->period_from->format('d M Y'),
                'to' => $existing->period_to->format('d M Y'),
            ])]);
        }

        $summary = $this->summary($referrer, $from, $to);
        $amount = $this->calculate($summary['profit'], $type, $value);

        if ($amount <= 0) {
            throw ValidationException::withMessages(['settleValue' => __('The commission comes to zero. Check the profit and the % or amount.')]);
        }

        if ($payNow > $amount + $referrer->commissionDue() + 0.001) {
            throw ValidationException::withMessages(['settlePay' => __('You can pay at most :amount now.', ['amount' => money($amount + $referrer->commissionDue())])]);
        }

        return DB::transaction(function () use ($branch, $referrer, $from, $to, $type, $value, $note, $payNow, $method, $throughDrawer, $summary, $amount): CommissionSettlement {
            $settlement = CommissionSettlement::query()->create([
                'referrer_id' => $referrer->id,
                'branch_id' => $branch->id,
                'period_from' => $from->toDateString(),
                'period_to' => $to->toDateString(),
                'invoices' => $summary['invoices'],
                'sales' => $summary['sales'],
                'profit' => $summary['profit'],
                'commission_type' => $type,
                'commission_value' => round($value, 2),
                'amount' => $amount,
                'note' => $note ?: null,
                'user_id' => Auth::id(),
            ]);

            if ($payNow > 0 && $method) {
                $this->pay($branch, $referrer, $payNow, $method, today()->toDateString(), null, $throughDrawer);
            }

            return $settlement;
        });
    }

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
