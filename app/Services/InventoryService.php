<?php

namespace App\Services;

use App\Enums\AdjustmentReason;
use App\Enums\StockMovementType;
use App\Enums\TransferStatus;
use App\Models\Branch;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockTransfer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stock adjustments (damaged, lost, count correction...) and transfers between branches.
 */
class InventoryService
{
    public function __construct(
        private StockService $stock,
        private NumberGenerator $numbers,
    ) {}

    /**
     * @param  array<int, float>  $changes  product_id => signed change in base units
     */
    public function adjust(Branch $branch, AdjustmentReason $reason, array $changes, ?string $note = null): StockAdjustment
    {
        $changes = array_filter($changes, fn ($change): bool => abs((float) $change) > 0.0001);

        if ($changes === []) {
            throw ValidationException::withMessages(['lines' => __('No stock change to save.')]);
        }

        return DB::transaction(function () use ($branch, $reason, $changes, $note): StockAdjustment {
            $adjustment = StockAdjustment::query()->create([
                'branch_id' => $branch->id,
                'reference_no' => $this->numbers->next($branch, 'adjustment'),
                'reason' => $reason,
                'note' => $note,
                'user_id' => Auth::id(),
            ]);

            $products = Product::query()->whereIn('id', array_keys($changes))->get()->keyBy('id');

            foreach ($changes as $productId => $change) {
                $product = $products->get($productId);
                $adjustment->items()->create([
                    'product_id' => $productId,
                    'quantity' => round((float) $change, 3),
                    'unit_cost' => $product->cost_price,
                ]);

                $this->stock->move($branch, $product, (float) $change, StockMovementType::Adjustment, $adjustment, null, $reason->label());
            }

            return $adjustment;
        });
    }

    /**
     * Send stock to another branch. Stock leaves now and arrives when received.
     *
     * @param  array<int, float>  $quantities  product_id => base quantity
     */
    public function send(Branch $from, Branch $to, array $quantities, ?string $note = null): StockTransfer
    {
        if ($from->is($to)) {
            throw ValidationException::withMessages(['to_branch_id' => __('Choose a different branch.')]);
        }

        $quantities = array_filter($quantities, fn ($quantity): bool => (float) $quantity > 0);

        if ($quantities === []) {
            throw ValidationException::withMessages(['lines' => __('Add at least one product.')]);
        }

        return DB::transaction(function () use ($from, $to, $quantities, $note): StockTransfer {
            $transfer = StockTransfer::query()->create([
                'reference_no' => $this->numbers->next($from, 'transfer'),
                'from_branch_id' => $from->id,
                'to_branch_id' => $to->id,
                'status' => TransferStatus::InTransit,
                'note' => $note,
                'sent_by' => Auth::id(),
                'sent_at' => now(),
            ]);

            $products = Product::query()->whereIn('id', array_keys($quantities))->get()->keyBy('id');

            foreach ($quantities as $productId => $quantity) {
                $product = $products->get($productId);
                $transfer->items()->create([
                    'product_id' => $productId,
                    'quantity_sent' => round((float) $quantity, 3),
                    'unit_cost' => $product->cost_price,
                ]);

                $this->stock->move($from, $product, -(float) $quantity, StockMovementType::TransferOut, $transfer);
            }

            return $transfer;
        });
    }

    /**
     * Receiving branch confirms what arrived. Missing quantity is recorded on the transfer.
     *
     * @param  array<int, float|string>  $received  stock_transfer_item_id => base quantity received
     */
    public function receive(StockTransfer $transfer, array $received): StockTransfer
    {
        if ($transfer->status !== TransferStatus::InTransit) {
            throw ValidationException::withMessages(['received' => __('This transfer is already closed.')]);
        }

        return DB::transaction(function () use ($transfer, $received): StockTransfer {
            $transfer->load(['items.product', 'toBranch']);

            foreach ($transfer->items as $item) {
                $quantity = round((float) ($received[$item->id] ?? $item->quantity_sent), 3);

                if ($quantity < 0 || $quantity > (float) $item->quantity_sent) {
                    throw ValidationException::withMessages(["received.{$item->id}" => __('Must be between 0 and :qty.', ['qty' => $item->quantity_sent + 0])]);
                }

                $item->update(['quantity_received' => $quantity]);

                if ($quantity > 0) {
                    $this->stock->move($transfer->toBranch, $item->product, $quantity, StockMovementType::TransferIn, $transfer, (float) $item->unit_cost);
                }
            }

            $transfer->update(['status' => TransferStatus::Received, 'received_by' => Auth::id(), 'received_at' => now()]);

            return $transfer;
        });
    }

    /**
     * Cancel a transfer still on the way: stock goes back to the sending branch.
     */
    public function cancel(StockTransfer $transfer): StockTransfer
    {
        if ($transfer->status !== TransferStatus::InTransit) {
            throw ValidationException::withMessages(['received' => __('This transfer is already closed.')]);
        }

        return DB::transaction(function () use ($transfer): StockTransfer {
            $transfer->load(['items.product', 'fromBranch']);

            foreach ($transfer->items as $item) {
                $this->stock->move($transfer->fromBranch, $item->product, (float) $item->quantity_sent, StockMovementType::TransferIn, $transfer, (float) $item->unit_cost, __('Transfer cancelled'));
            }

            $transfer->update(['status' => TransferStatus::Cancelled]);

            return $transfer;
        });
    }
}
