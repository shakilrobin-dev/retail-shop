<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Models\User;
use App\Services\CurrentBranch;
use App\Services\ReportDocument;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class QuotationPrintController extends Controller
{
    /**
     * Printable A4 quotation with the shop letterhead and branch.
     */
    public function __invoke(Request $request, Quotation $quotation, CurrentBranch $currentBranch, ReportDocument $documents): View
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->canAccessAllBranches() || $quotation->branch_id === $currentBranch->get()?->id, 404);

        $quotation->load(['items.product.units', 'customer', 'user', 'branch']);

        return view('print.quotation', [
            'quotation' => $quotation,
            'letterhead' => $documents->letterhead($quotation->branch),
        ]);
    }
}
