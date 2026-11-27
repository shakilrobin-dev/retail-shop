<?php

namespace App\Livewire\Concerns;

use App\Models\ProductUnit;
use App\Services\CurrentBranch;
use App\Services\StockService;

/**
 * The list of products on a sale or quotation: units (piece/box), quantities, prices
 * and products sold by measurement (tiles by sqft, worked out in whole pieces).
 */
trait ManagesCartLines
{
    /**
     * A line of a product sold by measurement (tiles by sqft) also has "measure" and "pieces":
     * then "quantity" is the measurement (e.g. 500 sqft), "pieces" the whole pieces given and "unit_price" the rate per measurement.
     *
     * @var list<array{product_unit_id: int, product_id: int, name: string, units: array<int, array{name: string, price: float, quantity: float}>, quantity: string, unit_price: string, discount_amount: string, stock: float, measure?: array{unit: string, per: float, rate: string}|null, pieces?: string}>
     */
    public array $cart = [];

    /**
     * May the price on a line be changed?
     */
    abstract public function canEditPrice(): bool;

    /**
     * Put a product unit on the list: one more of the same unit, or a new line on top.
     */
    public function addToCart(int $productUnitId): void
    {
        $unit = ProductUnit::query()->with('product.units')->findOrFail($productUnitId);

        if ($unit->product->isMeasured()) {
            $this->pickMeasured($unit);

            return;
        }

        foreach ($this->cart as $index => $line) {
            if ($line['product_unit_id'] === $unit->id) {
                $this->cart[$index]['quantity'] = (string) ((float) $line['quantity'] + 1);
                $this->moveLineToTop($index);

                return;
            }
        }

        $product = $unit->product;
        $branch = app(CurrentBranch::class)->get();

        array_unshift($this->cart, [
            'product_unit_id' => $unit->id,
            'product_id' => $product->id,
            'name' => $product->localName(),
            'units' => $product->units->mapWithKeys(fn (ProductUnit $u): array => [$u->id => [
                'name' => $u->localName(),
                'price' => (string) $u->selling_price,
                'quantity' => (float) $u->quantity,
            ]])->all(),
            'quantity' => '1',
            'unit_price' => (string) $unit->selling_price,
            'discount_amount' => '0',
            'stock' => app(StockService::class)->quantity($branch, $product),
        ]);
    }

    /**
     * A product sold by measurement goes on one line per product, counted in pieces of its base unit.
     * Picking a carton adds the pieces of a carton.
     */
    private function pickMeasured(ProductUnit $unit): void
    {
        $product = $unit->product;
        $addPieces = (float) $unit->quantity;

        foreach ($this->cart as $index => $line) {
            if ($line['product_id'] === $product->id && ! empty($line['measure'])) {
                $this->setPieces($index, (float) $line['pieces'] + $addPieces);
                $this->moveLineToTop($index);

                return;
            }
        }

        $baseUnit = $product->units->firstWhere('is_base', true) ?? $unit;
        $rate = $product->measure_price !== null
            ? (float) $product->measure_price
            : round((float) $baseUnit->selling_price / (float) $product->measure_per_unit, 2);

        array_unshift($this->cart, [
            'product_unit_id' => $baseUnit->id,
            'product_id' => $product->id,
            'name' => $product->localName(),
            'units' => $product->units->mapWithKeys(fn (ProductUnit $u): array => [$u->id => [
                'name' => $u->localName(),
                'price' => (string) $u->selling_price,
                'quantity' => (float) $u->quantity,
            ]])->all(),
            'measure' => ['unit' => (string) $product->measure_unit, 'per' => (float) $product->measure_per_unit, 'rate' => (string) $rate],
            'pieces' => (string) $addPieces,
            'quantity' => (string) round($addPieces * (float) $product->measure_per_unit, 3),
            'unit_price' => (string) $rate,
            'discount_amount' => '0',
            'stock' => app(StockService::class)->quantity(app(CurrentBranch::class)->get(), $product),
        ]);
    }

    /**
     * Set the whole pieces of a measured line; the measurement follows (283 pcs = 500.91 sqft).
     */
    private function setPieces(int $index, float $pieces): void
    {
        $pieces = max(0, (int) ceil(round($pieces, 6)));
        $this->cart[$index]['pieces'] = (string) $pieces;
        $this->cart[$index]['quantity'] = (string) round($pieces * $this->cart[$index]['measure']['per'], 3);
    }

    /**
     * Whole pieces needed to cover a measurement, e.g. 500 sqft ÷ 1.77 = 283 pieces.
     */
    private function piecesFor(array $line, float $measure): int
    {
        return $measure > 0 ? (int) ceil(round($measure / $line['measure']['per'], 6)) : 0;
    }

