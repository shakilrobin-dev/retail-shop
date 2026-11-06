<?php

namespace App\Http\Controllers;

use App\Models\RepairJob;
use App\Services\BarcodeService;
use App\Services\CurrentBranch;
use App\Services\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class RepairPrintController extends Controller
{
    public function slip(Request $request, RepairJob $job, Settings $settings, BarcodeService $barcodes, CurrentBranch $currentBranch): View
    {
        return $this->render('print.repair-slip', $request, $job, $settings, $barcodes, $currentBranch);
    }

    public function bill(Request $request, RepairJob $job, Settings $settings, BarcodeService $barcodes, CurrentBranch $currentBranch): View
    {
        return $this->render('print.repair-bill', $request, $job, $settings, $barcodes, $currentBranch);
    }

    private function render(string $view, Request $request, RepairJob $job, Settings $settings, BarcodeService $barcodes, CurrentBranch $currentBranch): View
    {
        abort_unless($request->user()->canAccessAllBranches() || $job->branch_id === $currentBranch->get()?->id, 404);

        $locale = in_array($request->query('lang'), ['en', 'bn'], true) ? $request->query('lang') : $settings->get('receipt_language', $job->branch_id);
        App::setLocale($locale);
        $isBangla = $locale === 'bn';

        $job->load(['customer', 'parts', 'payments.paymentMethod', 'branch', 'receiver', 'technician']);
        $paper = in_array($request->query('paper'), ['80', '58', 'a4'], true) ? $request->query('paper') : (string) $settings->get('receipt_paper', $job->branch_id);

        return view($view, [
            'job' => $job,
            'paper' => $paper,
            'shopName' => ($isBangla ? $settings->get('shop_name_bn') : null) ?: $settings->get('shop_name'),
            'shopPhone' => $job->branch->phone ?: $settings->get('shop_phone'),
            'address' => ($isBangla ? $job->branch->address_bn : null) ?: $job->branch->address,
            'terms' => ($isBangla ? $settings->get('repair_terms_bn', $job->branch_id) : null) ?: $settings->get('repair_terms', $job->branch_id),
            'barcodeSvg' => $barcodes->svg($job->job_no, 260, 40),
        ]);
    }
}
