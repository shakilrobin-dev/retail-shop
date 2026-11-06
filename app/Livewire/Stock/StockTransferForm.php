<?php

namespace App\Livewire\Stock;

use App\Livewire\Concerns\SearchesProducts;
use App\Livewire\Concerns\ShowsStockErrors;
use App\Models\Branch;
use App\Models\ProductUnit;
use App\Services\CurrentBranch;
use App\Services\InventoryService;
use App\Services\StockService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class StockTransferForm extends Component
{
    use SearchesProducts, ShowsStockErrors;

    public ?int $to_branch_id = null;

    public string $note = '';

    /** @var list<array{product_id: int, name: string, available: float, quantity: string}> */
    public array $lines = [];

    public function pickUnit(int $productUnitId): void
    {
        $unit = ProductUnit::query()->with('product')->findOrFail($productUnitId);

        foreach ($this->lines as $index => $line) {
            if ($line['product_id'] === $unit->product_id) {
                $this->lines[$index]['quantity'] = (string) ((float) $line['quantity'] + (float) $unit->quantity);

                return;
            }
        }

        $this->lines[] = [
            'product_id' => $unit->product_id,
            'name' => $unit->product->name,
            'available' => app(StockService::class)->quantity(app(CurrentBranch::class)->get(), $unit->product),
            'quantity' => (string) (float) $unit->quantity,
        ];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function save(InventoryService $inventory): void
    {
        $this->authorize('stock.transfer');

        $this->validate([
            'to_branch_id' => ['required', 'exists:branches,id'],
            'note' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ], [], ['to_branch_id' => __('branch'), 'lines' => __('products')]);

        $transfer = $inventory->send(
            app(CurrentBranch::class)->get(),
            Branch::query()->findOrFail($this->to_branch_id),
            collect($this->lines)->mapWithKeys(fn (array $line): array => [$line['product_id'] => (float) $line['quantity']])->all(),
            $this->note ?: null,
        );

        session()->flash('success', __('Transfer :no sent.', ['no' => $transfer->reference_no]));
        $this->redirectRoute('stock.transfers.show', $transfer, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.stock.stock-transfer-form', [
            'branches' => Branch::query()->where('is_active', true)->whereKeyNot(app(CurrentBranch::class)->get()?->id)->orderBy('name')->get(),
            'results' => $this->productResults(),
        ])->title(__('Send stock to branch'));
    }
}
