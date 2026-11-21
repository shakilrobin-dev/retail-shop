<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CustomerPayment;
use App\Models\Sale;
use App\Models\User;
use App\Services\CurrentBranch;
use App\Services\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class ReceiptController extends Controller
{
    /**
     * Printable receipt for a sale. ?paper=80|58|a4 and ?lang=en|bn override the settings.
     */
    public function show(Request $request, Sale $sale, Settings $settings, CurrentBranch $currentBranch): View
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->canAccessAllBranches() || $sale->branch_id === $currentBranch->get()?->id, 404);

        $locale = in_array($request->query('lang'), ['en', 'bn'], true)
            ? $request->query('lang')
            : $settings->get('receipt_language', $sale->branch_id);
        App::setLocale($locale);

        $paper = in_array($request->query('paper'), ['80', '58', 'a4'], true)
            ? $request->query('paper')
            : (string) $settings->get('receipt_paper', $sale->branch_id);

        $sale->load(['items', 'payments.paymentMethod', 'customer', 'user', 'branch']);

        return view('print.receipt', $this->shopDetails($settings, $sale->branch, $locale) + [
            'sale' => $sale,
            'paper' => $paper,
            'customerDue' => $sale->customer?->totalDue() ?? 0,
            'returnDays' => (int) $settings->get('return_policy_days', $sale->branch_id),
            'autoPrint' => ! $request->boolean('preview'),
        ]);
    }

    /**
     * Money receipt for a due collection.
     */
    public function due(Request $request, CustomerPayment $customerPayment, Settings $settings, CurrentBranch $currentBranch): View
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->canAccessAllBranches() || $customerPayment->branch_id === $currentBranch->get()?->id, 404);

        $locale = in_array($request->query('lang'), ['en', 'bn'], true)
            ? $request->query('lang')
            : $settings->get('receipt_language', $customerPayment->branch_id);
        App::setLocale($locale);

        $paper = in_array($request->query('paper'), ['80', '58', 'a4'], true)
            ? $request->query('paper')
            : (string) $settings->get('receipt_paper', $customerPayment->branch_id);

        $customerPayment->load(['customer', 'user', 'branch', 'paymentMethod', 'allocations.payable']);

        return view('print.due-receipt', $this->shopDetails($settings, $customerPayment->branch, $locale) + [
            'receipt' => $customerPayment,
            'paper' => $paper,
            'dueNow' => $customerPayment->customer->totalDue(),
            'autoPrint' => ! $request->boolean('preview'),
        ]);
    }

    /**
     * Shop name, address, logo and texts printed at the top and bottom of receipts.
     *
     * @return array<string, mixed>
     */
    private function shopDetails(Settings $settings, Branch $branch, string $locale): array
    {
        $isBangla = $locale === 'bn';

        return [
            'shopName' => ($isBangla ? $settings->get('shop_name_bn') : null) ?: $settings->get('shop_name'),
            'shopPhone' => $branch->phone ?: $settings->get('shop_phone'),
            'address' => ($isBangla ? $branch->address_bn : null) ?: $branch->address,
            'header' => ($isBangla ? $settings->get('receipt_header_bn', $branch->id) : null) ?: $settings->get('receipt_header', $branch->id),
            'footer' => ($isBangla ? $settings->get('receipt_footer_bn', $branch->id) : null) ?: $settings->get('receipt_footer', $branch->id),
            'logo' => $settings->bool('receipt_show_logo') ? $settings->get('shop_logo') : null,
            'vatBin' => $settings->bool('vat_enabled', $branch->id) ? $settings->get('vat_bin') : null,
        ];
    }

    /**
     * A tiny print job. The Rongta driver opens the cash drawer when it prints.
     */
    public function drawer(Request $request): View
    {
        activity()->causedBy($request->user())->log('Cash drawer opened (no sale)');

        return view('print.drawer');
    }
}
