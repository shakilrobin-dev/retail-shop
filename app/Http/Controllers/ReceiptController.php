<?php

namespace App\Http\Controllers;

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
        $isBangla = $locale === 'bn';

        return view('print.receipt', [
            'sale' => $sale,
            'paper' => $paper,
            'shopName' => ($isBangla ? $settings->get('shop_name_bn') : null) ?: $settings->get('shop_name'),
            'shopPhone' => $sale->branch->phone ?: $settings->get('shop_phone'),
            'address' => ($isBangla ? $sale->branch->address_bn : null) ?: $sale->branch->address,
            'header' => ($isBangla ? $settings->get('receipt_header_bn', $sale->branch_id) : null) ?: $settings->get('receipt_header', $sale->branch_id),
            'footer' => ($isBangla ? $settings->get('receipt_footer_bn', $sale->branch_id) : null) ?: $settings->get('receipt_footer', $sale->branch_id),
            'logo' => $settings->bool('receipt_show_logo') ? $settings->get('shop_logo') : null,
            'vatBin' => $settings->bool('vat_enabled', $sale->branch_id) ? $settings->get('vat_bin') : null,
            'returnDays' => (int) $settings->get('return_policy_days', $sale->branch_id),
            'autoPrint' => ! $request->boolean('preview'),
        ]);
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
