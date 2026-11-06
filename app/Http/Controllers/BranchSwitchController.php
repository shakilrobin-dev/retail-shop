<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\CurrentBranch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BranchSwitchController extends Controller
{
    /**
     * Change the branch an admin is working in.
     */
    public function __invoke(Request $request, CurrentBranch $currentBranch): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer'],
        ]);

        $branch = $currentBranch->available()->firstWhere('id', $validated['branch_id']);

        abort_if(! $branch instanceof Branch, 403);

        $currentBranch->switchTo($branch);

        return back();
    }
}
