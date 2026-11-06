<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Collection;

/**
 * Category hierarchy helper (unlimited levels, e.g. Accessories › Chargers › Fast chargers).
 * Loads all categories once per request; the table is small.
 */
class CategoryTree
{
    public const MAX_DEPTH = 5;

    /** @var Collection<int, Category>|null */
    private ?Collection $all = null;

    /**
     * Depth-first ordered list: each item has category, depth (0 = main), path and children count.
     *
     * @return Collection<int, array{category: Category, depth: int, path: string, has_children: bool}>
     */
    public function flat(bool $onlyActive = false): Collection
    {
        $all = $this->all();
        $byParent = $all->groupBy(fn (Category $category): int => (int) $category->parent_id);
        $rows = collect();

        $walk = function (int $parentId, int $depth, array $trail) use (&$walk, $byParent, $rows, $onlyActive): void {
            foreach ($byParent->get($parentId, collect())->sortBy(fn (Category $c) => mb_strtolower($c->name)) as $category) {
                if ($onlyActive && ! $category->is_active) {
                    continue;
                }

                $names = [...$trail, $category->localName()];
                $rows->push([
                    'category' => $category,
                    'depth' => $depth,
                    'path' => implode(' › ', $names),
                    'has_children' => $byParent->has($category->id),
                ]);

                if ($depth < self::MAX_DEPTH) {
                    $walk($category->id, $depth + 1, $names);
                }
            }
        };

        $walk(0, 0, []);

        return $rows;
    }

    /**
     * The category and every category below it.
     *
     * @return list<int>
     */
    public function withDescendants(int $categoryId): array
    {
        $byParent = $this->all()->groupBy(fn (Category $category): int => (int) $category->parent_id);
        $ids = [$categoryId];
        $queue = [$categoryId];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($byParent->get($current, collect()) as $child) {
                if (! in_array($child->id, $ids, true)) {
                    $ids[] = $child->id;
                    $queue[] = $child->id;
                }
            }
        }

        return $ids;
    }

    /**
     * "Accessories › Chargers › Fast chargers"
     */
    public function path(?int $categoryId): string
    {
        $names = [];
        $all = $this->all()->keyBy('id');
        $guard = 0;

        while ($categoryId && ($category = $all->get($categoryId)) && $guard++ < 20) {
            array_unshift($names, $category->localName());
            $categoryId = $category->parent_id;
        }

        return implode(' › ', $names);
    }

    public function depth(int $categoryId): int
    {
        return max(0, count(explode(' › ', $this->path($categoryId))) - 1);
    }

    /**
     * True if putting $categoryId under $newParentId would loop (parent inside its own branch).
     */
    public function wouldLoop(int $categoryId, ?int $newParentId): bool
    {
        return $newParentId !== null && in_array($newParentId, $this->withDescendants($categoryId), true);
    }

    public function forget(): void
    {
        $this->all = null;
    }

    /**
     * @return Collection<int, Category>
     */
    private function all(): Collection
    {
        return $this->all ??= Category::query()->get();
    }
}
