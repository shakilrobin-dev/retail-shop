<?php

namespace App\Livewire\Purchases;

use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\CurrentBranch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class PurchaseIndex extends Component
{
    use WithPagination;

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

    public function render(): View
    {
        $query = Purchase::query()
            ->with(['supplier', 'user'])
            ->withCount('items')
            ->where('branch_id', app(CurrentBranch::class)->get()?->id)
            ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('reference_no', 'like', "%{$this->search}%")
                ->orWhere('supplier_invoice_no', 'like', "%{$this->search}%")))
            ->when($this->supplierId, fn (Builder $query) => $query->where('supplier_id', $this->supplierId))
            ->when($this->from !== '', fn (Builder $query) => $query->whereDate('purchase_date', '>=', $this->from))
            ->when($this->to !== '', fn (Builder $query) => $query->whereDate('purchase_date', '<=', $this->to));

        return view('livewire.purchases.purchase-index', [
            'purchases' => (clone $query)->latest('purchase_date')->latest('id')->paginate(25),
            'filteredTotal' => (clone $query)->sum('total'),
            'suppliers' => Supplier::query()->orderBy('name')->get(),
        ])->title(__('Purchases'));
    }
}
