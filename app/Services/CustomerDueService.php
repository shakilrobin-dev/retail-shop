<?php

namespace App\Services;

use App\Enums\PaymentDirection;
use App\Enums\PaymentMethodType;
use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\PaymentMethod;
use App\Models\Sale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Collect money a customer owes. One collection gets a money receipt number
 * and is split over the customer's due invoices, oldest first (or one chosen invoice).
 */
class CustomerDueService
{
    public function __construct(
        private PaymentService $payments,
        private NumberGenerator $numbers,
    ) {}

    public function collect(
        Branch $branch,
        Customer $customer,
        float $amount,
        PaymentMethod $method,
        ?int $saleId = null,
        ?string $transactionRef = null,
        ?string $note = null,
    ): CustomerPayment {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => __('Enter the amount received.')]);
        }

        if ($method->type === PaymentMethodType::Cash && ! $this->payments->openShift($branch)) {
            throw ValidationException::withMessages(['amount' => __('Open the cash register to take cash.')]);
        }

        return DB::transaction(function () use ($branch, $customer, $amount, $method, $saleId, $transactionRef, $note): CustomerPayment {
            $sales = Sale::query()
                ->where('customer_id', $customer->id)
                ->where('status', SaleStatus::Completed)
                ->where('due_amount', '>', 0)
                ->when($saleId, fn ($query) => $query->whereKey($saleId))
                ->orderBy('sold_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $due = round((float) $sales->sum('due_amount'), 2);

            if ($due <= 0) {
                throw ValidationException::withMessages(['amount' => __('Nothing is due.')]);
            }

            if ($amount - $due > 0.009) {
                throw ValidationException::withMessages(['amount' => __('Amount is more than the due (:due).', ['due' => money($due)])]);
            }

            $collection = CustomerPayment::query()->create([
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'receipt_no' => $this->numbers->next($branch, 'due_receipt'),
                'payment_method_id' => $method->id,
                'amount' => $amount,
                'transaction_ref' => $transactionRef ?: null,
                'note' => $note ?: null,
                'user_id' => Auth::id(),
                'paid_at' => now(),
            ]);

            $remaining = $amount;

            foreach ($sales as $sale) {
                if ($remaining <= 0) {
                    break;
                }

                $part = round(min($remaining, (float) $sale->due_amount), 2);
                $this->payments->record($branch, $sale, $method, PaymentDirection::In, $part, $transactionRef, customerPaymentId: $collection->id);
                $sale->setDue((float) $sale->due_amount - $part);
                $remaining = round($remaining - $part, 2);
            }

            return $collection->load('allocations.payable');
        });
    }
}
