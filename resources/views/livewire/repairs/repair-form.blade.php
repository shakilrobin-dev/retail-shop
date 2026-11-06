<form wire:submit="save" class="mx-auto max-w-5xl space-y-4">
    <div class="card grid gap-3 p-5 md:grid-cols-3">
        <h2 class="font-semibold md:col-span-3">{{ __('Customer') }}</h2>
        <div>
            <label class="form-label">{{ __('Phone') }} *</label>
            <input wire:model.live.debounce.400ms="phone" type="text" class="form-input" autofocus>
            @error('phone') <p class="form-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="form-label">{{ __('Name') }} *</label>
            <input wire:model="customerName" type="text" class="form-input">
            @error('customerName') <p class="form-error">{{ $message }}</p> @enderror
        </div>
        <div class="flex items-end text-sm text-slate-500">
            @if ($knownCustomer)
                <span class="badge bg-emerald-50 text-emerald-700">{{ __('Old customer') }} · {{ __(':count repairs', ['count' => shop_digits($knownCustomer->repair_jobs_count)]) }}</span>
            @endif
        </div>
    </div>

    <div class="card grid gap-3 p-5 md:grid-cols-4">
        <h2 class="font-semibold md:col-span-4">{{ __('Device') }}</h2>
        <div>
            <label class="form-label">{{ __('Type') }}</label>
            <select wire:model="device_type" class="form-input">
                @foreach (\App\Livewire\Repairs\RepairForm::DEVICE_TYPES as $type)
                    <option value="{{ $type }}">{{ __('repair_devices.'.$type) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label">{{ __('Brand') }}</label>
            <input wire:model="device_brand" type="text" class="form-input" list="brand-suggestions">
            <datalist id="brand-suggestions">
                @foreach (['Samsung', 'Xiaomi', 'Redmi', 'Realme', 'Oppo', 'Vivo', 'Infinix', 'Tecno', 'iPhone', 'OnePlus', 'Nokia', 'Symphony', 'Walton', 'Huawei', 'Honor'] as $suggestion)
                    <option value="{{ $suggestion }}">
                @endforeach
            </datalist>
        </div>
        <div>
            <label class="form-label">{{ __('Model') }}</label>
            <input wire:model="device_model" type="text" class="form-input">
        </div>
        <div>
            <label class="form-label">{{ __('Color') }}</label>
            <input wire:model="device_color" type="text" class="form-input">
        </div>
        <div>
            <label class="form-label">{{ __('IMEI / serial') }}</label>
            <input wire:model="imei_serial" type="text" class="form-input font-mono">
        </div>
        <div>
            <label class="form-label">{{ __('Password / pattern') }}</label>
            <input wire:model="device_password" type="text" class="form-input" autocomplete="off">
        </div>
        <div class="md:col-span-2">
            <label class="form-label">{{ __('Accessories received') }}</label>
            <div class="flex flex-wrap gap-3 pt-1 text-sm">
                @foreach (\App\Livewire\Repairs\RepairForm::ACCESSORIES as $accessory)
                    <label class="flex items-center gap-1"><input wire:model="accessories" type="checkbox" value="{{ $accessory }}" class="rounded border-slate-300"> {{ __('repair_accessories.'.$accessory) }}</label>
                @endforeach
            </div>
        </div>
        <div class="md:col-span-2">
            <label class="form-label">{{ __('Problem (what customer says)') }} *</label>
            <textarea wire:model="problem" rows="3" class="form-input"></textarea>
            @error('problem') <p class="form-error">{{ $message }}</p> @enderror
        </div>
        <div class="md:col-span-2">
            <label class="form-label">{{ __('Device condition (scratches, broken parts...)') }}</label>
            <textarea wire:model="condition_notes" rows="3" class="form-input"></textarea>
        </div>
    </div>

    <div class="card grid gap-3 p-5 md:grid-cols-4">
        <div>
            <label class="form-label">{{ __('Estimated cost') }}</label>
            <input wire:model="estimated_cost" type="number" step="0.01" min="0" class="form-input">
        </div>
        <div>
            <label class="form-label">{{ __('Delivery date') }}</label>
            <input wire:model="estimated_delivery_at" type="datetime-local" class="form-input">
        </div>
        @if ($techniciansEnabled)
            <div>
                <label class="form-label">{{ __('Technician') }}</label>
                <select wire:model="technician_id" class="form-input">
                    <option value="">{{ __('Not assigned') }}</option>
                    @foreach ($technicians as $technician)
                        <option value="{{ $technician->id }}">{{ $technician->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div>
            <label class="form-label">{{ __('Advance payment') }}</label>
            <div class="flex gap-1">
                <input wire:model="advance" type="number" step="0.01" min="0" class="form-input">
                <select wire:model="advance_method_id" class="form-input w-28">
                    @foreach ($paymentMethods as $method)
                        <option value="{{ $method->id }}">{{ $method->localName() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="flex justify-end gap-2">
        <a href="{{ route('repairs.index') }}" class="btn-secondary" wire:navigate>{{ __('Cancel') }}</a>
        <button type="submit" class="btn-primary" wire:loading.attr="disabled">{{ __('Save & print job slip') }}</button>
    </div>
</form>
