<?php

namespace App\Livewire\Customers;

use App\Enums\SaleStatus;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class CustomerIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $phone = '';

    public string $address = '';

    public string $notes = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->reset('editingId', 'name', 'phone', 'address', 'notes');
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
        $this->showForm = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('customers', 'phone')->ignore($this->editingId)],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        Customer::query()->updateOrCreate(['id' => $this->editingId], array_map(fn ($value) => $value === '' ? null : $value, $validated));

        session()->flash('success', __('Customer saved.'));
        $this->reset('editingId', 'name', 'phone', 'address', 'notes', 'showForm');
    }

    public function render(): View
    {
        $customers = Customer::query()
            ->withCount(['sales' => fn (Builder $query) => $query->where('status', SaleStatus::Completed)])
            ->withSum(['sales' => fn (Builder $query) => $query->where('status', SaleStatus::Completed)], 'total')
            ->withCount('repairJobs')
            ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")))
            ->orderBy('name')
            ->paginate(25);

        return view('livewire.customers.customer-index', ['customers' => $customers])->title(__('Customers'));
    }
}
