<?php

namespace App\Services\Imports;

use App\Models\Branch;
use App\Models\Unit;

/**
 * Bulk add base units (how stock is counted: piece, kg, bag, ft...). Existing units are kept;
 * only a missing Bangla name is filled in.
 */
class UnitImport extends ImportSheet
{
    private const YES = ['yes', 'y', '1', 'true', 'হ্যাঁ'];

    private const NO = ['', 'no', 'n', '0', 'false', 'না'];

    public function key(): string
    {
        return 'units';
    }

    public function sheetName(): string
    {
        return 'Units';
    }

    public function columns(): array
    {
        return [
            'name' => ['label' => 'Unit name', 'required' => true, 'hint' => 'Full name in English, e.g. Square feet, Bundle, Litre.', 'example' => 'Bundle', 'width' => 22],
            'name_bn' => ['label' => 'Name (Bangla)', 'hint' => 'Optional Bangla name.', 'example' => 'বান্ডিল', 'width' => 20],
            'short_name' => ['label' => 'Short name', 'required' => true, 'hint' => 'Short code shown next to quantities (max 10 letters), e.g. bdl, ltr, cft. Units with a short name that already exists are skipped.', 'example' => 'bdl', 'width' => 14, 'text' => true],
            'allows_decimal' => ['label' => 'Allows decimal', 'hint' => 'Yes if you sell parts of it (2.5 kg, 3.75 ft). No (or empty) for whole numbers only (pieces, bags).', 'example' => 'No', 'width' => 16, 'list' => 'Yes / No'],
        ];
    }

    public function examples(): array
    {
        return [
            ['name' => 'Bundle', 'name_bn' => 'বান্ডিল', 'short_name' => 'bdl', 'allows_decimal' => 'No'],
            ['name' => 'Litre', 'name_bn' => 'লিটার', 'short_name' => 'ltr', 'allows_decimal' => 'Yes'],
            ['name' => 'Cubic feet', 'name_bn' => 'ঘনফুট', 'short_name' => 'cft', 'allows_decimal' => 'Yes'],
        ];
    }

    public function lists(): array
    {
        return [
            'Units' => Unit::query()->orderBy('id')->pluck('short_name')->all(),
            'Yes / No' => ['Yes', 'No'],
        ];
    }

    public function check(array $rows, Branch $branch, array $options = []): array
    {
        $units = Unit::query()->get();
        $seen = [];
        $results = [];

        foreach ($rows as ['row' => $row, 'values' => $values]) {
            $label = trim($values['name'].($values['short_name'] !== '' ? ' ('.$values['short_name'].')' : ''));
            $errors = $this->validateRow($values, [
                'name' => ['required', 'string', 'max:50'],
                'name_bn' => ['nullable', 'string', 'max:50'],
                'short_name' => ['required', 'string', 'max:10'],
            ]);
            $decimal = self::normalize($values['allows_decimal']);

            if (! in_array($decimal, [...self::YES, ...self::NO], true)) {
                $errors[] = __('Allows decimal must be Yes or No.');
            }

            $key = self::normalize($values['short_name']);

            if ($errors === [] && isset($seen[$key])) {
                $errors[] = __('Same short name as row :row.', ['row' => $seen[$key]]);
            }

            if ($errors !== []) {
                $results[] = $this->result($row, 'error', $label ?: __('Row :row', ['row' => $row]), errors: $errors);

                continue;
            }

            $seen[$key] = $row;
            $existing = $units->first(fn (Unit $unit): bool => self::normalize($unit->short_name) === $key);

            if ($existing) {
                $results[] = $values['name_bn'] !== '' && blank($existing->name_bn)
                    ? $this->result($row, 'update', $label, __('Bangla name will be added'), data: ['id' => $existing->id, 'name_bn' => $values['name_bn']])
                    : $this->result($row, 'skip', $label, __('Already exists'));

                continue;
            }

            $allowsDecimal = in_array($decimal, self::YES, true);
            $results[] = $this->result($row, 'new', $label, $allowsDecimal ? __('Allows decimals (e.g. 2.5)') : __('Whole numbers only'), data: [
                'name' => trim($values['name']),
                'name_bn' => $values['name_bn'] ?: null,
                'short_name' => trim($values['short_name']),
                'allows_decimal' => $allowsDecimal,
            ]);
        }

        return $results;
    }

    public function apply(array $rows, Branch $branch, array $options = []): void
    {
        foreach ($rows as ['data' => $data]) {
            if (isset($data['id'])) {
                Unit::query()->whereKey($data['id'])->update(['name_bn' => $data['name_bn']]);
            } else {
                Unit::query()->create($data);
            }
        }
    }
}
