<?php

namespace App\Livewire\Stock;

use App\Models\StockTransfer;
use App\Services\CurrentBranch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class StockTransferIndex extends Component
{
    use WithPagination;

    public function render(): View
    {
        $branchId = app(CurrentBranch::class)->get()?->id;

        return view('livewire.stock.stock-transfer-index', [
            'branchId' => $branchId,
            'transfers' => StockTransfer::query()
                ->with(['fromBranch', 'toBranch', 'sender'])
                ->withCount('items')
                ->where(fn (Builder $query) => $query->where('from_branch_id', $branchId)->orWhere('to_branch_id', $branchId))
                ->latest('id')
                ->paginate(25),
        ])->title(__('Stock transfers'));
    }
}
