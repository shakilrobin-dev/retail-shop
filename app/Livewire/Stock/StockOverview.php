<?php

namespace App\Livewire\Stock;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Product;
use App\Models\ProductStock;
use App\Services\CategoryTree;
use App\Services\CurrentBranch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockOverview extends Component
{
    use WithDataTable;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public ?int $categoryId = null;

    #[Url]
    public string $filter = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<string, string>
     */
    protected function sortableColumns(): array
    {
        return ['name' => 'products.name', 'stock' => 'branch_stock', 'alert' => 'products.reorder_level', 'value' => 'branch_stock * products.cost_price'];
    }

    public function export(): StreamedResponse
    {
        $tree = app(CategoryTree::class);
        $rows = $this->stockQuery()->orderBy('name')->get()->map(fn (Product $product): array => [
            $product->sku, $product->name, $tree->path($product->category_id), (float) $product->branch_stock, (float) $product->reorder_level, (float) $product->cost_price, round(max(0, (float) $product->branch_stock) * (float) $product->cost_price, 2),
        ]);

        return $this->csvDownload('stock', ['SKU', 'Product', 'Category', 'Stock', 'Alert at', 'Cost', 'Value'], $rows);
    }

    /**
     * @return Builder<Product>
     */
    private function stockQuery(): Builder
    {
        $branchId = app(CurrentBranch::class)->get()?->id;
        $stockSql = '(select quantity from product_stocks where product_stocks.product_id = products.id and product_stocks.branch_id = ?)';

        $query = Product::query()
            ->with(['units', 'category'])
            ->addSelect(['products.*', 'branch_stock' => ProductStock::query()->select('quantity')
                ->whereColumn('product_stocks.product_id', 'products.id')->where('product_stocks.branch_id', $branchId)->limit(1)])
            ->where('is_active', true)
            ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$this->search}%")->orWhere('sku', $this->search)
                ->orWhereHas('barcodes', fn (Builder $query) => $query->where('code', $this->search))))
            ->when($this->categoryId, fn (Builder $query) => $query->whereIn('category_id', app(CategoryTree::class)->withDescendants($this->categoryId)))
            ->when($this->filter === 'low', fn (Builder $query) => $query->where('reorder_level', '>', 0)->whereRaw("COALESCE({$stockSql}, 0) <= products.reorder_level", [$branchId]))
            ->when($this->filter === 'out', fn (Builder $query) => $query->whereRaw("COALESCE({$stockSql}, 0) <= 0", [$branchId]));

        return $query;
    }

    public function render(): View
    {
        $branchId = app(CurrentBranch::class)->get()?->id;
        $query = $this->stockQuery();

        $value = (float) ProductStock::query()
            ->join('products', 'products.id', '=', 'product_stocks.product_id')
            ->where('product_stocks.branch_id', $branchId)
            ->where('product_stocks.quantity', '>', 0)
            ->selectRaw('SUM(product_stocks.quantity * products.cost_price) as value')
            ->value('value');

        return view('livewire.stock.stock-overview', [
            'products' => $this->applySorting($query, 'name')->paginate($this->perPage),
            'stockValue' => $value,
            'stockUnits' => (float) ProductStock::query()->where('branch_id', $branchId)->where('quantity', '>', 0)->sum('quantity'),
            'lowCount' => Product::query()->where('is_active', true)->where('reorder_level', '>', 0)
                ->whereRaw('COALESCE((select quantity from product_stocks where product_stocks.product_id = products.id and product_stocks.branch_id = ?), 0) between 0.001 and products.reorder_level', [$branchId])->count(),
            'outCount' => Product::query()->where('is_active', true)
                ->whereRaw('COALESCE((select quantity from product_stocks where product_stocks.product_id = products.id and product_stocks.branch_id = ?), 0) <= 0', [$branchId])->count(),
            'categoryOptions' => app(CategoryTree::class)->flat(),
        ])->title(__('Stock'));
    }
}
