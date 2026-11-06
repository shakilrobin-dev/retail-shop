<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethodType;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\PaymentMethod;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sales at the POS: calculate totals, complete, hold, void and return.
 * No credit: a sale is completed only when fully paid.
 */
class SaleService
{
    public function __construct(
        private StockService $stock,
        private NumberGenerator $numbers,
        private PaymentService $payments,
        private Settings $settings,
        private SmsService $sms,
    ) {}

    /**
     * Work out all line and bill totals for a cart.
     *
     * @param  list<array{product_unit_id: int, quantity: float|string, unit_price: float|string, discount_amount?: float|string}>  $cart
     * @return array{lines: list<array<string, mixed>>, subtotal: float, discount_amount: float, vat_amount: float, total: float}
     */
    public function calculate(Branch $branch, array $cart, ?DiscountType $discountType = null, float $discountValue = 0): array
    {
        $units = ProductUnit::query()->with('product')->whereIn('id', array_column($cart, 'product_unit_id'))->get()->keyBy('id');
        $vatEnabled = $this->settings->bool('vat_enabled', $branch->id);
        $vatInclusive = $this->settings->bool('vat_inclusive', $branch->id);

        $lines = [];
        $subtotal = 0.0;

        foreach ($cart as $row) {
            $unit = $units->get((int) $row['product_unit_id']);

            if (! $unit) {
                throw ValidationException::withMessages(['cart' => __('A product in the cart no longer exists.')]);
            }

            $quantity = round((float) $row['quantity'], 3);
            $unitPrice = round((float) $row['unit_price'], 2);
            $lineDiscount = min(round((float) ($row['discount_amount'] ?? 0), 2), round($quantity * $unitPrice, 2));
            $lineTotal = round($quantity * $unitPrice - $lineDiscount, 2);
            $subtotal += $lineTotal;

            $lines[] = [
                'unit' => $unit,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_amount' => $lineDiscount,
                'line_total' => $lineTotal,
                'vat_rate' => $vatEnabled ? (float) $unit->product->vat_rate : 0.0,
            ];
        }

        $subtotal = round($subtotal, 2);
        $billDiscount = match ($discountType) {
            DiscountType::Percent => round($subtotal * min(100, max(0, $discountValue)) / 100, 2),
            DiscountType::Amount => round(min($subtotal, max(0, $discountValue)), 2),
            default => 0.0,
        };

        /** VAT is worked out on each line after its share of the bill discount. */
        $vatTotal = 0.0;
        $afterDiscountRatio = $subtotal > 0 ? ($subtotal - $billDiscount) / $subtotal : 1;

        foreach ($lines as $index => $line) {
            $taxable = $line['line_total'] * $afterDiscountRatio;
            $rate = $line['vat_rate'] / 100;
            $vat = $vatInclusive ? $taxable - ($taxable / (1 + $rate)) : $taxable * $rate;
            $lines[$index]['vat_amount'] = round($vat, 2);
            $vatTotal += $vat;
        }

        $vatTotal = round($vatTotal, 2);
        $total = round($subtotal - $billDiscount + ($vatInclusive ? 0 : $vatTotal), 2);

        return [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'discount_amount' => $billDiscount,
            'vat_amount' => $vatTotal,
            'total' => $total,
        ];
    }

