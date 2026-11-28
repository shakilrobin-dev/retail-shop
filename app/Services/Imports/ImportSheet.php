<?php

namespace App\Services\Imports;

use App\Models\Branch;
use Illuminate\Support\Facades\Validator;

/**
 * One kind of Excel import (categories, products or stock): its columns, sample rows,
 * the row check that runs before importing, and the import itself.
 *
 * A checked row looks like:
 *   ['row' => 5, 'status' => 'new'|'update'|'skip'|'error', 'label' => 'Wall Tiles 12x20', 'detail' => 'New category: Tiles', 'errors' => [...], 'data' => [...]]
 */
abstract class ImportSheet
{
    /**
     * Short key used in URLs and file names, e.g. "products".
     */
    abstract public function key(): string;

    /**
     * Name of the data sheet in the sample workbook.
     */
    abstract public function sheetName(): string;

    /**
     * @return array<string, array{label: string, required?: bool, hint: string, example?: string, width?: int, text?: bool, list?: string}>
     */
    abstract public function columns(): array;

    /**
     * Example rows written into the sample file.
     *
     * @return list<array<string, string|int|float|null>>
     */
    abstract public function examples(): array;

    /**
     * Check every row and work out what the import will do with it. Nothing is saved.
     *
     * @param  list<array{row: int, values: array<string, string>}>  $rows
     * @param  array<string, mixed>  $options
     * @return list<array{row: int, status: string, label: string, detail: string, errors: list<string>, data: array<string, mixed>}>
     */
    abstract public function check(array $rows, Branch $branch, array $options = []): array;

    /**
     * Save the rows that passed the check. Runs inside a database transaction.
     *
     * @param  list<array{row: int, status: string, label: string, detail: string, errors: list<string>, data: array<string, mixed>}>  $rows
     * @param  array<string, mixed>  $options
     */
    abstract public function apply(array $rows, Branch $branch, array $options = []): void;

    /**
     * Lists of existing values (categories, units...) written on a "Lists" sheet of the sample.
     *
     * @return array<string, list<string>>
     */
    public function lists(): array
    {
        return [];
    }

    /**
     * Run Laravel validation on one row and return the messages.
     *
     * @param  array<string, string>  $values
     * @param  array<string, mixed>  $rules
     * @return list<string>
     */
    protected function validateRow(array $values, array $rules): array
    {
        $names = collect($this->columns())->map(fn (array $column): string => $column['label'])->all();

        return array_values(Validator::make($values, $rules, [], $names)->errors()->all());
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $errors
     * @return array{row: int, status: string, label: string, detail: string, errors: list<string>, data: array<string, mixed>}
     */
    protected function result(int $row, string $status, string $label, string $detail = '', array $errors = [], array $data = []): array
    {
        return [
            'row' => $row,
            'status' => $errors !== [] ? 'error' : $status,
            'label' => $label,
            'detail' => $detail,
            'errors' => $errors,
            'data' => $data,
        ];
    }

    /**
     * Lower-case, single-spaced text for matching names typed in Excel.
     */
    protected static function normalize(?string $value): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $value)));
    }

    /**
     * A number typed in Excel ("1,250.50" or "1250.5"), or null when blank.
     */
    protected static function number(?string $value): ?float
    {
        $value = str_replace([',', ' '], '', trim((string) $value));

        return $value === '' || ! is_numeric($value) ? null : (float) $value;
    }
}
