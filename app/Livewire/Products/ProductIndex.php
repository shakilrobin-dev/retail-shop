<?php

namespace App\Livewire\Products;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductUnit;
use App\Services\CategoryTree;
use App\Services\CurrentBranch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductIndex extends Component
{
    use WithDataTable;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public ?int $categoryId = null;

    #[Url]
    public ?int $brandId = null;

    /** '' = active products, 'low', 'out', 'inactive' */
    #[Url]
    public string $stockFilter = '';

    public ?int $bulkCategoryId = null;

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'categoryId', 'brandId', 'stockFilter'], true)) {
            $this->resetPage();
            $this->selected = [];
        }
    }

    /**
     * @return array<string, string>
     */
    protected function sortableColumns(): array
    {
        return [
            'name' => 'products.name',
            'sku' => 'products.sku',
            'price' => 'base_price',
            'cost' => 'products.cost_price',
            'stock' => 'branch_stock',
        ];
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'categoryId', 'brandId');
        $this->resetPage();
    }

    public function toggleActive(int $productId): void
    {
        $this->authorize('products.manage');

        $product = Product::query()->findOrFail($productId);
        $product->update(['is_active' => ! $product->is_active]);
    }

    public function bulkSetActive(bool $active): void
    {
        $this->authorize('products.manage');

        Product::query()->whereIn('id', $this->selected)->get()->each->update(['is_active' => $active]);
        $this->dispatch('toast', message: trans_choice(':count product updated.|:count products updated.', count($this->selected), ['count' => count($this->selected)]));
        $this->selected = [];
    }

    public function bulkMoveCategory(): void
    {
        $this->authorize('products.manage');
        $this->validate(['bulkCategoryId' => ['required', 'exists:categories,id']], [], ['bulkCategoryId' => __('category')]);

        Product::query()->whereIn('id', $this->selected)->get()->each->update(['category_id' => $this->bulkCategoryId]);
        $this->dispatch('toast', message: __('Moved to :category.', ['category' => app(CategoryTree::class)->path($this->bulkCategoryId)]));
        $this->reset('selected', 'bulkCategoryId');
    }

    public function bulkPrintLabels(): void
    {
        $this->redirectRoute('labels.index', ['products' => implode(',', $this->selected)], navigate: true);
    }

    public function export(): StreamedResponse
    {
        $tree = app(CategoryTree::class);
        $query = $this->query()->with(['category', 'brand', 'units.barcodes']);

        if ($this->selected !== []) {
            $query->whereIn('products.id', $this->selected);
        }

        $rows = $query->get()->map(fn (Product $product): array => [
            $product->sku,
            $product->name,
            $product->name_bn,
            $tree->path($product->category_id),
            $product->brand?->name,
            $product->units->flatMap->barcodes->pluck('code')->implode(' '),
            $product->base_price,
            $product->cost_price,
            (float) $product->branch_stock,
            $product->is_active ? 'Active' : 'Archived',
        ]);

        return $this->csvDownload('products', ['SKU', 'Name', 'Name (Bangla)', 'Category', 'Brand', 'Barcodes', 'Price', 'Cost', 'Stock', 'Status'], $rows);
    }

    /**
     * @return Builder<Product>
     */
    private function query(): Builder
    {
        $branchId = app(CurrentBranch::class)->get()?->id;
        $stockSql = 'COALESCE((select quantity from product_stocks where product_stocks.product_id = products.id and product_stocks.branch_id = ?), 0)';

        return Product::query()
            ->addSelect(['products.*',
                'branch_stock' => ProductStock::query()->select('quantity')
                    ->whereColumn('product_stocks.product_id', 'products.id')
                    ->where('product_stocks.branch_id', $branchId)
                    ->limit(1),
                'base_price' => ProductUnit::query()->select('selling_price')
                    ->whereColumn('product_units.product_id', 'products.id')
                    ->where('is_base', true)
                    ->limit(1),
            ])
            ->when($this->stockFilter === 'inactive', fn (Builder $query) => $query->where('is_active', false), fn (Builder $query) => $query->where('is_active', true))
            ->when($this->search !== '', function (Builder $query): void {
                $term = trim($this->search);
                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('name_bn', 'like', "%{$term}%")
                    ->orWhere('sku', $term)
                    ->orWhere('compatible_models', 'like', "%{$term}%")
                    ->orWhereHas('barcodes', fn (Builder $query) => $query->where('code', $term)));
            })
            ->when($this->categoryId, fn (Builder $query) => $query->whereIn('category_id', app(CategoryTree::class)->withDescendants($this->categoryId)))
            ->when($this->brandId, fn (Builder $query) => $query->where('brand_id', $this->brandId))
            ->when($this->stockFilter === 'low', fn (Builder $query) => $query->where('reorder_level', '>', 0)->whereRaw("{$stockSql} > 0 and {$stockSql} <= products.reorder_level", [$branchId, $branchId]))
            ->when($this->stockFilter === 'out', fn (Builder $query) => $query->whereRaw("{$stockSql} <= 0", [$branchId]));
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    private function tabCounts(): array
    {
        $count = function (string $filter): int {
            $previous = $this->stockFilter;
            $this->stockFilter = $filter;
            $total = $this->query()->toBase()->getCountForPagination();
            $this->stockFilter = $previous;

            return $total;
        };

        return [
            '' => [__('All products'), $count('')],
            'low' => [__('Low stock'), $count('low')],
            'out' => [__('Out of stock'), $count('out')],
            'inactive' => [__('Archived'), $count('inactive')],
        ];
    }

    public function render(): View
    {
        $products = $this->applySorting($this->query()->with(['category', 'brand', 'units.barcodes']), 'name')
            ->paginate($this->perPage);

        return view('livewire.products.product-index', [
            'products' => $products,
            'tabs' => $this->tabCounts(),
            'categoryOptions' => app(CategoryTree::class)->flat(),
            'brands' => Brand::query()->orderBy('name')->get(),
            'hasCategories' => Category::query()->exists(),
        ])->title(__('Products'));
    }
}