    /**
     * Pieces shown as cartons + pieces, e.g. "28 Carton + 3 Piece".
     *
     * @param  array<string, mixed>  $line
     */
    public function packBreakdown(array $line, float $pieces): string
    {
        $units = collect($line['units']);
        $base = $units->firstWhere('quantity', 1.0);
        $baseName = $base['name'] ?? '';
        $pack = $units->filter(fn (array $unit): bool => $unit['quantity'] > 1)->sortByDesc('quantity')->first();

        if (! $pack || $pieces < $pack['quantity']) {
            return shop_digits($pieces + 0).' '.$baseName;
        }

        $packs = (int) floor($pieces / $pack['quantity']);
        $rest = round($pieces - $packs * $pack['quantity'], 3);
        $text = shop_digits($packs).' '.$pack['name'];

        return $rest > 0 ? $text.' + '.shop_digits($rest + 0).' '.$baseName : $text;
    }

    /**
     * Unit (piece/box) changed on a cart line: use that unit's price.
     */
    public function updatedCart(mixed $value, string $key): void
    {
        [$index, $field] = explode('.', $key) + [1 => null];

        if (isset($this->cart[$index]) && ! empty($this->cart[$index]['measure'])) {
            $this->updatedMeasuredLine((int) $index, (string) $field, $value);

            return;
        }

        if ($field === 'product_unit_id' && isset($this->cart[$index])) {
            $unitId = (int) $value;
            $this->cart[$index]['product_unit_id'] = $unitId;
            $this->cart[$index]['unit_price'] = (string) ($this->cart[$index]['units'][$unitId]['price'] ?? $this->cart[$index]['unit_price']);
        }

        if ($field === 'unit_price' && ! $this->canEditPrice()) {
            $unitId = $this->cart[$index]['product_unit_id'];
            $this->cart[$index]['unit_price'] = (string) $this->cart[$index]['units'][$unitId]['price'];
        }
    }

    /**
     * Measurement typed → work out whole pieces. Pieces typed → work out the measurement.
     */
    private function updatedMeasuredLine(int $index, string $field, mixed $value): void
    {
        $line = $this->cart[$index];

        if ($field === 'quantity') {
            $this->cart[$index]['pieces'] = (string) $this->piecesFor($line, (float) $value);
        }

        if ($field === 'pieces') {
            $this->setPieces($index, (float) $value);
        }

        if ($field === 'unit_price' && ! $this->canEditPrice()) {
            $this->cart[$index]['unit_price'] = $line['measure']['rate'];
        }
    }

    public function increment(int $index): void
    {
        if (isset($this->cart[$index]) && ! empty($this->cart[$index]['measure'])) {
            $this->setPieces($index, (float) $this->cart[$index]['pieces'] + 1);

            return;
        }

        if (isset($this->cart[$index])) {
            $this->cart[$index]['quantity'] = (string) ((float) $this->cart[$index]['quantity'] + 1);
        }
    }

    public function decrement(int $index): void
    {
        if (isset($this->cart[$index]) && ! empty($this->cart[$index]['measure'])) {
            if ((float) $this->cart[$index]['pieces'] > 1) {
                $this->setPieces($index, (float) $this->cart[$index]['pieces'] - 1);
            }

            return;
        }

        if (isset($this->cart[$index]) && (float) $this->cart[$index]['quantity'] > 1) {
            $this->cart[$index]['quantity'] = (string) ((float) $this->cart[$index]['quantity'] - 1);
        }
    }

    public function removeLine(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    private function moveLineToTop(int $index): void
    {
        $line = $this->cart[$index];
        unset($this->cart[$index]);
        array_unshift($this->cart, $line);
        $this->cart = array_values($this->cart);
    }

    /**
     * Put a saved line back on the list (a held sale or a quotation).
     */
    protected function addSavedLine(int $productUnitId, float $quantity, float $unitPrice, ?float $measureRate, float $discount): void
    {
        $this->addToCart($productUnitId);

        if (! empty($this->cart[0]['measure'])) {
            $pieces = $quantity * (float) ($this->cart[0]['units'][$productUnitId]['quantity'] ?? 1);
            $this->setPieces(0, $pieces);
            $this->cart[0]['unit_price'] = (string) ($measureRate ?? $this->cart[0]['unit_price']);
        } else {
            $this->cart[0]['quantity'] = (string) $quantity;
            $this->cart[0]['unit_price'] = (string) $unitPrice;
        }

        $this->cart[0]['discount_amount'] = (string) $discount;
    }

    /**
     * Measured lines go to the service as whole pieces with their rate per measurement.
     *
     * @return list<array{product_unit_id: int, quantity: string, unit_price: string, discount_amount: string, measure_rate?: string}>
     */
    private function cartForService(): array
    {
        return array_map(function (array $line): array {
            $row = [
                'product_unit_id' => (int) $line['product_unit_id'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'discount_amount' => $line['discount_amount'] === '' ? '0' : $line['discount_amount'],
            ];

            if (! empty($line['measure'])) {
                $row['quantity'] = (string) $line['pieces'];
                $row['unit_price'] = (string) round((float) $line['unit_price'] * $line['measure']['per'], 2);
                $row['measure_rate'] = $line['unit_price'] === '' ? '0' : $line['unit_price'];
            }

            return $row;
        }, $this->cart);
    }
}
