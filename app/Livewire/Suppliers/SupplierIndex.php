<?php

namespace App\Livewire\Suppliers;

use App\Models\Supplier;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class SupplierIndex extends Component
{
    use WithPagination;

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

    public function render(): View
    {
        $suppliers = Supplier::query()
            ->withCount('purchases')
            ->withSum('purchases', 'total')
            ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('company', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")))
            ->orderBy('name')
            ->paginate(25);

        return view('livewire.suppliers.supplier-index', ['suppliers' => $suppliers])->title(__('Suppliers'));
    }
}
