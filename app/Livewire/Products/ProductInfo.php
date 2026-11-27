<?php

namespace App\Livewire\Products;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Services\CurrentBranch;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Pop-up with a product's stock and prices (buying, wholesale, retail).
 * Open it from anywhere with: $dispatch('show-product-info', { productId: 1 }).
 */
class ProductInfo extends Component
{
    public ?int $productId = null;

    public bool $open = false;

    #[On('show-product-info')]
    public function show(int $productId): void
    {
        $this->productId = $productId;
        $this->open = true;
    }

    public function render(): View
    {
        $product = $this->open && $this->productId
            ? Product::query()->with(['units' => fn ($query) => $query->orderByDesc('is_base')->orderBy('quantity'), 'category', 'brand'])->find($this->productId)
            : null;

        $branchId = app(CurrentBranch::class)->get()?->id;

        /** @var User|null $user */
        $user = Auth::user();

        return view('livewire.products.product-info', [
            'product' => $product,
            'branchStock' => $product ? (float) ProductStock::query()->where('product_id', $product->id)->where('branch_id', $branchId)->value('quantity') : 0.0,
            'allBranchesStock' => $product ? (float) ProductStock::query()->where('product_id', $product->id)->sum('quantity') : 0.0,
            'canViewCost' => (bool) $user?->can('products.view-cost'),
            'canManage' => (bool) $user?->can('products.manage'),
        ]);
    }
}
