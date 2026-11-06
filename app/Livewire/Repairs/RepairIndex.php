<?php

namespace App\Livewire\Repairs;

use App\Enums\RepairStatus;
use App\Models\RepairJob;
use App\Services\CurrentBranch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class RepairIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    /** 'open' = all not delivered/cancelled */
    #[Url]
    public string $status = 'open';

    public function mount(): void
    {
        $this->authorize('repairs.manage');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $branchId = app(CurrentBranch::class)->get()?->id;
        $closed = [RepairStatus::Delivered->value, RepairStatus::Cancelled->value];

        $jobs = RepairJob::query()
            ->with(['customer', 'technician'])
            ->where('branch_id', $branchId)
            ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('job_no', 'like', "%{$this->search}%")
                ->orWhere('imei_serial', 'like', "%{$this->search}%")
                ->orWhere('device_model', 'like', "%{$this->search}%")
                ->orWhereHas('customer', fn (Builder $query) => $query->where('phone', 'like', "%{$this->search}%")->orWhere('name', 'like', "%{$this->search}%"))))
            ->when($this->search === '' && $this->status === 'open', fn (Builder $query) => $query->whereNotIn('status', $closed))
            ->when($this->search === '' && ! in_array($this->status, ['open', 'all'], true), fn (Builder $query) => $query->where('status', $this->status))
            ->orderByRaw('estimated_delivery_at is null, estimated_delivery_at')
            ->latest()
            ->paginate(25);

        $counts = RepairJob::query()->where('branch_id', $branchId)
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('livewire.repairs.repair-index', [
            'jobs' => $jobs,
            'counts' => $counts,
            'openCount' => $counts->except($closed)->sum(),
        ])->title(__('Repairs'));
    }
}
