<?php

namespace App\Services\Imports;

use App\Models\Branch;
use App\Models\Brand;

/**
 * Bulk add brands. Brands that already exist are kept.
 */
class BrandImport extends ImportSheet
{
    public function key(): string
    {
        return 'brands';
    }

    public function sheetName(): string
    {
        return 'Brands';
    }

    public function columns(): array
    {
        return [
            'name' => ['label' => 'Brand name', 'required' => true, 'hint' => 'One brand per row. Brands that already exist are skipped.', 'example' => 'BSRM', 'width' => 30],
        ];
    }

    public function examples(): array
    {
        return [['name' => 'BSRM'], ['name' => 'Shah Cement'], ['name' => 'RAK Ceramics'], ['name' => 'Walton']];
    }

    public function lists(): array
    {
        return ['Brands' => Brand::query()->orderBy('name')->pluck('name')->all()];
    }

    public function check(array $rows, Branch $branch, array $options = []): array
    {
        $existing = Brand::query()->pluck('name')->mapWithKeys(fn (string $name): array => [self::normalize($name) => true]);
        $seen = [];
        $results = [];

        foreach ($rows as ['row' => $row, 'values' => $values]) {
            $errors = $this->validateRow($values, ['name' => ['required', 'string', 'max:255']]);
            $key = self::normalize($values['name']);

            if ($errors !== []) {
                $results[] = $this->result($row, 'error', $values['name'] ?: __('Row :row', ['row' => $row]), errors: $errors);
            } elseif (isset($seen[$key])) {
                $results[] = $this->result($row, 'skip', $values['name'], __('Same as row :row', ['row' => $seen[$key]]));
            } elseif ($existing->has($key)) {
                $results[] = $this->result($row, 'skip', $values['name'], __('Already exists'));
            } else {
                $results[] = $this->result($row, 'new', $values['name'], __('New brand'), data: ['name' => trim($values['name'])]);
            }

            $seen[$key] ??= $row;
        }

        return $results;
    }

    public function apply(array $rows, Branch $branch, array $options = []): void
    {
        foreach ($rows as ['data' => $data]) {
            Brand::query()->firstOrCreate(['name' => $data['name']], ['is_active' => true]);
        }
    }
}
