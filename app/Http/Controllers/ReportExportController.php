<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CurrentBranch;
use App\Services\ReportDocument;
use App\Services\ReportExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class ReportExportController extends Controller
{
    /**
     * Download a report as an official PDF, a formatted Excel file or plain CSV.
     */
    public function __invoke(Request $request, ReportDocument $documents, ReportExporter $exporter, CurrentBranch $currentBranch): Response
    {
        $validated = $request->validate([
            'tab' => ['required', 'in:'.implode(',', array_keys(ReportDocument::REPORTS))],
            'format' => ['nullable', 'in:pdf,xlsx,csv'],
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

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $document = $documents->build($validated['tab'], $branchId, $from, $to, [
            'main_categories' => $request->boolean('main'),
            'dead_days' => (int) ($validated['deadDays'] ?? 60),
        ]);

        return match ($validated['format'] ?? 'csv') {
            'pdf' => $exporter->pdf($document),
            'xlsx' => $exporter->xlsx($document),
            default => $exporter->csv($document),
        };
    }
}
