<?php

namespace App\Services\Imports;

use App\Models\Branch;
use App\Models\Category;
use App\Services\CategoryTree;

/**
 * Bulk add categories and sub-categories. Existing ones are kept; a Bangla name fills in a missing one.
 */
class CategoryImport extends ImportSheet
{
    public function key(): string
    {
        return 'categories';
    }

    public function sheetName(): string
    {
        return 'Categories';
    }

    public function columns(): array
    {
        return [
            'category' => ['label' => 'Category', 'required' => true, 'hint' => 'Main category name. Repeat it on every row that has a sub-category under it.', 'example' => 'Tiles', 'width' => 26, 'list' => 'Categories'],
            'category_bn' => ['label' => 'Category (Bangla)', 'hint' => 'Optional Bangla name of the main category.', 'example' => 'টাইলস', 'width' => 24],
            'sub_category' => ['label' => 'Sub-category', 'hint' => 'Optional. Leave empty to add only the main category.', 'example' => 'Floor Tiles', 'width' => 26],
            'sub_category_bn' => ['label' => 'Sub-category (Bangla)', 'hint' => 'Optional Bangla name of the sub-category.', 'example' => 'ফ্লোর টাইলস', 'width' => 24],
        ];
    }

    public function examples(): array
    {
        return [
            ['category' => 'Tiles', 'category_bn' => 'টাইলস', 'sub_category' => 'Floor Tiles', 'sub_category_bn' => 'ফ্লোর টাইলস'],
            ['category' => 'Tiles', 'sub_category' => 'Wall Tiles', 'sub_category_bn' => 'ওয়াল টাইলস'],
            ['category' => 'Paint', 'category_bn' => 'রং'],
            ['category' => 'Paint', 'sub_category' => 'Exterior Paint'],
        ];
    }

    public function lists(): array
    {
        return [
            'Categories' => Category::query()->whereNull('parent_id')->orderBy('name')->pluck('name')->all(),
        ];
    }

    public function check(array $rows, Branch $branch, array $options = []): array
    {
        $lookup = new CatalogLookup;
        $seen = [];
        $results = [];

        foreach ($rows as ['row' => $row, 'values' => $values]) {
            $label = trim($values['category']).($values['sub_category'] !== '' ? ' › '.trim($values['sub_category']) : '');
            $errors = $this->validateRow($values, [
                'category' => ['required', 'string', 'max:255'],
                'category_bn' => ['nullable', 'string', 'max:255'],
                'sub_category' => ['nullable', 'string', 'max:255'],
                'sub_category_bn' => ['nullable', 'string', 'max:255'],
            ]);

            if ($errors !== []) {
                $results[] = $this->result($row, 'error', $label, errors: $errors);

                continue;
            }

            $key = self::normalize($values['category']).'>'.self::normalize($values['sub_category']);

            if (isset($seen[$key])) {
                $results[] = $this->result($row, 'skip', $label, __('Same as row :row', ['row' => $seen[$key]]));

                continue;
            }

            $seen[$key] = $row;
            $main = $lookup->child(null, $values['category']);
            $sub = $main && $values['sub_category'] !== '' ? $lookup->child($main->id, $values['sub_category']) : null;
            $new = $lookup->category($values['category'], $values['sub_category'], false, false)['new'];

            if ($new !== []) {
                $results[] = $this->result($row, 'new', $label, implode(' · ', $new), data: $values);

                continue;
            }

            $fillsBangla = ($values['category_bn'] !== '' && $main && blank($main->name_bn))
                || ($values['sub_category_bn'] !== '' && $sub && blank($sub->name_bn));

            $results[] = $fillsBangla
                ? $this->result($row, 'update', $label, __('Bangla name will be added'), data: $values)
                : $this->result($row, 'skip', $label, __('Already exists'));
        }

        return $results;
    }

    public function apply(array $rows, Branch $branch, array $options = []): void
    {
        $lookup = new CatalogLookup;

        foreach ($rows as ['data' => $values]) {
            $lookup->category($values['category'], '', true, false);
            $main = $lookup->child(null, $values['category']);
            $this->fillBangla($main, $values['category_bn']);

            if ($values['sub_category'] !== '') {
                $lookup->category($values['category'], $values['sub_category'], true, false);
                $this->fillBangla($lookup->child($main->id, $values['sub_category']), $values['sub_category_bn']);
            }
        }

        app(CategoryTree::class)->forget();
    }

    private function fillBangla(?Category $category, string $nameBn): void
    {
        if ($category && $nameBn !== '' && blank($category->name_bn)) {
            $category->update(['name_bn' => $nameBn]);
        }
    }
}
