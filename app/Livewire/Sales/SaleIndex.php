<?php

namespace App\Livewire\Sales;

use App\Enums\SaleStatus;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Sale;
use App\Models\User;
use App\Services\CurrentBranch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SaleIndex extends Component
{
    use WithDataTable;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = 'completed';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public ?int $userId = null;

    public function mount(): void
    {
        $this->from = $this->from ?: today()->toDateString();
        $this->to = $this->to ?: today()->toDateString();
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<string, string>
     */
    protected function sortableColumns(): array
    {
        return ['date' => 'sold_at', 'invoice' => 'invoice_no', 'items' => 'items_count', 'total' => 'total'];
    }

    /**
     * @return Builder<Sale>
     */
    private function query(?string $status = null): Builder
    {
        $status ??= $this->status;

        return Sale::query()
            ->with(['customer', 'user'])
            ->withCount('items')
            ->where('branch_id', app(CurrentBranch::class)->get()?->id)
            ->where('status', '!=', SaleStatus::Held)
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('invoice_no', 'like', "%{$this->search}%")
                ->orWhereHas('customer', fn (Builder $query) => $query->where('phone', 'like', "%{$this->search}%")->orWhere('name', 'like', "%{$this->search}%"))))
            ->when($this->search === '' && $this->from !== '', fn (Builder $query) => $query->whereDate('sold_at', '>=', $this->from))
            ->when($this->search === '' && $this->to !== '', fn (Builder $query) => $query->whereDate('sold_at', '<=', $this->to))
            ->when($this->userId, fn (Builder $query) => $query->where('user_id', $this->userId));
    }

    public function export(): StreamedResponse
    {
        $rows = $this->query()->when($this->selected !== [], fn (Builder $query) => $query->whereIn('id', $this->selected))->latest('sold_at')->get()
            ->map(fn (Sale $sale): array => [$sale->invoice_no, $sale->sold_at?->format('Y-m-d H:i'), $sale->customer?->name, $sale->customer?->phone, $sale->items_count, (float) $sale->subtotal, (float) $sale->discount_amount, (float) $sale->vat_amount, (float) $sale->total, $sale->status->value, $sale->user?->name]);

        return $this->csvDownload('sales', ['Invoice', 'Date', 'Customer', 'Phone', 'Items', 'Subtotal', 'Discount', 'VAT', 'Total', 'Status', 'Cashier'], $rows);
    }

    public function render(): View
    {
        $query = $this->query();

        return view('livewire.sales.sale-index', [
            'sales' => $this->applySorting(clone $query, 'sold_at', 'desc')->paginate($this->perPage),
            'sum' => (clone $query)->sum('total'),
            'count' => (clone $query)->count(),
            'tabs' => [
                'completed' => [SaleStatus::Completed->label(), $this->query('completed')->count()],
                'voided' => [SaleStatus::Voided->label(), $this->query('voided')->count()],
                '' => [__('All'), $this->query('')->count()],
            ],
            'users' => User::query()->orderBy('name')->get(),
        ])->title(__('Sales'));
    }
}
