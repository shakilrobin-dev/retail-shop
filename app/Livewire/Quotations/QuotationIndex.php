<?php

namespace App\Livewire\Quotations;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Quotation;
use App\Services\CurrentBranch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class QuotationIndex extends Component
{
    use WithDataTable;

    public string $search = '';

    public function mount(): void
    {
        $this->authorize('pos.sell');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $this->authorize('pos.sell');
        $quotation = $this->query()->findOrFail($id);
        abort_if($quotation->sale_id !== null, 403);
        $quotation->delete();
        $this->dispatch('toast', message: __('Quotation deleted.'));
    }

    /**
     * @return array<string, string>
     */
    protected function sortableColumns(): array
    {
        return ['no' => 'id', 'date' => 'quotation_date', 'total' => 'total'];
    }

    /**
     * @return Builder<Quotation>
     */
    private function query(): Builder
    {
        return Quotation::query()
            ->with('customer', 'sale')
            ->withCount('items')
            ->where('branch_id', app(CurrentBranch::class)->get()?->id)
            ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('quotation_no', 'like', "%{$this->search}%")
                ->orWhere('customer_name', 'like', "%{$this->search}%")
                ->orWhere('customer_phone', 'like', "%{$this->search}%")));
    }

    public function render(): View
    {
        return view('livewire.quotations.quotation-index', [
            'quotations' => $this->applySorting($this->query(), 'id', 'desc')->paginate($this->perPage),
            'branch' => app(CurrentBranch::class)->get(),
            'printId' => session('print_quotation'),
        ])->title(__('Quotations'));
    }
}
