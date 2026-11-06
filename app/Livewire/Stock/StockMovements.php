<?php

namespace App\Livewire\Stock;

use App\Models\Product;
use App\Models\StockMovement;
use App\Services\CurrentBranch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class StockMovements extends Component
{
    use WithPagination;

    #[Url]
    public ?int $productId = null;

    #[Url]
    public string $type = '';

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
        $movements = StockMovement::query()
            ->with(['product', 'user', 'reference'])
            ->where('branch_id', app(CurrentBranch::class)->get()?->id)
            ->when($this->productId, fn (Builder $query) => $query->where('product_id', $this->productId))
            ->when($this->type !== '', fn (Builder $query) => $query->where('type', $this->type))
            ->when($this->from !== '', fn (Builder $query) => $query->whereDate('created_at', '>=', $this->from))
            ->when($this->to !== '', fn (Builder $query) => $query->whereDate('created_at', '<=', $this->to))
            ->latest('id')
            ->paginate(50);

        return view('livewire.stock.stock-movements', [
            'movements' => $movements,
            'product' => $this->productId ? Product::query()->with('units')->find($this->productId) : null,
        ])->title(__('Stock history'));
    }
}
