<?php

namespace App\Livewire\Products;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductStock;
use App\Services\CategoryTree;
use App\Services\CurrentBranch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ProductIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public ?int $categoryId = null;

    #[Url]
    public ?int $brandId = null;

    #[Url]
    public string $stockFilter = '';

    #[Url]
    public bool $showInactive = false;

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'categoryId', 'brandId', 'stockFilter', 'showInactive'], true)) {
            $this->resetPage();
        }
    }

    public function toggleActive(int $productId): void
    {
        $this->authorize('products.manage');

        $product = Product::query()->findOrFail($productId);
        $product->update(['is_active' => ! $product->is_active]);
    }

    public function render(): View
    {
        $branchId = app(CurrentBranch::class)->get()?->id;

        $products = Product::query()
            ->with(['category', 'brand', 'units.barcodes'])
            ->addSelect(['products.*', 'branch_stock' => ProductStock::query()
                ->select('quantity')
                ->whereColumn('product_stocks.product_id', 'products.id')
                ->where('product_stocks.branch_id', $branchId)
                ->limit(1),
            ])
            ->when(! $this->showInactive, fn (Builder $query) => $query->where('is_active', true))
            ->when($this->search !== '', function (Builder $query): void {
                $term = trim($this->search);
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('name', 'like', "%{$term}%")
                        ->orWhere('name_bn', 'like', "%{$term}%")
                        ->orWhere('sku', $term)
                        ->orWhere('compatible_models', 'like', "%{$term}%")
                        ->orWhereHas('barcodes', fn (Builder $query) => $query->where('code', $term));
                });
            })
            ->when($this->categoryId, fn (Builder $query) => $query->whereIn('category_id', app(CategoryTree::class)->withDescendants($this->categoryId)))
            ->when($this->brandId, fn (Builder $query) => $query->where('brand_id', $this->brandId))
            ->when($this->stockFilter === 'low', fn (Builder $query) => $query->where('reorder_level', '>', 0)
                ->whereRaw('COALESCE((select quantity from product_stocks where product_stocks.product_id = products.id and product_stocks.branch_id = ?), 0) <= products.reorder_level', [$branchId]))
            ->when($this->stockFilter === 'out', fn (Builder $query) => $query
                ->whereRaw('COALESCE((select quantity from product_stocks where product_stocks.product_id = products.id and product_stocks.branch_id = ?), 0) <= 0', [$branchId]))
            ->orderBy('name')
            ->paginate(25);

        return view('livewire.products.product-index', [
            'products' => $products,
            'categoryOptions' => app(CategoryTree::class)->flat(),
            'brands' => Brand::query()->orderBy('name')->get(),
        ])->title(__('Products'));
    }
}
