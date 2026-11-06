<?php

namespace App\Livewire\Settings;

use App\Enums\PaymentMethodType;
use App\Models\PaymentMethod;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PaymentMethodManager extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $name_bn = '';

    public string $type = 'mobile_banking';

    public function mount(): void
    {
        $this->authorize('settings.manage');
    }

    public function edit(int $id): void
    {
        $method = PaymentMethod::query()->findOrFail($id);
        $this->editingId = $method->id;
        $this->name = $method->name;
        $this->name_bn = (string) $method->name_bn;
        $this->type = $method->type->value;
    }

    public function save(): void
    {
        $this->authorize('settings.manage');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:50'],
            'name_bn' => ['nullable', 'string', 'max:50'],
            'type' => ['required', Rule::enum(PaymentMethodType::class)],
        ]);

        PaymentMethod::query()->updateOrCreate(['id' => $this->editingId], [
            'name' => $validated['name'],
            'name_bn' => $validated['name_bn'] ?: null,
            'type' => $validated['type'],
            'sort_order' => $this->editingId ? PaymentMethod::query()->find($this->editingId)->sort_order : (int) PaymentMethod::query()->max('sort_order') + 1,
        ]);

        $this->reset('editingId', 'name', 'name_bn');
        session()->flash('success', __('Payment method saved.'));
    }

    public function toggle(int $id): void
    {
        $this->authorize('settings.manage');
        $method = PaymentMethod::query()->findOrFail($id);

        if ($method->is_active && PaymentMethod::query()->where('is_active', true)->where('type', PaymentMethodType::Cash)->whereKeyNot($id)->doesntExist() && $method->type === PaymentMethodType::Cash) {
            session()->flash('error', __('Keep at least one cash payment method active.'));

            return;
        }

        $method->update(['is_active' => ! $method->is_active]);
    }

    public function move(int $id, int $direction): void
    {
        $this->authorize('settings.manage');
        $methods = PaymentMethod::query()->orderBy('sort_order')->orderBy('id')->get()->values();
        $index = $methods->search(fn (PaymentMethod $method): bool => $method->id === $id);
        $swap = $index + $direction;

        if ($index === false || $swap < 0 || $swap >= $methods->count()) {
            return;
        }

        foreach ($methods as $position => $method) {
            $order = match ($position) {
                $index => $swap,
                $swap => $index,
                default => $position,
            };
            $method->update(['sort_order' => $order]);
        }
    }

    public function render(): View
    {
        return view('livewire.settings.payment-method-manager', [
            'methods' => PaymentMethod::query()->orderBy('sort_order')->orderBy('id')->get(),
        ])->title(__('Payment methods'));
    }
}
