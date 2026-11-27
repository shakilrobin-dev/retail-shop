<?php

namespace App\Livewire\Purchases;

use App\Livewire\Concerns\SearchesProducts;
use App\Models\PaymentMethod;
use App\Models\ProductUnit;
use App\Models\Supplier;
use App\Services\CurrentBranch;
use App\Services\PurchaseService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class PurchaseForm extends Component
{
    use SearchesProducts;

    public ?int $supplier_id = null;

    public string $supplier_invoice_no = '';

    public string $purchase_date = '';

    public string $discount = '0';

    public string $note = '';

    public ?int $payment_method_id = null;

    public string $transaction_ref = '';

    public bool $paid_from_drawer = false;

    /**
     * A product bought by measurement (tiles by sqft) also has "measure" and "pieces": then "quantity" is the
     * measurement (e.g. 500 sqft), "pieces" the whole pieces, "unit_cost" the cost per sqft and the prices are per sqft.
     *
     * @var list<array{product_id: int, product_unit_id: int, name: string, units: array<int, string>, unit_quantities: array<int, float>, quantity: string, unit_cost: string, selling_price: string, new_selling_price: string, measure?: array{unit: string, per: float}, pieces?: string}>
     */
    public array $items = [];

    public function mount(): void
    {
        $this->purchase_date = now()->toDateString();
        $this->payment_method_id = PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->value('id');
    }

    public function pickUnit(int $productUnitId): void
    {
        $unit = ProductUnit::query()->with('product.units')->findOrFail($productUnitId);

        if ($unit->product->isMeasured()) {
            $this->pickMeasured($unit);

            return;
        }

        foreach ($this->items as $index => $item) {
            if ($item['product_unit_id'] === $unit->id) {
                $this->items[$index]['quantity'] = (string) ((float) $item['quantity'] + 1);

                return;
            }
        }

        $product = $unit->product;

        $this->items[] = [
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'name' => $product->name,
            'units' => $product->units->mapWithKeys(fn ($u) => [$u->id => $u->name])->all(),
            'unit_quantities' => $product->units->mapWithKeys(fn ($u) => [$u->id => (float) $u->quantity])->all(),
            'quantity' => '1',
            'unit_cost' => (string) round((float) $product->cost_price * (float) $unit->quantity, 2),
            'selling_price' => (string) $unit->selling_price,
            'new_selling_price' => '',
        ];
    }

    /**
     * Tiles etc. go on one line per product, counted in whole pieces; a carton adds its pieces.
     */
    private function pickMeasured(ProductUnit $unit): void
    {
        $product = $unit->product;
        $per = (float) $product->measure_per_unit;

        foreach ($this->items as $index => $item) {
            if ($item['product_id'] === $product->id && ! empty($item['measure'])) {
                $this->setPieces($index, (float) $item['pieces'] + (float) $unit->quantity);

                return;
            }
        }

        $baseUnit = $product->units->firstWhere('is_base', true) ?? $unit;
        $pieces = (float) $unit->quantity;

        $this->items[] = [
            'product_id' => $product->id,
            'product_unit_id' => $baseUnit->id,
            'name' => $product->name,
            'units' => $product->units->mapWithKeys(fn ($u) => [$u->id => $u->name])->all(),
            'unit_quantities' => $product->units->mapWithKeys(fn ($u) => [$u->id => (float) $u->quantity])->all(),
            'measure' => ['unit' => (string) $product->measure_unit, 'per' => $per],
            'pieces' => (string) $pieces,
            'quantity' => (string) round($pieces * $per, 3),
            'unit_cost' => (string) round((float) $product->cost_price / $per, 2),
            'selling_price' => (string) ($product->measure_price ?? round((float) $baseUnit->selling_price / $per, 2)),
            'new_selling_price' => '',
        ];
    }

    /**
     * Whole pieces on a measured line; the sqft follows.
     */
    private function setPieces(int $index, float $pieces): void
    {
        $pieces = max(0, (int) ceil(round($pieces, 6)));
        $this->items[$index]['pieces'] = (string) $pieces;
        $this->items[$index]['quantity'] = (string) round($pieces * $this->items[$index]['measure']['per'], 3);
    }

    /**
     * Pieces shown as cartons + pieces, e.g. "28 Carton + 3 Piece".
     *
     * @param  array<string, mixed>  $item
     */
    public function packBreakdown(array $item, float $pieces): string
    {
        $packQuantity = collect($item['unit_quantities'])->filter(fn (float $quantity): bool => $quantity > 1)->sortDesc();
        $packId = $packQuantity->keys()->first();
        $baseName = $item['units'][$item['product_unit_id']] ?? '';

        if ($packId === null || $pieces < $packQuantity->first()) {
            return shop_digits($pieces + 0).' '.$baseName;
        }

        $packs = (int) floor($pieces / $packQuantity->first());
        $rest = round($pieces - $packs * $packQuantity->first(), 3);
        $text = shop_digits($packs).' '.$item['units'][$packId];

        return $rest > 0 ? $text.' + '.shop_digits($rest + 0).' '.$baseName : $text;
    }

    /**
     * Total of one line (measured lines: whole pieces × sqft per piece × rate).
     *
     * @param  array<string, mixed>  $item
     */
    public function lineTotal(array $item): float
    {
        if (! empty($item['measure'])) {
            return round(round((float) $item['pieces'] * $item['measure']['per'], 3) * (float) $item['unit_cost'], 2);
        }

        return round((float) $item['quantity'] * (float) $item['unit_cost'], 2);
    }

    /**
     * When the unit (piece/box) of a line changes, adjust the cost suggestion.
     * On a measured line, typing sqft works out pieces and typing pieces works out sqft.
     */
    public function updatedItems(mixed $value, string $key): void
    {
        [$index, $field] = explode('.', $key) + [1 => null];

        if (isset($this->items[$index]) && ! empty($this->items[$index]['measure'])) {
            if ($field === 'quantity') {
                $per = $this->items[$index]['measure']['per'];
                $this->items[$index]['pieces'] = (string) ((float) $value > 0 ? (int) ceil(round((float) $value / $per, 6)) : 0);
            }

            if ($field === 'pieces') {
                $this->setPieces((int) $index, (float) $value);
            }

            return;
        }

        if ($field !== 'product_unit_id' || ! isset($this->items[$index])) {
            return;
        }

        $unit = ProductUnit::query()->with('product')->find((int) $value);

        if ($unit) {
            $this->items[$index]['product_unit_id'] = $unit->id;
            $this->items[$index]['unit_cost'] = (string) round((float) $unit->product->cost_price * (float) $unit->quantity, 2);
            $this->items[$index]['selling_price'] = (string) $unit->selling_price;
        }
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function subtotal(): float
    {
        return round(collect($this->items)->sum(fn (array $item): float => $this->lineTotal($item)), 2);
    }

    public function total(): float
    {
        return max(0, round($this->subtotal() - (float) $this->discount, 2));
    }

    public function save(PurchaseService $purchases, CurrentBranch $currentBranch): void
    {
        $this->authorize('purchases.manage');

        $this->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'supplier_invoice_no' => ['nullable', 'string', 'max:50'],
            'purchase_date' => ['required', 'date', 'before_or_equal:today'],
            'discount' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:2000'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'transaction_ref' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.new_selling_price' => ['nullable', 'numeric', 'min:0'],
        ], [], [
            'items.*.quantity' => __('quantity'),
            'items.*.unit_cost' => __('cost'),
            'items' => __('products'),
        ]);

        $purchase = $purchases->create(
            $currentBranch->get(),
            [
                'supplier_id' => $this->supplier_id,
                'supplier_invoice_no' => $this->supplier_invoice_no,
                'purchase_date' => $this->purchase_date,
                'discount' => $this->discount,
                'note' => $this->note,
            ],
            array_map(fn (array $item): array => empty($item['measure']) ? [
                'product_unit_id' => (int) $item['product_unit_id'],
                'quantity' => $item['quantity'],
                'unit_cost' => $item['unit_cost'],
                'new_selling_price' => $item['new_selling_price'],
            ] : [
                'product_unit_id' => (int) $item['product_unit_id'],
                'quantity' => $item['pieces'],
                'unit_cost' => round((float) $item['unit_cost'] * $item['measure']['per'], 2),
                'measure_rate' => $item['unit_cost'],
                'new_measure_price' => $item['new_selling_price'],
            ], $this->items),
            PaymentMethod::query()->findOrFail($this->payment_method_id),
            $this->transaction_ref,
            $this->paid_from_drawer,
        );

        session()->flash('success', __('Purchase :no saved. Stock updated.', ['no' => $purchase->reference_no]));
        $this->redirectRoute('purchases.show', $purchase, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.purchases.purchase-form', [
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'results' => $this->productResults(),
        ])->title(__('New purchase'));
    }
}
