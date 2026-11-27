<?php

namespace App\Livewire\Concerns;

use App\Models\Barcode;
use App\Models\Product;
use App\Models\ProductStock;
use App\Services\CurrentBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;

/**
 * Shared "scan barcode or search product" box.
 *
 * The component using this must implement pickUnit(int $productUnitId).
 * A scanner types the code and presses Enter, which calls scan().
 */
trait SearchesProducts
{
    public string $productSearch = '';

    public string $scanMessage = '';

    /** Last scanned code or name that matched nothing (offered as a new product). */
    public string $missingProductTerm = '';

    abstract public function pickUnit(int $productUnitId): void;

    /**
     * Enter pressed in the search box: exact barcode/SKU first, else the only search result.
     */
    public function scan(): void
    {
        $code = trim($this->productSearch);
        $this->scanMessage = '';
        $this->missingProductTerm = '';

        if ($code === '') {
            return;
        }

        $barcode = Barcode::query()
            ->where('code', $code)
            ->whereHas('product', fn (Builder $query) => $query->where('is_active', true))
            ->first();

        if ($barcode) {
            $this->productSearch = '';
            $this->pickUnit($barcode->product_unit_id);

            return;
        }

        $results = $this->productResults();

        if ($results->count() === 1 && $results->first()->units->count() === 1) {
            $this->productSearch = '';
            $this->pickUnit($results->first()->units->first()->id);

            return;
        }

        if ($results->isEmpty()) {
            $this->scanMessage = __('No product found for ":code".', ['code' => $code]);
            $this->missingProductTerm = $code;
            $this->productSearch = '';
            $this->dispatch('scan-not-found');
        }
    }

    public function pickFromSearch(int $productUnitId): void
    {
        $this->productSearch = '';
        $this->scanMessage = '';
        $this->missingProductTerm = '';
        $this->pickUnit($productUnitId);
    }

    /**
     * Tiles etc. added from the search by typing the measurement, e.g. 100 sqft.
     * Screens that support it implement pickMeasure(); others just add one unit.
     */
    public function pickFromSearchMeasure(int $productUnitId, mixed $measure): void
    {
        if (! is_numeric($measure) || (float) $measure <= 0) {
            return;
        }

        $this->productSearch = '';
        $this->scanMessage = '';
        $this->missingProductTerm = '';

        if (method_exists($this, 'pickMeasure')) {
            $this->pickMeasure($productUnitId, (float) $measure);

            return;
        }

        $this->pickUnit($productUnitId);
    }

    /**
     * A product was just made in the quick "add product" pop-up: put it on this screen's list.
     */
    #[On('quick-product-created')]
    public function addQuickCreatedProduct(int $productUnitId): void
    {
        $this->pickFromSearch($productUnitId);
    }

    /**
     * @return Collection<int, Product>
     */
    public function productResults(): Collection
    {
        $term = trim($this->productSearch);

        if (mb_strlen($term) < 2) {
            return new Collection;
        }

        $branchId = app(CurrentBranch::class)->get()?->id;

        return Product::query()
            ->with('units')
            ->addSelect(['products.*', 'branch_stock' => ProductStock::query()
                ->select('quantity')
                ->whereColumn('product_stocks.product_id', 'products.id')
                ->where('product_stocks.branch_id', $branchId)
                ->limit(1),
            ])
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$term}%")
                ->orWhere('name_bn', 'like', "%{$term}%")
                ->orWhere('compatible_models', 'like', "%{$term}%")
                ->orWhere('sku', $term)
                ->orWhereHas('barcodes', fn (Builder $query) => $query->where('code', $term)))
            ->orderBy('name')
            ->limit(12)
            ->get();
    }
}
