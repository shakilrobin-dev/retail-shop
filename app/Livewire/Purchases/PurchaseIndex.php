<?php

namespace App\Livewire\Purchases;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\CurrentBranch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseIndex extends Component
{
    use WithDataTable;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public ?int $supplierId = null;

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<string, string>
     */
    protected function sortableColumns(): array
    {
        return ['date' => 'purchase_date', 'number' => 'reference_no', 'items' => 'items_count', 'total' => 'total'];
    }

    /**
     * @return Builder<Purchase>
     */
    private function query(): Builder
    {
        return Purchase::query()
            ->with(['supplier', 'user'])
            ->withCount('items')
            ->where('branch_id', app(CurrentBranch::class)->get()?->id)
            ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('reference_no', 'like', "%{$this->search}%")
                ->orWhere('supplier_invoice_no', 'like', "%{$this->search}%")))
            ->when($this->supplierId, fn (Builder $query) => $query->where('supplier_id', $this->supplierId))
            ->when($this->from !== '', fn (Builder $query) => $query->whereDate('purchase_date', '>=', $this->from))
            ->when($this->to !== '', fn (Builder $query) => $query->whereDate('purchase_date', '<=', $this->to));
    }

    public function export(): StreamedResponse
    {
        $rows = $this->query()->latest('purchase_date')->get()
            ->map(fn (Purchase $purchase): array => [$purchase->reference_no, $purchase->purchase_date->toDateString(), $purchase->supplier?->name, $purchase->supplier_invoice_no, $purchase->items_count, (float) $purchase->subtotal, (float) $purchase->discount, (float) $purchase->total, $purchase->user?->name]);

        return $this->csvDownload('purchases', ['Purchase no', 'Date', 'Supplier', 'Supplier invoice', 'Items', 'Subtotal', 'Discount', 'Total', 'Entered by'], $rows);
    }

    public function render(): View
    {
        $query = $this->query();

        return view('livewire.purchases.purchase-index', [
            'purchases' => $this->applySorting(clone $query, 'purchase_date', 'desc')->paginate($this->perPage),
            'filteredTotal' => (clone $query)->sum('total'),
            'filteredCount' => (clone $query)->count(),
            'suppliers' => Supplier::query()->orderBy('name')->get(),
        ])->title(__('Purchases'));
    }
}
