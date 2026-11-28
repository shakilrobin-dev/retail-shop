<?php

namespace App\Livewire\Products;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Unit;
use App\Services\CategoryTree;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Pop-up to add a category, sub-category, brand or unit while filling in a product.
 * The form that opened it gets the new item chosen (event "catalog-item-added").
 */
class CatalogQuickAdd extends Component
{
    private const TYPES = ['category', 'subcategory', 'brand', 'unit'];

    public bool $open = false;

    public string $type = 'category';

    /** Name of the Livewire form that asked for the pop-up. */
    public string $target = '';

    public ?int $parentId = null;

    public string $name = '';

    public string $name_bn = '';

    public string $short_name = '';

    public bool $allows_decimal = false;

    #[On('open-catalog-quick-add')]
    public function openForm(string $type, ?int $parentId = null, string $target = ''): void
    {
        $this->authorize('products.manage');

        $this->resetValidation();
        $this->reset('name', 'name_bn', 'short_name', 'allows_decimal');
        $this->type = in_array($type, self::TYPES, true) ? $type : 'category';
        $this->parentId = $this->type === 'subcategory' ? $parentId : null;
        $this->target = $target;
        $this->open = true;
    }

    public function save(): void
    {
        $this->authorize('products.manage');

        $id = match ($this->type) {
            'category', 'subcategory' => $this->saveCategory(),
            'brand' => $this->saveBrand(),
            'unit' => $this->saveUnit(),
        };

        $this->open = false;
        $this->dispatch('toast', message: __(':name added.', ['name' => trim($this->name)]));
        $this->dispatch('catalog-item-added', type: $this->type, id: $id, target: $this->target);
    }

    private function saveCategory(): int
    {
        $isSub = $this->type === 'subcategory';

        $this->validate([
            'parentId' => $isSub ? ['required', Rule::exists('categories', 'id')->whereNull('parent_id')] : ['nullable'],
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')
                ->where(fn ($query) => $isSub ? $query->where('parent_id', $this->parentId) : $query->whereNull('parent_id'))],
            'name_bn' => ['nullable', 'string', 'max:255'],
        ], [], ['parentId' => __('main category'), 'name' => __('name')]);

        $category = Category::query()->create([
            'parent_id' => $isSub ? $this->parentId : null,
            'name' => trim($this->name),
            'name_bn' => trim($this->name_bn) ?: null,
            'is_active' => true,
        ]);
        app(CategoryTree::class)->forget();

        return $category->id;
    }

    private function saveBrand(): int
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255', 'unique:brands,name'],
        ], [], ['name' => __('name')]);

        return Brand::query()->create(['name' => trim($this->name), 'is_active' => true])->id;
    }

    private function saveUnit(): int
    {
        $this->validate([
            'name' => ['required', 'string', 'max:50'],
            'name_bn' => ['nullable', 'string', 'max:50'],
            'short_name' => ['required', 'string', 'max:10', 'unique:units,short_name'],
            'allows_decimal' => ['boolean'],
        ], [], ['name' => __('name'), 'short_name' => __('short name')]);

        return Unit::query()->create([
            'name' => trim($this->name),
            'name_bn' => trim($this->name_bn) ?: null,
            'short_name' => trim($this->short_name),
            'allows_decimal' => $this->allows_decimal,
        ])->id;
    }

    public function render(): View
    {
        $titles = [
            'category' => __('New category'),
            'subcategory' => __('New sub-category'),
            'brand' => __('New brand'),
            'unit' => __('New unit'),
        ];

        return view('livewire.products.catalog-quick-add', [
            'title' => $titles[$this->type],
            'mainCategories' => $this->open && $this->type === 'subcategory' ? Category::query()->whereNull('parent_id')->orderBy('name')->get() : collect(),
        ]);
    }
}
