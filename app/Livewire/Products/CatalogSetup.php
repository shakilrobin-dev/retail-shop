<?php

namespace App\Livewire\Products;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Unit;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * One page to manage categories, brands and base units.
 */
class CatalogSetup extends Component
{
    public ?int $editingCategoryId = null;

    public string $categoryName = '';

    public string $categoryNameBn = '';

    public ?int $categoryParentId = null;

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

    public function saveCategory(): void
    {
        $this->authorize('products.manage');

        $validated = $this->validate([
            'categoryName' => ['required', 'string', 'max:255'],
            'categoryNameBn' => ['nullable', 'string', 'max:255'],
            'categoryParentId' => ['nullable', 'exists:categories,id', Rule::notIn(array_filter([$this->editingCategoryId]))],
        ]);

        Category::query()->updateOrCreate(['id' => $this->editingCategoryId], [
            'name' => $validated['categoryName'],
            'name_bn' => $validated['categoryNameBn'] ?: null,
            'parent_id' => $validated['categoryParentId'],
        ]);

        $this->reset('editingCategoryId', 'categoryName', 'categoryNameBn', 'categoryParentId');
    }

    public function editCategory(int $id): void
    {
        $category = Category::query()->findOrFail($id);
        $this->editingCategoryId = $category->id;
        $this->categoryName = $category->name;
        $this->categoryNameBn = (string) $category->name_bn;
        $this->categoryParentId = $category->parent_id;
    }

    public function toggleCategory(int $id): void
    {
        $this->authorize('products.manage');
        $category = Category::query()->findOrFail($id);
        $category->update(['is_active' => ! $category->is_active]);
    }

    public function saveBrand(): void
    {
        $this->authorize('products.manage');

        $validated = $this->validate([
            'brandName' => ['required', 'string', 'max:255', Rule::unique('brands', 'name')->ignore($this->editingBrandId)],
        ]);

        Brand::query()->updateOrCreate(['id' => $this->editingBrandId], ['name' => $validated['brandName']]);

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
        ]);

        Unit::query()->create([
            'name' => $validated['unitName'],
            'name_bn' => $validated['unitNameBn'] ?: null,
            'short_name' => $validated['unitShortName'],
            'allows_decimal' => $validated['unitAllowsDecimal'],
        ]);

        $this->reset('unitName', 'unitNameBn', 'unitShortName', 'unitAllowsDecimal');
    }

    public function render(): View
    {
        return view('livewire.products.catalog-setup', [
            'categories' => Category::query()->withCount('products')->with('parent')->orderBy('parent_id')->orderBy('name')->get(),
            'parentOptions' => Category::query()->whereNull('parent_id')->orderBy('name')->get(),
            'brands' => Brand::query()->withCount('products')->orderBy('name')->get(),
            'units' => Unit::query()->orderBy('id')->get(),
        ])->title(__('Categories & brands'));
    }
}