    /**
     * Complete a sale: save it, take stock out and record payments.
     *
     * @param  list<array{product_unit_id: int, quantity: float|string, unit_price: float|string, discount_amount?: float|string}>  $cart
     * @param  list<array{payment_method_id: int, amount: float|string, transaction_ref?: string|null}>  $payments
     */
    public function complete(
        Branch $branch,
        array $cart,
        array $payments,
        ?DiscountType $discountType = null,
        float $discountValue = 0,
        ?int $customerId = null,
        ?string $note = null,
    ): Sale {
        if ($cart === []) {
            throw ValidationException::withMessages(['cart' => __('The cart is empty.')]);
        }

        $shift = $this->payments->openShift($branch);

        if (! $shift) {
            throw ValidationException::withMessages(['cart' => __('Open the cash register before selling.')]);
        }

        $sale = DB::transaction(function () use ($branch, $cart, $payments, $discountType, $discountValue, $customerId, $note, $shift): Sale {
            $totals = $this->calculate($branch, $cart, $discountType, $discountValue);
            $this->checkPrices($branch, $totals);

            [$paymentRows, $paid, $change] = $this->preparePayments($payments, $totals['total']);

            $sale = Sale::query()->create([
                'branch_id' => $branch->id,
                'invoice_no' => $this->numbers->next($branch, 'invoice'),
                'status' => SaleStatus::Completed,
                'customer_id' => $customerId,
                'user_id' => Auth::id(),
                'cash_shift_id' => $shift->id,
                'sold_at' => now(),
                'subtotal' => $totals['subtotal'],
                'discount_type' => $totals['discount_amount'] > 0 ? $discountType : null,
                'discount_value' => $totals['discount_amount'] > 0 ? $discountValue : 0,
                'discount_amount' => $totals['discount_amount'],
                'vat_amount' => $totals['vat_amount'],
                'total' => $totals['total'],
                'paid_amount' => $paid,
                'change_amount' => $change,
                'note' => $note ?: null,
            ]);

            foreach ($totals['lines'] as $line) {
                /** @var ProductUnit $unit */
                $unit = $line['unit'];
                $baseQuantity = round($line['quantity'] * (float) $unit->quantity, 3);

                $sale->items()->create([
                    'product_id' => $unit->product_id,
                    'product_unit_id' => $unit->id,
                    'product_name' => $unit->product->name,
                    'unit_name' => $unit->name,
                    'quantity' => $line['quantity'],
                    'unit_quantity' => $unit->quantity,
                    'base_quantity' => $baseQuantity,
                    'unit_price' => $line['unit_price'],
                    'unit_cost' => round((float) $unit->product->cost_price * (float) $unit->quantity, 2),
                    'discount_amount' => $line['discount_amount'],
                    'vat_rate' => $line['vat_rate'],
                    'vat_amount' => $line['vat_amount'],
                    'line_total' => $line['line_total'],
                    'warranty_days' => $unit->product->warranty_days,
                ]);

                $this->stock->move($branch, $unit->product, -$baseQuantity, StockMovementType::Sale, $sale);
            }

            foreach ($paymentRows as $row) {
                $this->payments->record($branch, $sale, $row['method'], PaymentDirection::In, $row['amount'], $row['transaction_ref']);
            }

            return $sale->load('items');
        });

        $this->sms->saleReceipt($sale);

        return $sale;
    }

    /**
     * Put the cart aside (customer went to pick something else).
     *
     * @param  list<array{product_unit_id: int, quantity: float|string, unit_price: float|string, discount_amount?: float|string}>  $cart
     */
    public function hold(Branch $branch, array $cart, ?string $holdNote = null, ?int $customerId = null): Sale
    {
        return DB::transaction(function () use ($branch, $cart, $holdNote, $customerId): Sale {
            $totals = $this->calculate($branch, $cart);

            $sale = Sale::query()->create([
                'branch_id' => $branch->id,
                'status' => SaleStatus::Held,
                'customer_id' => $customerId,
                'user_id' => Auth::id(),
                'subtotal' => $totals['subtotal'],
                'total' => $totals['total'],
                'hold_note' => $holdNote ?: null,
            ]);

            foreach ($totals['lines'] as $line) {
                $unit = $line['unit'];
                $sale->items()->create([
                    'product_id' => $unit->product_id,
                    'product_unit_id' => $unit->id,
                    'product_name' => $unit->product->name,
                    'unit_name' => $unit->name,
                    'quantity' => $line['quantity'],
                    'unit_quantity' => $unit->quantity,
                    'base_quantity' => round($line['quantity'] * (float) $unit->quantity, 3),
                    'unit_price' => $line['unit_price'],
                    'discount_amount' => $line['discount_amount'],
                    'line_total' => $line['line_total'],
                ]);
            }

            return $sale;
        });
    }

