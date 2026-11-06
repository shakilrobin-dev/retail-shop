<?php

namespace App\Livewire\Products;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Services\CategoryTree;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Categories (tree with unlimited levels), brands and base units.
 */
class CatalogSetup extends Component
{
    #[Url]
    public string $tab = 'categories';

    public bool $showCategoryForm = false;

    public ?int $editingCategoryId = null;

    public string $categoryName = '';

    public string $categoryNameBn = '';

    public ?int $categoryParentId = null;

    public string $categorySearch = '';

    public ?int $editingBrandId = null;

    public string $brandName = '';

    public string $unitName = '';

    public string $unitNameBn = '';

    public string $unitShortName = '';

    public bool $unitAllowsDecimal = false;

    public function mount(): void
    {
        $this->authorize('products.manage');
    }

    public function newCategory(?int $parentId = null): void
    {
        $this->reset('editingCategoryId', 'categoryName', 'categoryNameBn');
        $this->categoryParentId = $parentId;
        $this->resetValidation();
        $this->showCategoryForm = true;
    }

    public function editCategory(int $id): void
    {
        $category = Category::query()->findOrFail($id);
        $this->editingCategoryId = $category->id;
        $this->categoryName = $category->name;
        $this->categoryNameBn = (string) $category->name_bn;
        $this->categoryParentId = $category->parent_id;
        $this->resetValidation();
        $this->showCategoryForm = true;
    }

    public function saveCategory(CategoryTree $tree): void
    {
        $this->authorize('products.manage');

        $validated = $this->validate([
            'categoryName' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')
                ->where(fn ($query) => $this->categoryParentId ? $query->where('parent_id', $this->categoryParentId) : $query->whereNull('parent_id'))
                ->ignore($this->editingCategoryId)],
            'categoryNameBn' => ['nullable', 'string', 'max:255'],
            'categoryParentId' => ['nullable', 'exists:categories,id'],
        ], [], ['categoryName' => __('name'), 'categoryParentId' => __('parent category')]);

        if ($this->editingCategoryId && $tree->wouldLoop($this->editingCategoryId, $validated['categoryParentId'])) {
            $this->addError('categoryParentId', __('A category cannot be moved inside itself.'));

            return;
        }

        if ($validated['categoryParentId'] && $tree->depth($validated['categoryParentId']) >= CategoryTree::MAX_DEPTH - 1) {
            $this->addError('categoryParentId', __('Maximum :levels levels of categories.', ['levels' => CategoryTree::MAX_DEPTH]));

            return;
        }

        Category::query()->updateOrCreate(['id' => $this->editingCategoryId], [
            'name' => trim($validated['categoryName']),
            'name_bn' => $validated['categoryNameBn'] ?: null,
            'parent_id' => $validated['categoryParentId'],
        ]);

        $tree->forget();
        $this->dispatch('toast', message: __('Category saved.'));
        $this->reset('editingCategoryId', 'categoryName', 'categoryNameBn', 'showCategoryForm');
    }

    public function toggleCategory(int $id): void
    {
        $this->authorize('products.manage');
        $category = Category::query()->findOrFail($id);
        $category->update(['is_active' => ! $category->is_active]);
        app(CategoryTree::class)->forget();
    }

    public function deleteCategory(int $id, CategoryTree $tree): void
    {
        $this->authorize('products.manage');
        $category = Category::query()->findOrFail($id);

        if (Category::query()->where('parent_id', $id)->exists() || Product::withTrashed()->where('category_id', $id)->exists()) {
            $this->dispatch('toast', type: 'error', message: __('This category has sub-categories or products. Move them first, or hide the category.'));

            return;
        }

        $category->delete();
        $tree->forget();
        $this->dispatch('toast', message: __('Category deleted.'));
    }

    public function saveBrand(): void
    {
        $this->authorize('products.manage');

        $validated = $this->validate([
            'brandName' => ['required', 'string', 'max:255', Rule::unique('brands', 'name')->ignore($this->editingBrandId)],
        ], [], ['brandName' => __('name')]);

        Brand::query()->updateOrCreate(['id' => $this->editingBrandId], ['name' => trim($validated['brandName'])]);

        $this->dispatch('toast', message: __('Brand saved.'));
        $this->reset('editingBrandId', 'brandName');
    }

    public function editBrand(int $id): void
    {
        $brand = Brand::query()->findOrFail($id);
        $this->editingBrandId = $brand->id;
        $this->brandName = $brand->name;
    }

    public function toggleBrand(int $id): void
    {
        $this->authorize('products.manage');
        $brand = Brand::query()->findOrFail($id);
        $brand->update(['is_active' => ! $brand->is_active]);
    }

    public function saveUnit(): void
    {
        $this->authorize('products.manage');

        $validated = $this->validate([
            'unitName' => ['required', 'string', 'max:50'],
            'unitNameBn' => ['nullable', 'string', 'max:50'],
            'unitShortName' => ['required', 'string', 'max:10', 'unique:units,short_name'],
            'unitAllowsDecimal' => ['boolean'],
        ], [], ['unitName' => __('name'), 'unitShortName' => __('short name')]);

        Unit::query()->create([
            'name' => $validated['unitName'],
            'name_bn' => $validated['unitNameBn'] ?: null,
            'short_name' => $validated['unitShortName'],
            'allows_decimal' => $validated['unitAllowsDecimal'],
        ]);

        $this->dispatch('toast', message: __('Unit added.'));
        $this->reset('unitName', 'unitNameBn', 'unitShortName', 'unitAllowsDecimal');
    }

    public function render(): View
    {
        $tree = app(CategoryTree::class);
        $rows = $tree->flat();

        /** Product count per category, then rolled up so a parent includes its sub-categories. */
        $direct = Product::query()->selectRaw('category_id, count(*) as total')->groupBy('category_id')->pluck('total', 'category_id');
        $totals = $rows->mapWithKeys(fn (array $row): array => [
            $row['category']->id => collect($tree->withDescendants($row['category']->id))->sum(fn (int $id): int => (int) ($direct[$id] ?? 0)),
        ]);

        $search = mb_strtolower(trim($this->categorySearch));

        if ($search !== '') {
            $rows = $rows->filter(fn (array $row): bool => str_contains(mb_strtolower($row['path'].' '.$row['category']->name_bn), $search))->values();
        }

        return view('livewire.products.catalog-setup', [
            'rows' => $rows,
            'directCounts' => $direct,
            'totals' => $totals,
            'parentOptions' => $tree->flat(),
            'excludeFromParents' => $this->editingCategoryId ? $tree->withDescendants($this->editingCategoryId) : [],
            'brands' => Brand::query()->withCount('products')->orderBy('name')->get(),
            'units' => Unit::query()->withCount('products')->orderBy('id')->get(),
            'categoryCount' => Category::query()->count(),
        ])->title(__('Categories & brands'));
    }
}
