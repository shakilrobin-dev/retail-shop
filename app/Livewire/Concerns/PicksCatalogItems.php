<?php

namespace App\Livewire\Concerns;

use App\Models\Category;
use App\Services\CategoryTree;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;

/**
 * Category + sub-category pickers for a product form, and the "+ New" buttons that open the
 * add pop-up for a category, sub-category, brand or unit. The new item is chosen in the form.
 *
 * The form keeps the chosen category in $category_id (the sub-category when one is chosen).
 */
trait PicksCatalogItems
{
    public ?int $mainCategoryId = null;

    public ?int $subCategoryId = null;

    /**
     * Fill the two pickers from $category_id (e.g. when editing a product).
     */
    protected function syncCategoryPickers(): void
    {
        $this->mainCategoryId = null;
        $this->subCategoryId = null;

        if (! $this->category_id) {
            return;
        }

        $category = Category::query()->find($this->category_id);
        $main = $category;

        while ($main?->parent_id) {
            $main = Category::query()->find($main->parent_id);
        }

        $this->mainCategoryId = $main?->id;
        $this->subCategoryId = $main && $category && $main->id !== $category->id ? $category->id : null;
    }

    public function updatedMainCategoryId(mixed $value): void
    {
        $this->mainCategoryId = $value ? (int) $value : null;
        $this->subCategoryId = null;
        $this->category_id = $this->mainCategoryId;
    }

    public function updatedSubCategoryId(mixed $value): void
    {
        $this->subCategoryId = $value ? (int) $value : null;
        $this->category_id = $this->subCategoryId ?? $this->mainCategoryId;
    }

    public function updatedCategoryId(): void
    {
        $this->syncCategoryPickers();
    }

    /**
     * @return Collection<int, Category>
     */
    public function mainCategoryOptions(): Collection
    {
        return Category::query()->whereNull('parent_id')->where('is_active', true)->orderBy('name')->get();
    }

    /**
     * Categories below the chosen main category, e.g. "Floor Tiles" or "Floor Tiles › Matt".
     *
     * @return Collection<int, array{id: int, label: string}>
     */
    public function subCategoryOptions(): Collection
    {
        if (! $this->mainCategoryId) {
            return collect();
        }

        $tree = app(CategoryTree::class);
        $below = $tree->withDescendants($this->mainCategoryId);

        return $tree->flat(onlyActive: true)
            ->filter(fn (array $row): bool => $row['depth'] > 0 && in_array($row['category']->id, $below, true))
            ->map(fn (array $row): array => ['id' => $row['category']->id, 'label' => implode(' › ', array_slice(explode(' › ', $row['path']), 1))])
            ->values();
    }

    /**
     * Open the pop-up to add a category, sub-category (under the chosen category), brand or unit.
     */
    public function addCatalogItem(string $type): void
    {
        $this->dispatch('open-catalog-quick-add', type: $type, parentId: $type === 'subcategory' ? $this->mainCategoryId : null, target: $this->getName());
    }

    /**
     * A category, sub-category, brand or unit was added from this form: choose it.
     */
    #[On('catalog-item-added')]
    public function catalogItemAdded(string $type, int $id, string $target): void
    {
        if ($target !== $this->getName()) {
            return;
        }

        match ($type) {
            'category', 'subcategory' => $this->category_id = $id,
            'brand' => property_exists($this, 'brand_id') ? $this->brand_id = $id : null,
            'unit' => $this->unit_id = $id,
            default => null,
        };

        if (in_array($type, ['category', 'subcategory'], true)) {
            $this->syncCategoryPickers();
            $this->resetValidation('category_id');
        }
    }
}
