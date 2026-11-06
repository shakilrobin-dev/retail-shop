<?php

namespace App\Livewire\Repairs;

use App\Enums\RepairStatus;
use App\Livewire\Concerns\WithDataTable;
use App\Models\RepairJob;
use App\Services\CurrentBranch;
use App\Services\RepairService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RepairIndex extends Component
{
    use WithDataTable;

    #[Url(as: 'q')]
    public string $search = '';

    /** 'open' = all not delivered/cancelled */
    #[Url]
    public string $status = 'open';

    /** 'kanban' or 'list' */
    #[Url]
    public string $view = 'kanban';

    public function mount(): void
    {
        $this->authorize('repairs.manage');
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
        return ['job' => 'job_no', 'delivery' => 'estimated_delivery_at', 'total' => 'total', 'received' => 'created_at'];
    }

    /**
     * Drag a card to another column on the board.
     */
    public function moveTo(int $jobId, string $status, RepairService $repairs): void
    {
        $this->authorize('repairs.manage');

        $job = RepairJob::query()->where('branch_id', app(CurrentBranch::class)->get()?->id)->findOrFail($jobId);
        $target = RepairStatus::from($status);

        if ($job->status === $target) {
            return;
        }

        try {
            $repairs->changeStatus($job, $target);
            $this->dispatch('toast', message: __(':job moved to :status.', ['job' => $job->job_no, 'status' => $target->label()]));
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'error', message: collect($exception->errors())->flatten()->first());
        }
    }

    public function export(): StreamedResponse
    {
        $rows = $this->query()->latest()->get()->map(fn (RepairJob $job): array => [
            $job->job_no, $job->created_at->format('Y-m-d'), $job->customer->name, $job->customer->phone,
            trim($job->device_brand.' '.$job->device_model), $job->imei_serial, $job->problem, $job->status->label(),
            $job->estimated_delivery_at?->format('Y-m-d H:i'), (float) $job->total, (float) $job->paid_amount,
        ]);

        return $this->csvDownload('repairs', ['Job no', 'Received', 'Customer', 'Phone', 'Device', 'IMEI', 'Problem', 'Status', 'Delivery', 'Total', 'Paid'], $rows);
    }

    /**
     * @return Builder<RepairJob>
     */
    private function query(): Builder
    {
        $closed = [RepairStatus::Delivered->value, RepairStatus::Cancelled->value];

        return RepairJob::query()
            ->with(['customer', 'technician'])
            ->where('branch_id', app(CurrentBranch::class)->get()?->id)
            ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('job_no', 'like', "%{$this->search}%")
                ->orWhere('imei_serial', 'like', "%{$this->search}%")
                ->orWhere('device_model', 'like', "%{$this->search}%")
                ->orWhereHas('customer', fn (Builder $query) => $query->where('phone', 'like', "%{$this->search}%")->orWhere('name', 'like', "%{$this->search}%"))))
            ->when($this->search === '' && $this->status === 'open', fn (Builder $query) => $query->whereNotIn('status', $closed))
            ->when($this->search === '' && ! in_array($this->status, ['open', 'all'], true), fn (Builder $query) => $query->where('status', $this->status));
    }

    public function render(): View
    {
        $branchId = app(CurrentBranch::class)->get()?->id;
        $closed = [RepairStatus::Delivered->value, RepairStatus::Cancelled->value];

        $counts = RepairJob::query()->where('branch_id', $branchId)
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $boardStatuses = [RepairStatus::Received, RepairStatus::Diagnosing, RepairStatus::WaitingForParts, RepairStatus::Repairing, RepairStatus::Ready];

        $board = [];

        if ($this->view === 'kanban') {
            $jobs = RepairJob::query()->with('customer')
                ->where('branch_id', $branchId)
                ->whereIn('status', array_map(fn (RepairStatus $status) => $status->value, $boardStatuses))
                ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                    ->where('job_no', 'like', "%{$this->search}%")
                    ->orWhere('imei_serial', 'like', "%{$this->search}%")
                    ->orWhere('device_model', 'like', "%{$this->search}%")
                    ->orWhereHas('customer', fn (Builder $query) => $query->where('phone', 'like', "%{$this->search}%")->orWhere('name', 'like', "%{$this->search}%"))))
                ->orderByRaw('estimated_delivery_at is null, estimated_delivery_at')
                ->get()
                ->groupBy(fn (RepairJob $job): string => $job->status->value);

            foreach ($boardStatuses as $boardStatus) {
                $board[$boardStatus->value] = ['status' => $boardStatus, 'jobs' => $jobs->get($boardStatus->value, collect())];
            }
        }

        return view('livewire.repairs.repair-index', [
            'jobs' => $this->view === 'list'
                ? $this->applySorting($this->query(), 'estimated_delivery_at')->paginate($this->perPage)
                : null,
            'board' => $board,
            'counts' => $counts,
            'openCount' => $counts->except($closed)->sum(),
        ])->title(__('Repairs'));
    }
}
