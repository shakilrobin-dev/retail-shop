<?php

namespace App\Services;

use App\Enums\PaymentDirection;
use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReturn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stock in from suppliers. Purchases are always paid in full.
 */
class PurchaseService
{
    public function __construct(
        private StockService $stock,
        private NumberGenerator $numbers,
        private PaymentService $payments,
    ) {}

    /**
     * @param  array{supplier_id: int|null, supplier_invoice_no: string|null, purchase_date: string, discount: float|string, note: string|null}  $header
     * @param  list<array{product_unit_id: int, quantity: float|string, unit_cost: float|string, new_selling_price?: float|string|null}>  $items
     */
    public function create(Branch $branch, array $header, array $items, PaymentMethod $paymentMethod, ?string $transactionRef = null, bool $paidFromDrawer = false): Purchase
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => __('Add at least one product.')]);
        }

        return DB::transaction(function () use ($branch, $header, $items, $paymentMethod, $transactionRef, $paidFromDrawer): Purchase {
            $units = ProductUnit::query()->with('product')->whereIn('id', array_column($items, 'product_unit_id'))->get()->keyBy('id');

            $lines = [];
            $subtotal = 0.0;

            foreach ($items as $item) {
                $unit = $units->get($item['product_unit_id']);

                if (! $unit) {
                    throw ValidationException::withMessages(['items' => __('A product in the list no longer exists.')]);
                }

                $quantity = (float) $item['quantity'];
                $unitCost = (float) $item['unit_cost'];
                $lineTotal = round($quantity * $unitCost, 2);
                $subtotal += $lineTotal;

                $lines[] = compact('unit', 'quantity', 'unitCost', 'lineTotal') + ['newPrice' => $item['new_selling_price'] ?? null];
            }

            $discount = min((float) $header['discount'], $subtotal);
            $total = round($subtotal - $discount, 2);

            $purchase = Purchase::query()->create([
                'branch_id' => $branch->id,
                'supplier_id' => $header['supplier_id'],
                'reference_no' => $this->numbers->next($branch, 'purchase'),
                'supplier_invoice_no' => $header['supplier_invoice_no'] ?: null,
                'purchase_date' => $header['purchase_date'],
                'subtotal' => round($subtotal, 2),
                'discount' => $discount,
                'total' => $total,
                'note' => $header['note'] ?: null,
                'user_id' => Auth::id(),
            ]);

            /** Spread the bill discount over the lines so average cost is correct. */
            $discountRatio = $subtotal > 0 ? $total / $subtotal : 1;

            foreach ($lines as $line) {
                /** @var ProductUnit $unit */
                $unit = $line['unit'];
                $baseQuantity = round($line['quantity'] * (float) $unit->quantity, 3);

                $purchase->items()->create([
                    'product_id' => $unit->product_id,
                    'product_unit_id' => $unit->id,
                    'quantity' => $line['quantity'],
                    'unit_quantity' => $unit->quantity,
                    'base_quantity' => $baseQuantity,
                    'unit_cost' => $line['unitCost'],
                    'line_total' => $line['lineTotal'],
                ]);

                $baseCost = $baseQuantity > 0 ? ($line['lineTotal'] * $discountRatio) / $baseQuantity : 0;

                /** @var Product $product */
                $product = Product::query()->lockForUpdate()->find($unit->product_id);
                $this->stock->updateAverageCost($product, $baseQuantity, $baseCost);
                $this->stock->move($branch, $product, $baseQuantity, StockMovementType::Purchase, $purchase, round($baseCost, 2));

                if ($line['newPrice'] !== null && $line['newPrice'] !== '') {
                    $unit->update(['selling_price' => $line['newPrice']]);
                }
            }

            if ($total > 0) {
                $this->payments->record($branch, $purchase, $paymentMethod, PaymentDirection::Out, $total, $transactionRef, $paidFromDrawer);
            }

            return $purchase->load('items');
        });
    }

    /**
     * Send items back to the supplier and receive the money back.
     *
     * @param  array<int, float|string>  $baseQuantities  purchase_item_id => base quantity to return
     */
    public function return(Purchase $purchase, array $baseQuantities, PaymentMethod $refundMethod, ?string $note = null, bool $intoDrawer = false): PurchaseReturn
    {
        return DB::transaction(function () use ($purchase, $baseQuantities, $refundMethod, $note, $intoDrawer): PurchaseReturn {
            $purchase->load(['items.product', 'branch']);
            $branch = $purchase->branch;
            $discountRatio = (float) $purchase->subtotal > 0 ? (float) $purchase->total / (float) $purchase->subtotal : 1;

            $purchaseReturn = PurchaseReturn::query()->create([
                'branch_id' => $branch->id,
                'purchase_id' => $purchase->id,
                'reference_no' => $this->numbers->next($branch, 'purchase_return'),
                'total' => 0,
                'note' => $note,
                'user_id' => Auth::id(),
            ]);

            $total = 0.0;

            foreach ($baseQuantities as $itemId => $quantity) {
                $quantity = round((float) $quantity, 3);

                if ($quantity <= 0) {
                    continue;
                }

                /** @var PurchaseItem|null $item */
                $item = $purchase->items->firstWhere('id', (int) $itemId);
                $returnable = (float) $item?->base_quantity - (float) $item?->returned_base_quantity;

                if (! $item || $quantity > $returnable) {
                    throw ValidationException::withMessages(["returns.{$itemId}" => __('Cannot return more than :qty.', ['qty' => $returnable + 0])]);
                }

                $baseCost = ((float) $item->line_total * $discountRatio) / (float) $item->base_quantity;
                $amount = round($baseCost * $quantity, 2);
                $total += $amount;

                $purchaseReturn->items()->create([
                    'purchase_item_id' => $item->id,
                    'base_quantity' => $quantity,
                    'amount' => $amount,
                ]);

                $item->increment('returned_base_quantity', $quantity);
                $this->stock->move($branch, $item->product, -$quantity, StockMovementType::PurchaseReturn, $purchaseReturn, round($baseCost, 2));
            }

            if ($total <= 0) {
                throw ValidationException::withMessages(['returns' => __('Enter a quantity to return.')]);
            }

            $purchaseReturn->update(['total' => round($total, 2)]);
            $this->payments->record($branch, $purchaseReturn, $refundMethod, PaymentDirection::In, $total, null, $intoDrawer);

            return $purchaseReturn;
        });
    }
}
