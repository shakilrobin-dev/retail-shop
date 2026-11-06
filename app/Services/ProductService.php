<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Barcode;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseItem;
use App\Models\RepairJobPart;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves a product together with its units, barcodes and opening stock.
 */
class ProductService
{
    public function __construct(private StockService $stock) {}

    /**
     * @param  array<string, mixed>  $attributes  product fields
     * @param  list<array{id?: int|null, name: string, name_bn?: string|null, quantity: float|string, selling_price: float|string, is_base: bool, barcodes: list<array{id?: int|null, code: string, is_generated?: bool}>}>  $units
     */
    public function save(?Product $product, array $attributes, array $units, ?Branch $openingBranch = null, float $openingQuantity = 0): Product
    {
        return DB::transaction(function () use ($product, $attributes, $units, $openingBranch, $openingQuantity): Product {
            $isNew = $product === null;
            $product ??= new Product;

            $product->fill($attributes);

            if (blank($product->sku)) {
                $product->sku = 'TMP-'.uniqid();
            }

            $product->save();

            if (str_starts_with($product->sku, 'TMP-')) {
                $product->update(['sku' => 'P'.str_pad((string) $product->id, 6, '0', STR_PAD_LEFT)]);
            }

            $this->syncUnits($product, $units);

            if ($isNew && $openingBranch && $openingQuantity > 0) {
                $this->stock->move($openingBranch, $product, $openingQuantity, StockMovementType::Opening, null, (float) $product->cost_price, __('Opening stock'));
            }

            return $product->load('units.barcodes');
        });
    }

    /**
     * @param  list<array<string, mixed>>  $units
     */
    private function syncUnits(Product $product, array $units): void
    {
        $keptUnitIds = [];

        foreach ($units as $unitData) {
            $unit = isset($unitData['id']) ? $product->units()->find($unitData['id']) : null;
            $unit ??= new ProductUnit(['product_id' => $product->id]);

            $unit->fill([
                'name' => $unitData['name'],
                'name_bn' => $unitData['name_bn'] ?? null,
                'quantity' => $unitData['is_base'] ? 1 : $unitData['quantity'],
                'selling_price' => $unitData['selling_price'],
                'is_base' => (bool) $unitData['is_base'],
            ]);
            $unit->product_id = $product->id;
            $unit->save();
            $keptUnitIds[] = $unit->id;

            $this->syncBarcodes($product, $unit, $unitData['barcodes'] ?? []);
        }

        $removedUnits = $product->units()->whereNotIn('id', $keptUnitIds)->get();

        foreach ($removedUnits as $removedUnit) {
            if ($this->unitIsUsed($removedUnit)) {
                throw ValidationException::withMessages([
                    'units' => __('Unit ":unit" is used in old sales or purchases and cannot be removed.', ['unit' => $removedUnit->name]),
                ]);
            }

            $removedUnit->delete();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $barcodes
     */
    private function syncBarcodes(Product $product, ProductUnit $unit, array $barcodes): void
    {
        $keptCodes = [];

        foreach ($barcodes as $barcodeData) {
            $code = trim((string) $barcodeData['code']);

            if ($code === '') {
                continue;
            }

            Barcode::query()->updateOrCreate(
                ['code' => $code],
                [
                    'product_id' => $product->id,
                    'product_unit_id' => $unit->id,
                    'is_generated' => (bool) ($barcodeData['is_generated'] ?? false),
                ],
            );
            $keptCodes[] = $code;
        }

        $unit->barcodes()->whereNotIn('code', $keptCodes)->delete();
    }

    private function unitIsUsed(ProductUnit $unit): bool
    {
        return SaleItem::query()->where('product_unit_id', $unit->id)->exists()
            || PurchaseItem::query()->where('product_unit_id', $unit->id)->exists()
            || RepairJobPart::query()->where('product_unit_id', $unit->id)->exists();
    }

    /**
     * Find the product unit for a scanned barcode.
     */
    public function findByBarcode(string $code): ?Barcode
    {
        return Barcode::query()
            ->with(['product.units', 'productUnit'])
            ->where('code', trim($code))
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->first();
    }
}