    /**
     * Take a held sale back into the cart. The held record is removed.
     *
     * @return array{cart: list<array{product_unit_id: int, quantity: float, unit_price: float, discount_amount: float}>, customer_id: int|null}
     */
    public function resume(Sale $sale): array
    {
        if ($sale->status !== SaleStatus::Held) {
            throw ValidationException::withMessages(['cart' => __('This sale is not on hold.')]);
        }

        return DB::transaction(function () use ($sale): array {
            $cart = $sale->items->map(fn (SaleItem $item): array => [
                'product_unit_id' => $item->product_unit_id,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'discount_amount' => (float) $item->discount_amount,
            ])->all();

            $customerId = $sale->customer_id;
            $sale->items()->delete();
            $sale->delete();

            return ['cart' => $cart, 'customer_id' => $customerId];
        });
    }

    /**
     * Cancel a completed sale: stock goes back and the money is refunded.
     */
    public function void(Sale $sale, string $reason): Sale
    {
        if ($sale->status !== SaleStatus::Completed) {
            throw ValidationException::withMessages(['void_reason' => __('Only completed sales can be voided.')]);
        }

        if ($sale->returns()->exists()) {
            throw ValidationException::withMessages(['void_reason' => __('This sale has returns. Use returns instead of void.')]);
        }

        return DB::transaction(function () use ($sale, $reason): Sale {
            $sale->load(['items.product', 'payments.paymentMethod', 'branch']);

            foreach ($sale->items as $item) {
                $this->stock->move($sale->branch, $item->product, (float) $item->base_quantity, StockMovementType::SaleVoid, $sale, (float) $item->unit_cost / max(1, (float) $item->unit_quantity));
            }

            $netByMethod = $sale->payments->groupBy('payment_method_id')->map(fn (Collection $payments): float => (float) $payments->sum(
                fn ($payment) => $payment->direction === PaymentDirection::In ? (float) $payment->amount : -(float) $payment->amount,
            ));

            foreach ($netByMethod as $methodId => $amount) {
                if ($amount > 0) {
                    $this->payments->record($sale->branch, $sale, $sale->payments->firstWhere('payment_method_id', $methodId)->paymentMethod, PaymentDirection::Out, $amount);
                }
            }

            $sale->update([
                'status' => SaleStatus::Voided,
                'voided_at' => now(),
                'voided_by' => Auth::id(),
                'void_reason' => $reason,
            ]);

            return $sale;
        });
    }

    /**
     * Customer returns items. Refund is the price actually paid for them.
     *
     * @param  array<int, array{quantity: float|string, restock: bool}>  $items  sale_item_id => details
     */
    public function return(Sale $sale, array $items, PaymentMethod $refundMethod, ?string $reason = null): SaleReturn
    {
        if ($sale->status !== SaleStatus::Completed) {
            throw ValidationException::withMessages(['returns' => __('Only completed sales can be returned.')]);
        }

        $policyDays = (int) $this->settings->get('return_policy_days', $sale->branch_id);
        /** @var User|null $user */
        $user = Auth::user();

        if ($policyDays > 0 && $sale->sold_at->copy()->addDays($policyDays)->endOfDay()->isPast() && ! $user?->hasRole('admin')) {
            throw ValidationException::withMessages(['returns' => __('Return time (:days days) is over. Only admin can accept this return.', ['days' => $policyDays])]);
        }

        return DB::transaction(function () use ($sale, $items, $refundMethod, $reason): SaleReturn {
            $sale->load(['items.product', 'branch']);
            $branch = $sale->branch;
            $linesTotal = (float) $sale->items->sum('line_total');
            $paidRatio = $linesTotal > 0 ? (float) $sale->total / $linesTotal : 1;

            $saleReturn = SaleReturn::query()->create([
                'branch_id' => $branch->id,
                'sale_id' => $sale->id,
                'reference_no' => $this->numbers->next($branch, 'sale_return'),
                'total' => 0,
                'reason' => $reason,
                'user_id' => Auth::id(),
                'cash_shift_id' => $this->payments->openShift($branch)?->id,
            ]);

            $total = 0.0;

            foreach ($items as $itemId => $row) {
                $quantity = round((float) $row['quantity'], 3);

                if ($quantity <= 0) {
                    continue;
                }

                /** @var SaleItem|null $item */
                $item = $sale->items->firstWhere('id', (int) $itemId);
                $returnable = (float) $item?->quantity - (float) $item?->returned_quantity;

                if (! $item || $quantity > $returnable) {
                    throw ValidationException::withMessages(["returns.{$itemId}.quantity" => __('Cannot return more than :qty.', ['qty' => $returnable + 0])]);
                }

                $amount = round(((float) $item->line_total / (float) $item->quantity) * $quantity * $paidRatio, 2);
                $total += $amount;

                $saleReturn->items()->create([
                    'sale_item_id' => $item->id,
                    'quantity' => $quantity,
                    'amount' => $amount,
                    'restock' => (bool) $row['restock'],
                ]);

                $item->increment('returned_quantity', $quantity);

                if ($row['restock']) {
                    $this->stock->move($branch, $item->product, round($quantity * (float) $item->unit_quantity, 3), StockMovementType::SaleReturn, $saleReturn, (float) $item->unit_cost / max(1, (float) $item->unit_quantity));
                }
            }

            if ($total <= 0) {
                throw ValidationException::withMessages(['returns' => __('Enter a quantity to return.')]);
            }

            $saleReturn->update(['total' => round($total, 2)]);
            $this->payments->record($branch, $saleReturn, $refundMethod, PaymentDirection::Out, $total);

            return $saleReturn;
        });
    }

