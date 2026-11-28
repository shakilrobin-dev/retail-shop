<?php

namespace App\Services\Imports;

use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

/**
 * Writes the sample Excel file for an import and reads filled-in files back.
 */
class ImportWorkbook
{
    public const MAX_ROWS = 5000;

    /** Rows below the header that get dropdowns and text formatting in the sample. */
    private const FORMATTED_ROWS = 2000;

    /**
     * Build the sample workbook and return the path of a temporary .xlsx file.
     */
    public function sample(ImportSheet $sheet): string
    {
        $spreadsheet = new Spreadsheet;
        $data = $spreadsheet->getActiveSheet()->setTitle($sheet->sheetName());
        $columns = $sheet->columns();
        $this->writeInstructions($spreadsheet, $columns);
        $lists = $sheet->lists();
        $listRanges = $lists !== [] ? $this->writeLists($spreadsheet, $lists) : [];

        $index = 1;

        foreach ($columns as $column) {
            $letter = Coordinate::stringFromColumnIndex($index);
            $data->setCellValue($letter.'1', $column['label'].(($column['required'] ?? false) ? ' *' : ''));
            $data->getColumnDimension($letter)->setWidth($column['width'] ?? 16);
            $data->getComment($letter.'1')->getText()->createText($column['hint']);

            if ($column['text'] ?? false) {
                $data->getStyle($letter.'2:'.$letter.self::FORMATTED_ROWS)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            }

            if (isset($column['list'], $listRanges[$column['list']])) {
                $validation = $data->getCell($letter.'2')->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST)
                    ->setAllowBlank(true)
                    ->setShowDropDown(true)
                    ->setShowErrorMessage(false)
                    ->setFormula1($listRanges[$column['list']]);
                $data->setDataValidation($letter.'2:'.$letter.self::FORMATTED_ROWS, $validation);
            }

            $index++;
        }

        $lastLetter = Coordinate::stringFromColumnIndex(count($columns));
        $header = $data->getStyle('A1:'.$lastLetter.'1');
        $header->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A8A');
        $data->getRowDimension(1)->setRowHeight(22);
        $data->freezePane('A2');

        $rowNumber = 2;

        foreach ($sheet->examples() as $example) {
            $index = 1;

            foreach (array_keys($columns) as $key) {
                $value = $example[$key] ?? null;

                if ($value !== null && $value !== '') {
                    $data->setCellValueExplicit(
                        Coordinate::stringFromColumnIndex($index).$rowNumber,
                        $value,
                        is_string($value) ? 'str' : 'n',
                    );
                }

                $index++;
            }

            $rowNumber++;
        }

        $spreadsheet->setActiveSheetIndex(0);

        $path = tempnam(sys_get_temp_dir(), 'import-sample-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    /**
     * Read the filled-in rows. Each row is keyed by column key, every value a trimmed string.
     *
     * @return list<array{row: int, values: array<string, string>}>
     *
     * @throws ValidationException when the file can't be read or the header row is wrong
     */
    public function read(string $path, string $extension, ImportSheet $sheet): array
    {
        $readerType = match (strtolower($extension)) {
            'xlsx' => 'Xlsx',
            'xls' => 'Xls',
            'csv', 'txt' => 'Csv',
            default => throw ValidationException::withMessages(['file' => __('Please upload an Excel (.xlsx, .xls) or CSV file.')]),
        };

        try {
            $reader = IOFactory::createReader($readerType);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => __('This file could not be read. Save it as .xlsx from Excel and try again.')]);
        }

        $worksheet = $spreadsheet->getSheetByName($sheet->sheetName()) ?? $spreadsheet->getSheet(0);
        $cells = $worksheet->toArray(null, true, false, false);

        $headerIndex = null;

        foreach ($cells as $index => $cellRow) {
            if (collect($cellRow)->contains(fn ($cell): bool => $this->text($cell) !== '')) {
                $headerIndex = $index;
                break;
            }
        }

        if ($headerIndex === null) {
            throw ValidationException::withMessages(['file' => __('The file is empty.')]);
        }

