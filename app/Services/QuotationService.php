<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\ProductUnit;
use App\Models\Quotation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Price quotations. Totals are worked out exactly like a sale (VAT, discount, rounding),
 * but no stock or money moves.
 */
class QuotationService
{
    public function __construct(
        private SaleService $sales,
        private NumberGenerator $numbers,
    ) {}

    /**
     * @param  array{customer_name?: string|null, customer_phone?: string|null, customer_address?: string|null, quotation_date: string, valid_until?: string|null, note?: string|null}  $header
     * @param  list<array{product_unit_id: int, quantity: float|string, unit_price: float|string, discount?: string|null, discount_amount?: float|string, bill_discount?: bool, measure_rate?: float|string|null}>  $cart
     */
    public function save(Branch $branch, ?Quotation $quotation, array $header, array $cart, ?DiscountType $discountType = null, float $discountValue = 0): Quotation
    {
        if ($cart === []) {
            throw ValidationException::withMessages(['cart' => __('Add at least one product.')]);
        }

        return DB::transaction(function () use ($branch, $quotation, $header, $cart, $discountType, $discountValue): Quotation {
            $totals = $this->sales->calculate($branch, $cart, $discountType, $discountValue);
            $phone = trim((string) ($header['customer_phone'] ?? ''));
            $customer = $phone !== '' ? Customer::query()->where('phone', $phone)->first() : null;

            $quotation ??= new Quotation([
                'branch_id' => $branch->id,
                'quotation_no' => $this->numbers->next($branch, 'quotation'),
                'user_id' => Auth::id(),
            ]);

            $quotation->fill([
                'customer_id' => $customer?->id,
                'customer_name' => trim((string) ($header['customer_name'] ?? '')) ?: $customer?->name,
                'customer_phone' => $phone ?: null,
                'customer_address' => trim((string) ($header['customer_address'] ?? '')) ?: null,
                'quotation_date' => $header['quotation_date'],
                'valid_until' => ($header['valid_until'] ?? null) ?: null,
                'subtotal' => $totals['subtotal'],
                'discount_type' => $totals['discount_amount'] > 0 ? $discountType : null,
                'discount_value' => $totals['discount_amount'] > 0 ? $discountValue : 0,
                'discount_amount' => $totals['discount_amount'],
                'vat_amount' => $totals['vat_amount'],
                'rounding_amount' => $totals['rounding_amount'],
                'total' => $totals['total'],
                'note' => ($header['note'] ?? null) ?: null,
            ])->save();

            $quotation->items()->delete();

            foreach ($totals['lines'] as $line) {
                /** @var ProductUnit $unit */
                $unit = $line['unit'];

                $quotation->items()->create([
                    'product_id' => $unit->product_id,
                    'product_unit_id' => $unit->id,
                    'product_name' => $unit->product->name,
                    'unit_name' => $unit->name,
                    'quantity' => $line['quantity'],
                    'unit_quantity' => $unit->quantity,
                    'base_quantity' => round($line['quantity'] * (float) $unit->quantity, 3),
                    'unit_price' => $line['unit_price'],
                    'measure_unit' => $line['measure']['unit'] ?? null,
                    'measure_quantity' => $line['measure']['quantity'] ?? null,
                    'measure_rate' => $line['measure']['rate'] ?? null,
                    'discount_amount' => $line['discount_amount'],
                    'discount_percents' => $line['discount_percents'],
                    'bill_discount' => $line['bill_discount'],
                    'line_total' => $line['line_total'],
                ]);
            }

            return $quotation->load('items');
        });
    }
}
