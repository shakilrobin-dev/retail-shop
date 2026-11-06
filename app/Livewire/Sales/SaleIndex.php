<?php

namespace App\Livewire\Sales;

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\User;
use App\Services\CurrentBranch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SaleIndex extends Component
{
    use WithPagination;

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

    public function render(): View
    {
        $query = Sale::query()
            ->with(['customer', 'user'])
            ->withCount('items')
            ->where('branch_id', app(CurrentBranch::class)->get()?->id)
            ->where('status', '!=', SaleStatus::Held)
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('invoice_no', 'like', "%{$this->search}%")
                ->orWhereHas('customer', fn (Builder $query) => $query->where('phone', 'like', "%{$this->search}%")->orWhere('name', 'like', "%{$this->search}%"))))
            ->when($this->search === '' && $this->from !== '', fn (Builder $query) => $query->whereDate('sold_at', '>=', $this->from))
            ->when($this->search === '' && $this->to !== '', fn (Builder $query) => $query->whereDate('sold_at', '<=', $this->to))
            ->when($this->userId, fn (Builder $query) => $query->where('user_id', $this->userId));

        return view('livewire.sales.sale-index', [
            'sales' => (clone $query)->latest('sold_at')->paginate(25),
            'sum' => (clone $query)->sum('total'),
            'count' => (clone $query)->count(),
            'users' => User::query()->orderBy('name')->get(),
        ])->title(__('Sales'));
    }
}
