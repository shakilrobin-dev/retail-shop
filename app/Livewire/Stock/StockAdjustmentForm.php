<?php

namespace App\Livewire\Stock;

use App\Enums\AdjustmentReason;
use App\Livewire\Concerns\SearchesProducts;
use App\Livewire\Concerns\ShowsStockErrors;
use App\Models\ProductUnit;
use App\Models\StockAdjustment;
use App\Services\CurrentBranch;
use App\Services\InventoryService;
use App\Services\StockService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Two ways to fix stock:
 *  - "change": enter how many to add/remove (damaged, lost, found...)
 *  - "count": scan the shelf, enter what you counted; the difference is saved.
 */
class StockAdjustmentForm extends Component
{
    use SearchesProducts, ShowsStockErrors;

    public string $mode = 'change';

    public string $reason = 'damaged';

    public string $note = '';

    /** @var list<array{product_id: int, name: string, current: float, value: string}> */
    public array $lines = [];

    public function updatedMode(): void
    {
        $this->reason = $this->mode === 'count' ? AdjustmentReason::CountCorrection->value : AdjustmentReason::Damaged->value;

        foreach ($this->lines as $index => $line) {
            $this->lines[$index]['value'] = $this->mode === 'count' ? (string) ($line['current'] + 0) : '';
        }
    }

    public function pickUnit(int $productUnitId): void
    {
        $unit = ProductUnit::query()->with('product')->findOrFail($productUnitId);

        foreach ($this->lines as $index => $line) {
            if ($line['product_id'] === $unit->product_id) {
                if ($this->mode === 'count') {
                    $this->lines[$index]['value'] = (string) ((float) $line['value'] + (float) $unit->quantity);
                }

                return;
            }
        }

        $current = app(StockService::class)->quantity(app(CurrentBranch::class)->get(), $unit->product);

        $this->lines[] = [
            'product_id' => $unit->product_id,
            'name' => $unit->product->name,
            'current' => $current,
            'value' => $this->mode === 'count' ? (string) (float) $unit->quantity : '-'.(float) $unit->quantity,
        ];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function save(InventoryService $inventory): void
    {
        $this->authorize('stock.adjust');

        $this->validate([
            'reason' => ['required', Rule::enum(AdjustmentReason::class)],
            'note' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.value' => ['required', 'numeric'],
        ], [], ['lines' => __('products'), 'lines.*.value' => __('quantity')]);

        $changes = [];

        foreach ($this->lines as $line) {
            $changes[$line['product_id']] = $this->mode === 'count'
                ? (float) $line['value'] - $line['current']
                : (float) $line['value'];
        }

        $adjustment = $inventory->adjust(app(CurrentBranch::class)->get(), AdjustmentReason::from($this->reason), $changes, $this->note ?: null);

        session()->flash('success', __('Stock adjustment :no saved.', ['no' => $adjustment->reference_no]));
        $this->redirectRoute('stock.adjust', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.stock.stock-adjustment-form', [
            'results' => $this->productResults(),
            'recent' => StockAdjustment::query()->with(['items.product', 'user'])
                ->where('branch_id', app(CurrentBranch::class)->get()?->id)->latest()->limit(10)->get(),
        ])->title(__('Adjust / count stock'));
    }
}
