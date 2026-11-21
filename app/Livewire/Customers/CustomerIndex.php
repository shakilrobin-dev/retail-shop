<?php

namespace App\Livewire\Customers;

use App\Enums\SaleStatus;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Customer;
use App\Models\Sale;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerIndex extends Component
{
    use WithDataTable;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $phone = '';

    public string $address = '';

    public string $notes = '';

    public string $credit_limit = '';

    /** '' = all customers, 'due' = only customers who owe money. */
    #[Url]
    public string $dueFilter = '';

    public function updatedDueFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->reset('editingId', 'name', 'phone', 'address', 'notes', 'credit_limit');
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $customer = Customer::query()->findOrFail($id);
        $this->editingId = $customer->id;
        $this->name = $customer->name;
        $this->phone = (string) $customer->phone;
        $this->address = (string) $customer->address;
        $this->notes = (string) $customer->notes;
        $this->credit_limit = $customer->credit_limit === null ? '' : (string) (float) $customer->credit_limit;
        $this->showForm = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('customers', 'phone')->ignore($this->editingId)],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
        ]);

        Customer::query()->updateOrCreate(['id' => $this->editingId], array_map(fn ($value) => $value === '' ? null : $value, $validated));

        session()->flash('success', __('Customer saved.'));
        $this->reset('editingId', 'name', 'phone', 'address', 'notes', 'credit_limit', 'showForm');
    }

    /**
     * @return array<string, string>
     */
    protected function sortableColumns(): array
    {
        return ['name' => 'name', 'purchases' => 'sales_count', 'spent' => 'sales_sum_total', 'due' => 'due_sum', 'repairs' => 'repair_jobs_count', 'created' => 'created_at'];
    }

    public function export(): StreamedResponse
    {
        $rows = $this->query()->when($this->selected !== [], fn (Builder $query) => $query->whereIn('id', $this->selected))->orderBy('name')->get()
            ->map(fn (Customer $customer): array => [$customer->name, $customer->phone, $customer->address, $customer->sales_count, (float) $customer->sales_sum_total, (float) $customer->due_sum, $customer->credit_limit, $customer->repair_jobs_count]);

        return $this->csvDownload('customers', ['Name', 'Phone', 'Address', 'Purchases', 'Total spent', 'Due', 'Credit limit', 'Repairs'], $rows);
    }

    /**
     * @return Builder<Customer>
     */
    private function query(): Builder
    {
        return Customer::query()
            ->withCount(['sales' => fn (Builder $query) => $query->where('status', SaleStatus::Completed)])
            ->withSum(['sales' => fn (Builder $query) => $query->where('status', SaleStatus::Completed)], 'total')
            ->withSum(['sales as due_sum' => fn (Builder $query) => $query->where('status', SaleStatus::Completed)], 'due_amount')
            ->withCount('repairJobs')
            ->when($this->dueFilter === 'due', fn (Builder $query) => $query->whereHas('sales', fn (Builder $query) => $query->where('status', SaleStatus::Completed)->where('due_amount', '>', 0)))
            ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")));
    }

    public function render(): View
    {
        $customers = $this->applySorting($this->query(), 'name')->paginate($this->perPage);

        $completed = Sale::query()->where('status', SaleStatus::Completed)->whereNotNull('customer_id');

        return view('livewire.customers.customer-index', [
            'customers' => $customers,
            'summary' => [
                'total' => Customer::query()->count(),
                'new' => Customer::query()->where('created_at', '>=', today()->startOfMonth())->count(),
                'repeat' => (clone $completed)->selectRaw('customer_id')->groupBy('customer_id')->havingRaw('count(*) > 1')->get()->count(),
                'revenue' => (float) (clone $completed)->sum('total'),
                'due' => (float) (clone $completed)->sum('due_amount'),
                'due_customers' => (clone $completed)->where('due_amount', '>', 0)->distinct()->count('customer_id'),
            ],
        ])->title(__('Customers'));
    }
}
