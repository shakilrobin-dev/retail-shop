<?php

namespace App\Livewire\Settings;

use App\Models\Branch;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class BranchManager extends Component
{
    public ?int $editingId = null;

    public bool $showForm = false;

    public string $code = '';

    public string $name = '';

    public string $name_bn = '';

    public string $address = '';

    public string $address_bn = '';

    public string $phone = '';

    public bool $is_active = true;

    public function mount(): void
    {
        $this->authorize('branches.manage');
    }

    public function create(): void
    {
        $this->reset('editingId', 'code', 'name', 'name_bn', 'address', 'address_bn', 'phone');
        $this->is_active = true;
        $this->code = 'B'.(Branch::query()->count() + 1);
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $branch = Branch::query()->findOrFail($id);
        $this->editingId = $branch->id;
        $this->code = $branch->code;
        $this->name = $branch->name;
        $this->name_bn = (string) $branch->name_bn;
        $this->address = (string) $branch->address;
        $this->address_bn = (string) $branch->address_bn;
        $this->phone = (string) $branch->phone;
        $this->is_active = $branch->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('branches.manage');

        $validated = $this->validate([
            'code' => ['required', 'alpha_num', 'max:10', Rule::unique('branches', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'address_bn' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['boolean'],
        ]);

        if ($this->editingId && ! $validated['is_active'] && Branch::query()->where('is_active', true)->whereKeyNot($this->editingId)->doesntExist()) {
            $this->addError('is_active', __('At least one branch must stay active.'));

            return;
        }

        $validated['code'] = strtoupper($validated['code']);
        Branch::query()->updateOrCreate(['id' => $this->editingId], array_map(fn ($value) => $value === '' ? null : $value, $validated));

        session()->flash('success', __('Branch saved.'));
        $this->reset('showForm', 'editingId');
    }

    public function render(): View
    {
        return view('livewire.settings.branch-manager', [
            'branches' => Branch::query()->withCount('users')->orderBy('id')->get(),
        ])->title(__('Branches'));
    }
}
