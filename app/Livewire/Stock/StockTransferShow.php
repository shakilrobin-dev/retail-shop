<?php

namespace App\Livewire\Stock;

use App\Enums\TransferStatus;
use App\Livewire\Concerns\ShowsStockErrors;
use App\Models\StockTransfer;
use App\Services\CurrentBranch;
use App\Services\InventoryService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class StockTransferShow extends Component
{
    use ShowsStockErrors;

    public StockTransfer $transfer;

    /** @var array<int, string> item id => received quantity */
    public array $received = [];

    public function mount(StockTransfer $transfer): void
    {
        $branchId = app(CurrentBranch::class)->get()?->id;
        abort_unless(in_array($branchId, [$transfer->from_branch_id, $transfer->to_branch_id], true), 404);

        $this->transfer = $transfer;

        foreach ($transfer->items as $item) {
            $this->received[$item->id] = (string) ($item->quantity_sent + 0);
        }
    }

    public function receive(InventoryService $inventory): void
    {
        $this->authorize('stock.transfer');
        abort_unless($this->transfer->to_branch_id === app(CurrentBranch::class)->get()?->id, 403);
        $this->validate(['received.*' => ['required', 'numeric', 'min:0']]);

        $inventory->receive($this->transfer, $this->received);
        session()->flash('success', __('Stock received.'));
        $this->redirectRoute('stock.transfers.show', $this->transfer, navigate: true);
    }

    public function cancel(InventoryService $inventory): void
    {
        $this->authorize('stock.transfer');
        abort_unless($this->transfer->from_branch_id === app(CurrentBranch::class)->get()?->id, 403);

        $inventory->cancel($this->transfer);
        session()->flash('success', __('Transfer cancelled. Stock is back.'));
        $this->redirectRoute('stock.transfers.show', $this->transfer, navigate: true);
    }

    public function render(): View
    {
        $this->transfer->load(['items.product', 'fromBranch', 'toBranch', 'sender', 'receiver']);
        $branchId = app(CurrentBranch::class)->get()?->id;
        $open = $this->transfer->status === TransferStatus::InTransit;

        return view('livewire.stock.stock-transfer-show', [
            'canReceive' => $open && $this->transfer->to_branch_id === $branchId,
            'canCancel' => $open && $this->transfer->from_branch_id === $branchId,
        ])->title(__('Transfer').' '.$this->transfer->reference_no);
    }
}