    /**
     * @param  array{lines: list<array<string, mixed>>}  $totals
     */
    private function checkPrices(Branch $branch, array $totals): void
    {
        foreach ($totals['lines'] as $index => $line) {
            $unit = $line['unit'];
            $minimum = $unit->product->min_selling_price;

            if ($minimum === null) {
                continue;
            }

            $netPerBase = $line['quantity'] > 0 ? ($line['line_total'] / $line['quantity']) / (float) $unit->quantity : 0;

            if ($netPerBase + 0.001 < (float) $minimum) {
                throw ValidationException::withMessages(["cart.{$index}" => __(':product cannot be sold below :price.', [
                    'product' => $unit->product->name,
                    'price' => money((float) $minimum * (float) $unit->quantity),
                ])]);
            }
        }
    }

    /**
     * Check payments cover the total. Only cash can be more than the total (change is given back).
     *
     * @param  list<array{payment_method_id: int, amount: float|string, transaction_ref?: string|null}>  $payments
     * @return array{0: list<array{method: PaymentMethod, amount: float, transaction_ref: string|null}>, 1: float, 2: float}
     */
    private function preparePayments(array $payments, float $total): array
    {
        $methods = PaymentMethod::query()->whereIn('id', array_column($payments, 'payment_method_id'))->get()->keyBy('id');
        $rows = [];
        $cashGiven = 0.0;
        $otherPaid = 0.0;

        foreach ($payments as $payment) {
            $amount = round((float) $payment['amount'], 2);
            $method = $methods->get((int) $payment['payment_method_id']);

            if ($amount <= 0 || ! $method) {
                continue;
            }

            if ($method->type === PaymentMethodType::Cash) {
                $cashGiven += $amount;
            } else {
                $otherPaid += $amount;
            }

            $rows[] = ['method' => $method, 'amount' => $amount, 'transaction_ref' => $payment['transaction_ref'] ?? null];
        }

        if ($otherPaid - $total > 0.009) {
            throw ValidationException::withMessages(['payments' => __('Card / mobile payment cannot be more than the bill.')]);
        }

        $paid = round($cashGiven + $otherPaid, 2);

        if ($total - $paid > 0.009) {
            throw ValidationException::withMessages(['payments' => __('Payment is short by :amount. Full payment is required.', ['amount' => money($total - $paid)])]);
        }

        $change = round($paid - $total, 2);

        /** Record only the cash actually kept (cash given minus change). */
        if ($change > 0) {
            foreach ($rows as $index => $row) {
                if ($row['method']->type === PaymentMethodType::Cash) {
                    $keep = min($row['amount'], $change);
                    $rows[$index]['amount'] = round($row['amount'] - $keep, 2);
                    $change -= $keep;
                }
            }

            $rows = array_values(array_filter($rows, fn (array $row): bool => $row['amount'] > 0));
            $change = round($paid - $total, 2);
        }

        return [$rows, $paid, $change];
    }
}
