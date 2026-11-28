<?php

namespace App\Services\Imports;

use App\Enums\AdjustmentReason;
use App\Models\Barcode;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductStock;
use App\Services\InventoryService;
use App\Services\StockService;
use Illuminate\Support\Facades\Auth;

/**
 * Bulk stock entry for the current branch. Two modes:
 *   add — add the quantities to the stock there is now (goods received, opening stock);
 *   set — the quantities are a stock count and replace the stock there is now.
 * Saved as one stock adjustment, so it shows in the stock history with a reference number.
 */
class StockImport extends ImportSheet
{
    public const MODES = ['add', 'set'];

    public function __construct(private InventoryService $inventory, private StockService $stock) {}

    public function key(): string
    {
        return 'stock';
    }

    public function sheetName(): string
    {
        return 'Stock';
    }

    public function columns(): array
    {
        return [
            'sku' => ['label' => 'SKU', 'hint' => 'Product code. Fill SKU, Barcode or Product name — one is enough to find the product.', 'example' => 'P000012', 'width' => 14, 'text' => true],
            'barcode' => ['label' => 'Barcode', 'hint' => 'Barcode of the product (or of its pack — then the quantity is counted in packs).', 'example' => '8901000000017', 'width' => 18, 'text' => true],
            'name' => ['label' => 'Product name', 'hint' => 'Exact product name, used when SKU and Barcode are empty.', 'example' => 'Floor Tiles 24x24 Glossy', 'width' => 32],
            'quantity' => ['label' => 'Quantity', 'required' => true, 'hint' => 'How much stock. In the product\'s unit unless the Unit column says otherwise.', 'example' => '500', 'width' => 11],
            'unit' => ['label' => 'Unit', 'hint' => 'Optional. Leave empty for the product\'s own unit (pcs, kg, bag...). You can also write a pack name (Carton, Ton) or, for tiles, sqft — pieces are worked out (whole pieces).', 'example' => 'sqft', 'width' => 10],
            'cost_price' => ['label' => 'Buying price', 'hint' => 'Optional buying price of one unit of the product (one piece for tiles). Updates the product\'s buying price.', 'example' => '300', 'width' => 13],
        ];
    }

    public function examples(): array
    {
        return Product::query()->where('is_active', true)->with(['unit', 'units'])->orderBy('name')->limit(4)->get()
            ->map(fn (Product $product): array => [
                'sku' => $product->sku,
                'name' => $product->name,
                'quantity' => $product->isMeasured() ? 500 : 100,
                'unit' => $product->isMeasured() ? $product->measure_unit : null,
                'cost_price' => (float) $product->cost_price ?: null,
            ])->all() ?: [
                ['sku' => 'P000001', 'name' => 'Portland Cement 50kg', 'quantity' => 100],
                ['name' => 'Floor Tiles 24x24 Glossy', 'quantity' => 500, 'unit' => 'sqft'],
            ];
    }

