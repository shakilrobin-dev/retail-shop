<?php

namespace App\Services\Imports;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Unit;
use App\Services\CategoryTree;
use Illuminate\Support\Collection;

/**
 * Finds categories, brands and units by the names typed in an import file,
 * and creates missing categories and brands when the import is saved.
 */
class CatalogLookup
{
    /** @var Collection<int, Category> */
    private Collection $categories;

    /** @var Collection<int, Brand> */
    private Collection $brands;

    /** @var Collection<int, Unit> */
    private Collection $units;

    /** @var array<string, true> names already reported as "will be created" during a check */
    private array $planned = [];

    public function __construct()
    {
        $this->categories = Category::query()->get();
        $this->brands = Brand::query()->get();
        $this->units = Unit::query()->get();
    }

    /**
     * Find (or, when $create is true, make) the category for "Category" + optional "Sub-category".
     * When only one name is given and it is an existing sub-category, that one is used (unless $anyLevel is false).
     *
     * @return array{id: int|null, new: list<string>}
     */
    public function category(string $mainName, string $subName, bool $create, bool $anyLevel = true): array
    {
        $new = [];
        $main = $this->child(null, $mainName);

        if (! $main && $subName === '' && $anyLevel) {
            $matches = $this->categories->filter(fn (Category $category): bool => $this->same($category->name, $mainName));

            if ($matches->count() === 1) {
                return ['id' => $matches->first()->id, 'new' => []];
            }
        }

        if (! $main) {
            $new[] = $this->plan('category:'.$this->key($mainName), __('New category: :name', ['name' => trim($mainName)]));
            $main = $create ? $this->createCategory(null, $mainName) : null;
        }

        if ($subName === '') {
            return ['id' => $main?->id, 'new' => array_values(array_filter($new))];
        }

        $sub = $main ? $this->child($main->id, $subName) : null;

        if (! $sub) {
            $new[] = $this->plan('sub:'.$this->key($mainName).'>'.$this->key($subName), __('New sub-category: :name', ['name' => trim($mainName).' › '.trim($subName)]));
            $sub = $create && $main ? $this->createCategory($main->id, $subName) : null;
        }

        return ['id' => $sub?->id, 'new' => array_values(array_filter($new))];
    }

    public function child(?int $parentId, string $name): ?Category
    {
        return $this->categories->first(fn (Category $category): bool => $category->parent_id === $parentId && $this->same($category->name, $name));
    }

    /**
     * @return array{id: int|null, new: string|null}
     */
    public function brand(string $name, bool $create): array
    {
        if (trim($name) === '') {
            return ['id' => null, 'new' => null];
        }

        $brand = $this->brands->first(fn (Brand $brand): bool => $this->same($brand->name, $name));

        if ($brand) {
            return ['id' => $brand->id, 'new' => null];
        }

        $note = $this->plan('brand:'.$this->key($name), __('New brand: :name', ['name' => trim($name)]));

        if ($create) {
            $brand = Brand::query()->create(['name' => trim($name)]);
            $this->brands->push($brand);
        }

        return ['id' => $brand?->id, 'new' => $note];
    }

    /**
     * A base unit by its short name, name or Bangla name (pcs, Piece, পিস).
     */
    public function unit(string $name): ?Unit
    {
        return $this->units->first(fn (Unit $unit): bool => $this->same($unit->short_name, $name) || $this->same($unit->name, $name) || $this->same((string) $unit->name_bn, $name));
    }

    public function unitNames(): string
    {
        return $this->units->pluck('short_name')->implode(', ');
    }

    /**
     * Forget the "will be created" notes (before saving, so nothing is skipped).
     */
    public function resetPlan(): void
    {
        $this->planned = [];
    }

    private function createCategory(?int $parentId, string $name): Category
    {
        $category = Category::query()->create(['parent_id' => $parentId, 'name' => trim($name), 'is_active' => true]);
        $this->categories->push($category);
        app(CategoryTree::class)->forget();

        return $category;
    }

    /**
     * The note the first time something new is met, null after that.
     */
    private function plan(string $key, string $note): ?string
    {
        if (isset($this->planned[$key])) {
            return null;
        }

        $this->planned[$key] = true;

        return $note;
    }

    private function same(?string $a, ?string $b): bool
    {
        return $this->key($a) === $this->key($b) && $this->key($a) !== '';
    }

    private function key(?string $value): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $value)));
    }
}