        $map = $this->mapHeader($cells[$headerIndex], $sheet);
        $missing = collect($sheet->columns())
            ->filter(fn (array $column, string $key): bool => ($column['required'] ?? false) && ! in_array($key, $map, true))
            ->map(fn (array $column): string => $column['label']);

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(['file' => __('Column(s) missing: :columns. Download the sample file and keep its first row as it is.', ['columns' => $missing->implode(', ')])]);
        }

        $rows = [];

        foreach (array_slice($cells, $headerIndex + 1, null, true) as $index => $cellRow) {
            $values = [];

            foreach ($map as $position => $key) {
                $values[$key] = $this->text($cellRow[$position] ?? null);
            }

            if (implode('', $values) === '') {
                continue;
            }

            $rows[] = ['row' => $index + 1, 'values' => $values + array_fill_keys(array_keys($sheet->columns()), '')];
        }

        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => __('Too many rows. Import at most :max rows at a time.', ['max' => self::MAX_ROWS])]);
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => __('No rows found below the header row.')]);
        }

        return $rows;
    }

    /**
     * Match the header cells with the import's columns. Returns cell position => column key.
     *
     * @param  array<int, mixed>  $headerRow
     * @return array<int, string>
     */
    private function mapHeader(array $headerRow, ImportSheet $sheet): array
    {
        $known = [];

        foreach ($sheet->columns() as $key => $column) {
            $known[$this->headerKey($column['label'])] = $key;
            $known[$this->headerKey($key)] = $key;
        }

        $map = [];

        foreach ($headerRow as $position => $cell) {
            $key = $known[$this->headerKey($this->text($cell))] ?? null;

            if ($key !== null && ! in_array($key, $map, true)) {
                $map[$position] = $key;
            }
        }

        return $map;
    }

    private function headerKey(string $label): string
    {
        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($label));
    }

    /**
     * A cell as trimmed text. Whole numbers lose the ".0" Excel adds (barcodes, SKUs).
     */
    private function text(mixed $cell): string
    {
        if ($cell === null) {
            return '';
        }

        if (is_bool($cell)) {
            return $cell ? '1' : '0';
        }

        if (is_float($cell)) {
            return floor($cell) === $cell && abs($cell) < 1e15 ? sprintf('%.0f', $cell) : (string) $cell;
        }

        return trim(str_replace("\u{00A0}", ' ', (string) $cell));
    }

    /**
     * @param  array<string, list<string>>  $lists
     * @return array<string, string> list title => absolute range for a dropdown
     */
    private function writeLists(Spreadsheet $spreadsheet, array $lists): array
    {
        $sheet = $spreadsheet->createSheet()->setTitle('Lists');
        $ranges = [];
        $index = 1;

        foreach ($lists as $title => $values) {
            $letter = Coordinate::stringFromColumnIndex($index);
            $sheet->setCellValue($letter.'1', $title);
            $sheet->getStyle($letter.'1')->getFont()->setBold(true);
            $sheet->getColumnDimension($letter)->setWidth(28);

            foreach (array_values($values) as $offset => $value) {
                $sheet->setCellValueExplicit($letter.($offset + 2), $value, 'str');
            }

            $ranges[$title] = sprintf("'Lists'!\$%s\$2:\$%s\$%d", $letter, $letter, max(2, count($values) + 1));
            $index++;
        }

        return $ranges;
    }

    /**
     * @param  array<string, array{label: string, required?: bool, hint: string, example?: string}>  $columns
     */
    private function writeInstructions(Spreadsheet $spreadsheet, array $columns): void
    {
        $sheet = $spreadsheet->createSheet()->setTitle('Instructions');
        $sheet->fromArray([__('Column'), __('Required'), __('What to write'), __('Example')]);
        $sheet->getStyle('A1:D1')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(24);
        $sheet->getColumnDimension('B')->setWidth(10);
        $sheet->getColumnDimension('C')->setWidth(80);
        $sheet->getColumnDimension('D')->setWidth(22);

        $row = 2;

        foreach ($columns as $column) {
            $sheet->setCellValue('A'.$row, $column['label']);
            $sheet->setCellValue('B'.$row, ($column['required'] ?? false) ? __('Yes') : '');
            $sheet->setCellValue('C'.$row, $column['hint']);
            $sheet->setCellValueExplicit('D'.$row, (string) ($column['example'] ?? ''), 'str');
            $sheet->getStyle('C'.$row)->getAlignment()->setWrapText(true);
            $row++;
        }

        $row++;
        $notes = [
            __('Fill the first sheet. Keep the first row (the column names) as it is. You can delete the example rows.'),
            __('Columns marked * must be filled. Leave other columns empty if you do not need them.'),
            __('After uploading you will see what will happen to every row before anything is saved.'),
        ];

        foreach ($notes as $note) {
            $sheet->setCellValue('A'.$row, '• '.$note);
            $sheet->mergeCells('A'.$row.':D'.$row);
            $row++;
        }
    }
}
