<?php

namespace App\Http\Controllers;

use App\Livewire\Reports\ReportIndex;
use App\Models\User;
use App\Services\CurrentBranch;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    /**
     * Download a report as CSV (opens in Excel). UTF-8 with BOM so Bangla shows correctly.
     */
    public function __invoke(Request $request, ReportService $reports, CurrentBranch $currentBranch): StreamedResponse
    {
        $validated = $request->validate([
            'tab' => ['required', 'in:'.implode(',', ReportIndex::TABS)],
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
            'scope' => ['nullable', 'in:current,all'],
            'deadDays' => ['nullable', 'integer', 'min:1'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $branchId = ($validated['scope'] ?? 'current') === 'all' && $user->canAccessAllBranches() ? null : $currentBranch->get()?->id;
        $from = Carbon::parse($validated['from']);
        $to = Carbon::parse($validated['to']);

        [$header, $rows] = match ($validated['tab']) {
            'summary' => [['Item', 'Amount'], collect($reports->summary($branchId, $from, $to))->map(fn ($value, $key) => [$key, $value])->values()->all()],
            'daily' => [['Date', 'Invoices', 'Sales', 'Profit'], $reports->daily($branchId, $from, $to)->map(fn ($row) => [$row->day, $row->invoices, $row->sales, $row->profit])->all()],
            'products' => [['Product', 'Qty (pcs)', 'Sales', 'Profit'], $reports->topProducts($branchId, $from, $to, 1000)->map(fn ($row) => [$row->name, $row->quantity + 0, $row->revenue, $row->profit])->all()],
            'categories' => [['Category', 'Qty (pcs)', 'Sales', 'Profit'], $reports->byCategory($branchId, $from, $to, $request->boolean('main'))->map(fn ($row) => [$row['name'], $row['quantity'] + 0, $row['revenue'], $row['profit']])->all()],
            'payments' => [['Payment method', 'Money in', 'Money out', 'Net'], $reports->byPaymentMethod($branchId, $from, $to)->map(fn ($row) => [$row->name, $row->money_in, $row->money_out, $row->money_in - $row->money_out])->all()],
            'staff' => [['Staff', 'Invoices', 'Sales', 'Discounts'], $reports->byStaff($branchId, $from, $to)->map(fn ($row) => [$row->name, $row->invoices, $row->sales, $row->discounts])->all()],
            'dead-stock' => [['Product', 'SKU', 'Qty', 'Value at cost'], $reports->deadStock($branchId, (int) ($validated['deadDays'] ?? 60))->map(fn ($row) => [$row->name, $row->sku, $row->quantity + 0, $row->value])->all()],
        };

        $filename = sprintf('report-%s-%s-to-%s.csv', $validated['tab'], $from->toDateString(), $to->toDateString());

        return response()->streamDownload(function () use ($header, $rows): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $header);

            foreach ($rows as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
