<?php

namespace App\Livewire\Products;

use App\Models\Barcode;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Purchase;
use App\Services\BarcodeService;
use App\Services\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Choose products and how many labels to print for each.
 */
class LabelPrint extends Component
{
    public string $search = '';

    /** @var list<array{barcode_id: int, product: string, unit: string, code: string, price: string, quantity: int}> */
    public array $rows = [];

    #[Url]
    public ?int $product = null;

    #[Url]
    public ?int $purchase = null;

    public function mount(): void
    {
        $this->authorize('products.manage');

        if ($this->product) {
            $product = Product::query()->with('units.barcodes')->find($this->product);

            if ($product) {
                foreach ($product->units as $unit) {
                    foreach ($unit->barcodes as $barcode) {
                        $this->addRow($barcode, $unit, $product, 1);
                    }
                }
            }
        }

        if ($this->purchase) {
            $purchase = Purchase::query()->with('items.product.units.barcodes')->find($this->purchase);

            foreach ($purchase->items ?? [] as $item) {
                $unit = $item->product->units->firstWhere('id', $item->product_unit_id);
                $barcode = $unit?->barcodes->first() ?? $item->product->units->firstWhere('is_base', true)?->barcodes->first();

                if ($barcode && $unit) {
                    $this->addRow($barcode, $barcode->product_unit_id === $unit->id ? $unit : $item->product->units->firstWhere('id', $barcode->product_unit_id), $item->product, (int) ceil((float) $item->quantity));
                }
            }
        }
    }

    public function addBarcode(int $barcodeId): void
    {
        $barcode = Barcode::query()->with(['productUnit', 'product'])->findOrFail($barcodeId);
        $this->addRow($barcode, $barcode->productUnit, $barcode->product, 1);
        $this->search = '';
    }

    /**
     * Scanner pressed Enter in the search box: add the exact barcode if found.
     */
    public function addFromSearch(): void
    {
        $barcode = Barcode::query()->where('code', trim($this->search))->first();

        if ($barcode) {
            $this->addBarcode($barcode->id);
        }
    }

    /**
     * Make a barcode for a product that has none, then add it.
     */
    public function generateFor(int $productUnitId): void
    {
        $this->authorize('products.manage');

        $unit = ProductUnit::query()->findOrFail($productUnitId);
        $barcode = $unit->barcodes()->create([
            'product_id' => $unit->product_id,
            'code' => app(BarcodeService::class)->generate(),
            'is_generated' => true,
        ]);

        $this->addBarcode($barcode->id);
    }

    public function removeRow(int $index): void
    {
        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
    }

    private function addRow(Barcode $barcode, ?ProductUnit $unit, ?Product $product, int $quantity): void
    {
        foreach ($this->rows as $index => $row) {
            if ($row['barcode_id'] === $barcode->id) {
                $this->rows[$index]['quantity'] += $quantity;

                return;
            }
        }

        $this->rows[] = [
            'barcode_id' => $barcode->id,
            'product' => $product?->name ?? '',
            'unit' => $unit && ! $unit->is_base ? $unit->name : '',
            'code' => $barcode->code,
            'price' => money($unit?->selling_price),
            'quantity' => max(1, $quantity),
        ];
    }

    public function printUrl(): string
    {
        $items = collect($this->rows)
            ->filter(fn (array $row): bool => (int) $row['quantity'] > 0)
            ->map(fn (array $row): string => $row['barcode_id'].':'.min(500, (int) $row['quantity']))
            ->implode(',');

        return route('labels.print', ['items' => $items]);
    }

    public function render(): View
    {
        /** @var Collection<int, Product> $results */
        $results = new Collection;

        if (strlen(trim($this->search)) >= 2) {
            $term = trim($this->search);
            $results = Product::query()
                ->with('units.barcodes')
                ->where('is_active', true)
                ->where(fn (Builder $query) => $query->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', $term)
                    ->orWhereHas('barcodes', fn (Builder $query) => $query->where('code', $term)))
                ->limit(10)
                ->get();
        }

        return view('livewire.products.label-print', [
            'results' => $results,
            'labelOutput' => app(Settings::class)->get('label_output'),
            'labelSize' => app(Settings::class)->get('label_size'),
            'labelLayout' => app(Settings::class)->get('label_a4_layout'),
        ])->title(__('Print barcode labels'));
    }
}
