<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Referrer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filters chosen on the reports page (each one a list of ids; empty = all).
 *
 * Category = main categories, Sub-category = categories below them. Choosing a sub-category narrows
 * its own main category down to that sub-category; other chosen main categories stay whole.
 */
class ReportFilters
{
    /** Filter key => label (for the printed "Filtered by" line). */
    public const KEYS = [
        'categories' => 'Category',
        'subcategories' => 'Sub-category',
        'brands' => 'Brand',
        'products' => 'Product',
        'referrers' => 'Reference',
        'customers' => 'Customer',
        'staff' => 'Staff',
        'payment_methods' => 'Payment method',
        'expense_categories' => 'Expense category',
    ];

    /**
     * @param  array<string, list<int>>  $ids
     */
    public function __construct(public readonly array $ids = []) {}

    /**
     * Clean filters from the request or the page: only known keys, whole positive numbers.
     *
     * @param  array<string, mixed>  $input
     * @param  list<string>|null  $only  keep only these keys (the filters a report uses)
     */
    public static function from(array $input, ?array $only = null): self
    {
        $ids = [];

        foreach (array_keys(self::KEYS) as $key) {
            if ($only !== null && ! in_array($key, $only, true)) {
                continue;
            }

            $values = array_values(array_unique(array_filter(array_map('intval', (array) ($input[$key] ?? [])), fn (int $id): bool => $id > 0)));

            if ($values !== []) {
                $ids[$key] = $values;
            }
        }

        return new self($ids);
    }

    /**
     * @return list<int>
     */
    public function get(string $key): array
    {
        return $this->ids[$key] ?? [];
    }

    public function isEmpty(): bool
    {
        return $this->ids === [];
    }

    /**
     * Is any product-level filter (category, sub-category, brand, product) chosen?
     */
    public function hasItemFilters(): bool
    {
        return $this->get('categories') !== [] || $this->get('subcategories') !== [] || $this->get('brands') !== [] || $this->get('products') !== [];
    }

    /**
     * Ids of the categories to include (with everything below them), or null for all.
     *
     * @return list<int>|null
     */
    public function categoryIds(): ?array
    {
        $mains = $this->get('categories');
        $subs = $this->get('subcategories');

        if ($mains === [] && $subs === []) {
            return null;
        }

        $tree = app(CategoryTree::class);
        $ids = [];
        $narrowed = [];

        foreach ($subs as $subId) {
            $ids = [...$ids, ...$tree->withDescendants($subId)];
            $narrowed[] = $this->mainOf($subId);
        }

        foreach ($mains as $mainId) {
            if (! in_array($mainId, $narrowed, true)) {
                $ids = [...$ids, ...$tree->withDescendants($mainId)];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Products matching the product-level filters (a subquery of ids), or null when there are none.
     *
     * @return Builder<Product>|null
     */
    public function productQuery(): ?Builder
    {
        if (! $this->hasItemFilters()) {
            return null;
        }

        $categoryIds = $this->categoryIds();

        return Product::query()
            ->select('products.id')
            ->when($categoryIds !== null, fn (Builder $query) => $query->whereIn('products.category_id', $categoryIds))
            ->when($this->get('brands') !== [], fn (Builder $query) => $query->whereIn('products.brand_id', $this->get('brands')))
            ->when($this->get('products') !== [], fn (Builder $query) => $query->whereIn('products.id', $this->get('products')));
    }

    /**
     * Limit a query on a product id column to the filtered products.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function onProducts(Builder $query, string $column): Builder
    {
        $products = $this->productQuery();

        return $products ? $query->whereIn($column, $products) : $query;
    }

    /**
     * Limit a query on a column to the chosen ids of one filter.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function on(Builder $query, string $key, string $column): Builder
    {
        return $this->get($key) !== [] ? $query->whereIn($column, $this->get($key)) : $query;
    }

    /**
     * "Category: Tiles, Cement · Reference: Karim Mistri" for the printed report.
     *
     * @return list<string>
     */
    public function describe(): array
    {
        $names = [
            'categories' => fn (array $ids) => Category::query()->whereIn('id', $ids)->get()->map->localName(),
            'subcategories' => fn (array $ids) => Category::query()->whereIn('id', $ids)->get()->map(fn (Category $category) => app(CategoryTree::class)->path($category->id)),
            'brands' => fn (array $ids) => Brand::query()->whereIn('id', $ids)->pluck('name'),
            'products' => fn (array $ids) => Product::query()->whereIn('id', $ids)->get()->map->localName(),
            'referrers' => fn (array $ids) => Referrer::query()->whereIn('id', $ids)->pluck('name'),
            'customers' => fn (array $ids) => Customer::query()->whereIn('id', $ids)->pluck('name'),
            'staff' => fn (array $ids) => User::query()->whereIn('id', $ids)->pluck('name'),
            'payment_methods' => fn (array $ids) => PaymentMethod::query()->whereIn('id', $ids)->get()->map->localName(),
            'expense_categories' => fn (array $ids) => ExpenseCategory::query()->whereIn('id', $ids)->get()->map->localName(),
        ];
        $lines = [];

        foreach ($this->ids as $key => $ids) {
            $list = $names[$key]($ids);
            $text = $list->count() > 6 ? $list->take(6)->implode(', ').' '.__('and :count more', ['count' => $list->count() - 6]) : $list->implode(', ');
            $lines[] = __(self::KEYS[$key]).': '.$text;
        }

        return $lines;
    }

    private function mainOf(int $categoryId): int
    {
        $category = Category::query()->find($categoryId);

        while ($category?->parent_id) {
            $category = Category::query()->find($category->parent_id);
        }

        return (int) $category?->id;
    }
}
