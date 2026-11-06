<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The only place that changes stock. Every change updates the branch
 * stock row and writes a stock movement, inside one transaction.
 * Quantities are always in the product's base unit (e.g. pieces).
 */
class StockService
{
    public function __construct(private Settings $settings) {}

    /**
     * Add (positive quantity) or remove (negative quantity) stock.
     *
     * @throws InsufficientStockException
     */
    public function move(
        Branch $branch,
        Product $product,
        float $quantity,
        StockMovementType $type,
        ?Model $reference = null,
        ?float $unitCost = null,
        ?string $note = null,
    ): StockMovement {
        return DB::transaction(function () use ($branch, $product, $quantity, $type, $reference, $unitCost, $note): StockMovement {
            ProductStock::query()->firstOrCreate(
                ['branch_id' => $branch->id, 'product_id' => $product->id],
                ['quantity' => 0],
            );

            $stock = ProductStock::query()
                ->where('branch_id', $branch->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $available = (float) $stock->quantity;
            $newBalance = round($available + $quantity, 3);

            if ($quantity < 0 && $newBalance < 0 && ! $this->settings->bool('pos_allow_negative_stock', $branch->id)) {
                throw new InsufficientStockException($product, $available, abs($quantity));
            }

            $stock->update(['quantity' => $newBalance]);

            return StockMovement::query()->create([
                'branch_id' => $branch->id,
                'product_id' => $product->id,
                'type' => $type,
                'quantity' => round($quantity, 3),
                'balance_after' => $newBalance,
                'unit_cost' => $unitCost ?? $product->cost_price,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'note' => $note,
                'user_id' => Auth::id(),
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Update the product's average cost when new stock is bought.
     * Call this BEFORE adding the purchased quantity with move().
     */
    public function updateAverageCost(Product $product, float $incomingQuantity, float $incomingUnitCost): void
    {
        if ($incomingQuantity <= 0) {
            return;
        }

        $currentQuantity = max(0, $this->totalQuantity($product));
        $currentCost = (float) $product->cost_price;
        $totalQuantity = $currentQuantity + $incomingQuantity;

        $averageCost = (($currentQuantity * $currentCost) + ($incomingQuantity * $incomingUnitCost)) / $totalQuantity;

        $product->update(['cost_price' => round($averageCost, 2)]);
    }

    /**
     * Stock of a product in one branch.
     */
    public function quantity(Branch $branch, Product $product): float
    {
        return (float) ProductStock::query()
            ->where('branch_id', $branch->id)
            ->where('product_id', $product->id)
            ->value('quantity');
    }

    /**
     * Stock of a product in all branches together.
     */
    public function totalQuantity(Product $product): float
    {
        return (float) ProductStock::query()->where('product_id', $product->id)->sum('quantity');
    }
}
