<?php

namespace App\Services;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Style;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Writes a report document (from ReportDocument) as PDF, Excel or CSV.
 */
class ReportExporter
{
    private const INK = '1E1B4B';

    private const ACCENT = '4F46E5';

    private const HEADER_FILL = 'EEF0F6';

    /** Excel number formats for each column format. */
    private const NUMBER_FORMATS = [
        'money' => '#,##0.00;[Red]-#,##0.00',
        'qty' => '#,##0.###',
        'int' => '#,##0',
        'percent' => '0.0"%"',
    ];

    /**
     * @param  array<string, mixed>  $document
     */
    public function filename(array $document, string $extension): string
    {
        return str($document['key'].'-'.$document['reference'])->slug().'.'.$extension;
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public function pdf(array $document): Response
    {
        $this->ensureMemory('256M');
        $mpdf = $this->mpdf($document);
        $mpdf->WriteHTML(view('reports.pdf', ['document' => $document])->render());

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->filename($document, 'pdf').'"',
        ]);
    }

    /**
     * Long reports need more memory than the usual 128 MB. Only ever raises the limit.
     */
    private function ensureMemory(string $limit): void
    {
        $current = ini_get('memory_limit');

        if ($current !== '-1' && ini_parse_quantity((string) $current) < ini_parse_quantity($limit)) {
            ini_set('memory_limit', $limit);
        }
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public function mpdf(array $document): Mpdf
    {
        $tempDir = storage_path('framework/cache/mpdf');

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $document['landscape'] ? 'A4-L' : 'A4',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 14,
            'margin_bottom' => 16,
            'margin_header' => 6,
            'margin_footer' => 7,
            'tempDir' => $tempDir,
            'fontDir' => array_merge((new ConfigVariables)->getDefaults()['fontDir'], [resource_path('fonts')]),
            'fontdata' => (new FontVariables)->getDefaults()['fontdata'] + [
                'hindsiliguri' => ['R' => 'HindSiliguri-Regular.ttf', 'B' => 'HindSiliguri-SemiBold.ttf', 'useOTL' => 0xFF],
            ],
            'default_font' => 'hindsiliguri',
            'autoScriptToLang' => true,
            'autoLangToFont' => false,
            'useSubstitutions' => true,
        ]);
        $mpdf->SetTitle($document['title']);
        $mpdf->SetAuthor((string) $document['letterhead']['name']);
        $mpdf->SetCreator(config('app.name'));

        return $mpdf;
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public function xlsx(array $document): StreamedResponse
    {
        $spreadsheet = $this->spreadsheet($document);

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $this->filename($document, 'xlsx'), ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public function csv(array $document): StreamedResponse
    {
        return response()->streamDownload(function () use ($document): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [$document['title']]);
            fputcsv($output, [$document['period'], $document['branch'], $document['reference']]);
            fputcsv($output, []);

            if ($document['columns']) {
                fputcsv($output, array_column($document['columns'], 'label'));

                foreach ($document['rows'] as $row) {
                    fputcsv($output, array_map(fn (array $column) => $row[$column['key']] ?? '', $document['columns']));
                }

                if ($document['totals'] && $document['rows']) {
                    fputcsv($output, array_map(fn (array $column) => $document['totals'][$column['key']] ?? '', $document['columns']));
                }
            }

            foreach ($document['sections'] as $section) {
                fputcsv($output, []);

                if ($section['title']) {
                    fputcsv($output, [$section['title']]);
                }

                foreach ($section['lines'] as [$label, $value]) {
                    fputcsv($output, [$label, $value]);
                }
            }

            fclose($output);
        }, $this->filename($document, 'csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Build the Excel workbook: letterhead, title block, table with totals,
     * notes, signature lines and a print header/footer.
     *
     * @param  array<string, mixed>  $document
     */
    public function spreadsheet(array $document): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()->setTitle($document['title'])->setCreator((string) $document['letterhead']['name']);
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $document['title']), 0, 31));
        $sheet->setShowGridlines(false);

        $columns = $document['columns'];
        $width = max(count($columns) + 1, 4);
        $last = Coordinate::stringFromColumnIndex($width);
        $head = $document['letterhead'];

        $line = function (int $row, string $text, array $font = [], string $align = Alignment::HORIZONTAL_LEFT) use ($sheet, $last): void {
            $sheet->setCellValue("A{$row}", $text);
            $sheet->mergeCells("A{$row}:{$last}{$row}");
            $style = $sheet->getStyle("A{$row}");
            $style->getAlignment()->setHorizontal($align);
            $style->getFont()->applyFromArray($font);
        };

        $line(1, (string) $head['name'], ['bold' => true, 'size' => 16, 'color' => ['rgb' => self::INK]]);
        $sheet->getRowDimension(1)->setRowHeight(24);
        $row = 2;

        if ($head['tagline']) {
            $line($row++, (string) $head['tagline'], ['color' => ['rgb' => self::ACCENT]]);
        }

        $contact = array_filter([
            $head['address'],
            $head['phone'] ? __('Phone').': '.$head['phone'] : null,
            $head['bin'] ? __('VAT BIN').': '.$head['bin'] : null,
        ]);

        if ($contact) {
            $line($row++, implode('  ·  ', $contact), ['size' => 9, 'color' => ['rgb' => '6B7280']]);
        }

        $sheet->getStyle("A{$row}:{$last}{$row}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THICK)->getColor()->setRgb(self::INK);
        $sheet->getRowDimension($row)->setRowHeight(6);
        $row++;

        $line($row++, mb_strtoupper($document['title']), ['bold' => true, 'size' => 13], Alignment::HORIZONTAL_CENTER);
        $line($row++, $document['period'], ['size' => 9, 'color' => ['rgb' => '4B5563']], Alignment::HORIZONTAL_CENTER);
        $row++;

        $meta = [
            [__('Reference'), $document['reference'], __('Branch'), $document['branch']],
            [__('Prepared by'), $document['generated_by'], __('Printed on'), $document['generated_at']],
        ];
        $split = Coordinate::stringFromColumnIndex(intdiv($width, 2) + 1);

        foreach ($meta as [$labelA, $valueA, $labelB, $valueB]) {
            $sheet->setCellValue("A{$row}", $labelA.': '.$valueA);
            $sheet->setCellValue("{$split}{$row}", $labelB.': '.$valueB);
            $sheet->getStyle("A{$row}:{$last}{$row}")->getFont()->setSize(9);
            $row++;
        }

        $row++;

        if ($columns) {
            $row = $this->writeTable($sheet, $document, $row, $last);
        }

        foreach ($document['sections'] as $section) {
            $row = $this->writeSection($sheet, $section, $row + 1, $last);
        }

        if ($document['notes']) {
            $row++;
            $sheet->setCellValue("A{$row}", __('Notes'));
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;

            foreach ($document['notes'] as $index => $note) {
                $line($row++, ($index + 1).'. '.$note, ['size' => 9, 'color' => ['rgb' => '4B5563']]);
            }
        }

        if ($head['signatures']) {
            $row += 3;
            $roles = [__('Prepared by'), __('Checked by'), __('Approved by')];
            $positions = [1, max(2, intdiv($width, 2)), $width];

            foreach ($roles as $index => $role) {
                $cell = Coordinate::stringFromColumnIndex($positions[$index]);
                $sheet->setCellValue("{$cell}{$row}", $role);
                $style = $sheet->getStyle("{$cell}{$row}");
                $style->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
                $style->getFont()->setBold(true);
                $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            $row++;
        }

        if ($head['footer_note']) {
            $row++;
            $line($row, (string) $head['footer_note'], ['italic' => true, 'size' => 8, 'color' => ['rgb' => '6B7280']]);
        }

        $this->pageSetup($sheet, $document);

        return $spreadsheet;
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function writeTable(Worksheet $sheet, array $document, int $row, string $last): int
    {
        $columns = $document['columns'];
        $headerRow = $row;
        $sheet->setCellValue("A{$row}", __('SL'));

        foreach ($columns as $index => $column) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 2).$row, $column['label']);
        }

        $tableLast = Coordinate::stringFromColumnIndex(count($columns) + 1);
        $header = $sheet->getStyle("A{$row}:{$tableLast}{$row}");
        $header->getFont()->setBold(true)->getColor()->setRgb(self::INK);
        $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRgb(self::HEADER_FILL);
        $header->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRgb(self::INK);
        $header->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRgb(self::INK);
        $header->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $row++;
        $firstData = $row;

        foreach ($document['rows'] as $index => $data) {
            $sheet->setCellValue("A{$row}", $index + 1);

            foreach ($columns as $columnIndex => $column) {
                $value = $data[$column['key']] ?? null;
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex + 2).$row, $value);
            }

            if ($index % 2 === 1) {
                $sheet->getStyle("A{$row}:{$tableLast}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRgb('F9FAFB');
            }

            $row++;
        }

        $lastData = $row - 1;

        if ($document['rows']) {
            $sheet->getStyle("A{$firstData}:{$tableLast}{$lastData}")->getBorders()->getHorizontal()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRgb('D1D5DB');
        } else {
            $sheet->setCellValue("A{$row}", __('No records for this period.'));
            $sheet->mergeCells("A{$row}:{$tableLast}{$row}");
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
        }

        if ($document['totals'] && $document['rows']) {
            foreach ($columns as $columnIndex => $column) {
                $value = $document['totals'][$column['key']] ?? null;
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex + 2).$row, $value);
            }

            $totals = $sheet->getStyle("A{$row}:{$tableLast}{$row}");
            $totals->getFont()->setBold(true);
            $totals->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRgb(self::HEADER_FILL);
            $totals->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRgb(self::INK);
            $totals->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE)->getColor()->setRgb(self::INK);
            $row++;
        }

        $sheet->getStyle("A{$headerRow}:A".($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getColumnDimension('A')->setWidth(6);

        foreach ($columns as $columnIndex => $column) {
            $letter = Coordinate::stringFromColumnIndex($columnIndex + 2);
            $range = "{$letter}".($headerRow + 1).":{$letter}".($row - 1);

            if (isset(self::NUMBER_FORMATS[$column['format']])) {
                $sheet->getStyle($range)->getNumberFormat()->setFormatCode(self::NUMBER_FORMATS[$column['format']]);
                $sheet->getStyle("{$letter}{$headerRow}:{$letter}".($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }

            $longest = collect($document['rows'])->map(fn (array $data) => mb_strlen((string) ($data[$column['key']] ?? '')))->max() ?? 0;
            $sheet->getColumnDimension($letter)->setWidth(min(48, max(10, mb_strlen($column['label']) + 2, $longest + 3)));
        }

        $sheet->freezePane('A'.($headerRow + 1));
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);

        return $row;
    }

    /**
     * @param  array{title: ?string, lines: list<array{0: string, 1: mixed, 2: string, 3: string}>}  $section
     */
    private function writeSection(Worksheet $sheet, array $section, int $row, string $last): int
    {
        $labelEnd = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($last) - 1);

        if ($section['title']) {
            $sheet->setCellValue("A{$row}", mb_strtoupper($section['title']));
            $sheet->mergeCells("A{$row}:{$last}{$row}");
            $style = $sheet->getStyle("A{$row}:{$last}{$row}");
            $style->getFont()->setBold(true)->setSize(9)->getColor()->setRgb(self::INK);
            $style->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRgb(self::INK);
            $row++;
        }

        foreach ($section['lines'] as [$label, $value, $format, $style]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->mergeCells("A{$row}:{$labelEnd}{$row}");
            $sheet->setCellValue("{$last}{$row}", $value);
            $sheet->getStyle("{$last}{$row}")->getNumberFormat()->setFormatCode(self::NUMBER_FORMATS[$format] ?? '@');
            $range = $sheet->getStyle("A{$row}:{$last}{$row}");

            if ($style === 'muted') {
                $range->getFont()->getColor()->setRgb('6B7280');
            } elseif ($style === 'subtotal' || $style === 'total') {
                $range->getFont()->setBold(true);
                $range->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
            } elseif ($style === 'grand') {
                $this->grandTotal($range);
            }

            $row++;
        }

        $sheet->getColumnDimension($last)->setWidth(max(16, $sheet->getColumnDimension($last)->getWidth()));

        return $row;
    }

    private function grandTotal(Style $range): void
    {
        $range->getFont()->setBold(true)->setSize(12)->getColor()->setRgb(self::INK);
        $range->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRgb(self::HEADER_FILL);
        $range->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRgb(self::INK);
        $range->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE)->getColor()->setRgb(self::INK);
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function pageSetup(Worksheet $sheet, array $document): void
    {
        $setup = $sheet->getPageSetup();
        $setup->setPaperSize(PageSetup::PAPERSIZE_A4);
        $setup->setOrientation($document['landscape'] ? PageSetup::ORIENTATION_LANDSCAPE : PageSetup::ORIENTATION_PORTRAIT);
        $setup->setFitToWidth(1);
        $setup->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.6)->setBottom(0.6)->setLeft(0.4)->setRight(0.4)->setHeader(0.25)->setFooter(0.25);

        $clean = fn (?string $text): string => str_replace('&', '&&', (string) $text);
        $sheet->getHeaderFooter()
            ->setOddHeader('&L&8'.$clean($document['letterhead']['name']).'&R&8'.$clean($document['title']))
            ->setOddFooter('&L&8'.$clean($document['letterhead']['footer_note']).'&C&8'.$clean($document['reference']).'&R&8'.str_replace([':page', ':total'], ['&P', '&N'], $clean(__('Page :page of :total'))));
    }
}
