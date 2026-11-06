<?php

namespace App\Livewire\Suppliers;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\CurrentBranch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierIndex extends Component
{
    use WithDataTable;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $company = '';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public string $notes = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $supplier = Supplier::query()->findOrFail($id);
        $this->editingId = $supplier->id;
        $this->name = $supplier->name;
        $this->company = (string) $supplier->company;
        $this->phone = (string) $supplier->phone;
        $this->email = (string) $supplier->email;
        $this->address = (string) $supplier->address;
        $this->notes = (string) $supplier->notes;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('purchases.manage');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        Supplier::query()->updateOrCreate(['id' => $this->editingId], array_map(fn ($value) => $value === '' ? null : $value, $validated));

        session()->flash('success', __('Supplier saved.'));
        $this->resetForm();
    }

    public function toggleActive(int $id): void
    {
        $this->authorize('purchases.manage');
        $supplier = Supplier::query()->findOrFail($id);
        $supplier->update(['is_active' => ! $supplier->is_active]);
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'name', 'company', 'phone', 'email', 'address', 'notes', 'showForm');
        $this->resetValidation();
    }

    /**
     * @return array<string, string>
     */
    protected function sortableColumns(): array
    {
        return ['name' => 'name', 'purchases' => 'purchases_count', 'total' => 'purchases_sum_total'];
    }

    public function export(): StreamedResponse
    {
        $rows = $this->query()->orderBy('name')->get()
            ->map(fn (Supplier $supplier): array => [$supplier->name, $supplier->company, $supplier->phone, $supplier->email, $supplier->address, $supplier->purchases_count, (float) $supplier->purchases_sum_total]);

        return $this->csvDownload('suppliers', ['Name', 'Company', 'Phone', 'Email', 'Address', 'Purchases', 'Total bought'], $rows);
    }

    /**
     * @return Builder<Supplier>
     */
    private function query(): Builder
    {
        return Supplier::query()
            ->withCount('purchases')
            ->withSum('purchases', 'total')
            ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('company', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")));
    }

    public function render(): View
    {
        $suppliers = $this->applySorting($this->query(), 'name')->paginate($this->perPage);

        $branchId = app(CurrentBranch::class)->get()?->id;
        $monthPurchases = Purchase::query()->where('branch_id', $branchId)->whereDate('purchase_date', '>=', today()->startOfMonth());
        $top = Purchase::query()->with('supplier')->where('branch_id', $branchId)->whereNotNull('supplier_id')
            ->selectRaw('supplier_id, SUM(total) as total')->groupBy('supplier_id')->orderByDesc('total')->first();

        return view('livewire.suppliers.supplier-index', [
            'suppliers' => $suppliers,
            'summary' => [
                'active' => Supplier::query()->where('is_active', true)->count(),
                'month_total' => (float) (clone $monthPurchases)->sum('total'),
                'month_count' => (clone $monthPurchases)->count(),
                'top' => $top?->supplier?->name,
                'top_total' => (float) ($top->total ?? 0),
            ],
        ])->title(__('Suppliers'));
    }
}