    public function check(array $rows, Branch $branch, array $options = []): array
    {
        $mode = in_array($options['mode'] ?? 'add', self::MODES, true) ? $options['mode'] ?? 'add' : 'add';
        $canViewCost = (bool) Auth::user()?->can('products.view-cost');
        $products = Product::query()->with(['unit', 'units'])->get();
        $bySku = $products->filter(fn (Product $product): bool => filled($product->sku))->keyBy(fn (Product $product): string => self::normalize($product->sku));
        $byName = $products->groupBy(fn (Product $product): string => self::normalize($product->name));
        $barcodes = Barcode::query()->get(['code', 'product_id', 'product_unit_id'])->keyBy('code');
        $onHand = ProductStock::query()->where('branch_id', $branch->id)->pluck('quantity', 'product_id');
        $seen = [];
        $results = [];

        foreach ($rows as ['row' => $row, 'values' => $values]) {
            $values['quantity'] = str_replace([',', ' '], '', $values['quantity']);
            $values['cost_price'] = str_replace([',', ' '], '', $values['cost_price']);
            $label = $values['name'] ?: ($values['sku'] ?: ($values['barcode'] ?: __('Row :row', ['row' => $row])));
            $errors = $this->validateRow($values, [
                'sku' => ['nullable', 'string', 'required_without_all:barcode,name'],
                'barcode' => ['nullable', 'string'],
                'name' => ['nullable', 'string'],
                'quantity' => ['required', 'numeric', $mode === 'add' ? 'gt:0' : 'min:0'],
                'unit' => ['nullable', 'string'],
                'cost_price' => ['nullable', 'numeric', 'min:0'],
            ]);

            $product = null;
            $barcodeUnitId = null;

            if ($values['sku'] !== '') {
                $product = $bySku->get(self::normalize($values['sku']));

                if (! $product) {
                    $errors[] = __('No product with SKU :sku.', ['sku' => $values['sku']]);
                }
            } elseif ($values['barcode'] !== '') {
                $barcode = $barcodes->get($values['barcode']);
                $product = $barcode ? $products->firstWhere('id', $barcode->product_id) : null;
                $barcodeUnitId = $barcode?->product_unit_id;

                if (! $product) {
                    $errors[] = __('No product with barcode :code.', ['code' => $values['barcode']]);
                }
            } elseif ($values['name'] !== '') {
                $matches = $byName->get(self::normalize($values['name']), collect());

                if ($matches->count() > 1) {
                    $errors[] = __('More than one product is named ":name". Add its SKU to say which one.', ['name' => $values['name']]);
                } else {
                    $product = $matches->first();

                    if (! $product) {
                        $errors[] = __('No product named ":name". Add it with the Products import first.', ['name' => $values['name']]);
                    }
                }
            }

            if ($values['cost_price'] !== '' && ! $canViewCost) {
                $errors[] = __('You are not allowed to set buying prices. Leave the Buying price column empty.');
            }

            if ($product && isset($seen[$product->id])) {
                $errors[] = __('Same product as row :row.', ['row' => $seen[$product->id]]);
            }

            $pieces = null;
            $unitNote = '';

            if ($product && $errors === []) {
                [$pieces, $unitNote, $unitError] = $this->toBaseQuantity($product, (float) $values['quantity'], $values['unit'], $barcodeUnitId);

                if ($unitError) {
                    $errors[] = $unitError;
                }
            }

            if ($errors !== [] || ! $product) {
                $results[] = $this->result($row, 'error', $label, errors: $errors ?: [__('Product not found.')]);

                continue;
            }

            $seen[$product->id] = $row;
            $current = (float) ($onHand->get($product->id) ?? 0);
            $change = round($mode === 'set' ? $pieces - $current : $pieces, 3);
            $after = round($current + $change, 3);
            $detail = ($change > 0 ? '+' : '−').shop_digits(abs($change) + 0).' '.$product->unit?->short_name
                .($unitNote !== '' ? ' ('.$unitNote.')' : '')
                .' · '.__('Stock: :from → :to', ['from' => $product->stockLabel($current), 'to' => $product->stockLabel($after)]);

            $results[] = abs($change) < 0.0005
                ? $this->result($row, 'skip', $product->name, __('No change (:stock)', ['stock' => $product->stockLabel($current)]))
                : $this->result($row, 'update', $product->name, $detail, data: [
                    'product_id' => $product->id,
                    'change' => $change,
                    'cost_price' => $values['cost_price'],
                ]);
        }

        return $results;
    }

    public function apply(array $rows, Branch $branch, array $options = []): void
    {
        $mode = $options['mode'] ?? 'add';
        $changes = [];

        foreach ($rows as ['data' => $data]) {
            $product = Product::query()->findOrFail($data['product_id']);

            if ($data['cost_price'] !== '' && $mode === 'add' && $data['change'] > 0) {
                $this->stock->updateAverageCost($product, (float) $data['change'], (float) $data['cost_price']);
            } elseif ($data['cost_price'] !== '') {
                $product->update(['cost_price' => $data['cost_price']]);
            }

            $changes[$product->id] = (float) $data['change'];
        }

        if ($changes !== []) {
            $this->inventory->adjust($branch, AdjustmentReason::CountCorrection, $changes, $mode === 'set' ? __('Excel import: stock count') : __('Excel import: stock added'));
        }
    }

    /**
     * Turn the typed quantity into base units (pieces). Returns [quantity, note, error].
     *
     * @return array{0: float|null, 1: string, 2: string|null}
     */
    private function toBaseQuantity(Product $product, float $quantity, string $unitName, ?int $barcodeUnitId): array
    {
        $unitName = self::normalize($unitName);

        if ($unitName === '' && $barcodeUnitId) {
            $unit = $product->units->firstWhere('id', $barcodeUnitId);

            return [$quantity * (float) $unit->quantity, $unit->is_base ? '' : shop_digits($quantity + 0).' '.$unit->name, null];
        }

        if ($product->isMeasured() && $unitName !== '' && $unitName === self::normalize($product->measure_unit)) {
            $pieces = $product->piecesForMeasure($quantity);

            return [(float) $pieces, shop_digits($quantity + 0).' '.$product->measure_unit, null];
        }

        $unit = $unitName === ''
            ? $product->units->firstWhere('is_base', true)
            : $product->units->first(fn ($unit): bool => self::normalize($unit->name) === $unitName || self::normalize($unit->name_bn) === $unitName)
                ?? ($unitName === self::normalize($product->unit?->short_name) ? $product->units->firstWhere('is_base', true) : null);

        if (! $unit) {
            $allowed = $product->units->pluck('name')->push($product->unit?->short_name)->push($product->measure_unit)->filter()->unique()->implode(', ');

            return [null, '', __('Unit ":unit" is not used by this product. Use one of: :units.', ['unit' => $unitName, 'units' => $allowed])];
        }

        $pieces = $quantity * (float) $unit->quantity;

        if (! $product->unit?->allows_decimal && abs($pieces - round($pieces)) > 0.0001) {
            return [null, '', __(':unit can only be counted in whole numbers.', ['unit' => $product->unit?->name])];
        }

        return [$pieces, $unit->is_base ? '' : shop_digits($quantity + 0).' '.$unit->name, null];
    }
}
