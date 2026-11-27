<div>
    <x-modal wire:model="open" :title="__('Add new product')" :subtitle="__('Quick add. You can fill in the other details later from Products.')">
        <form wire:submit="save" id="quick-product-form" class="space-y-3">
            <div>
                <label for="qp-name" class="form-label">{{ __('Product name') }} <span class="text-red-500">*</span></label>
                <input wire:model="name" id="qp-name" type="text" class="form-input" x-init="$watch('open', value => value && setTimeout(() => $el.focus(), 60))">
                @error('name') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="qp-name-bn" class="form-label">{{ __('Name (Bangla)') }}</label>
                <input wire:model="name_bn" id="qp-name-bn" type="text" class="form-input">
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="qp-category" class="form-label">{{ __('Category') }} <span class="text-red-500">*</span></label>
                    <x-category-select wire:model="category_id" id="qp-category" :options="$categoryOptions" :placeholder="__('Choose...')" />
                    @error('category_id') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="qp-unit" class="form-label">{{ __('Unit') }} <span class="text-red-500">*</span></label>
                    <select wire:model="unit_id" id="qp-unit" class="form-input">
                        @foreach ($unitOptions as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->localName() }}</option>
                        @endforeach
                    </select>
                    @error('unit_id') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label for="qp-barcode" class="form-label">{{ __('Barcode') }}</label>
                <input wire:model="barcode" id="qp-barcode" type="text" wire:keydown.enter.prevent="" placeholder="{{ __('Scan or type barcode') }}" class="form-input font-mono">
                @error('barcode') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div @class(['grid gap-3', 'sm:grid-cols-3' => $canViewCost, 'sm:grid-cols-2' => ! $canViewCost])>
                @if ($canViewCost)
                    <div>
                        <label for="qp-cost" class="form-label">{{ __('Buying price') }}</label>
                        <input wire:model="cost_price" id="qp-cost" type="number" step="0.01" min="0" class="form-input text-right">
                        @error('cost_price') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                @endif
                <div>
                    <label for="qp-wholesale" class="form-label">{{ __('Wholesale price') }}</label>
                    <input wire:model="wholesale_price" id="qp-wholesale" type="number" step="0.01" min="0" placeholder="{{ __('Optional') }}" class="form-input text-right">
                    @error('wholesale_price') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="qp-price" class="form-label">{{ __('Retail price') }} <span class="text-red-500">*</span></label>
                    <input wire:model="selling_price" id="qp-price" type="number" step="0.01" min="0" class="form-input text-right font-medium">
                    @error('selling_price') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
            @if ($withStock)
                <div>
                    <label for="qp-stock" class="form-label">{{ __('Opening stock') }}</label>
                    <input wire:model="opening_stock" id="qp-stock" type="number" step="any" min="0" class="form-input w-40 text-right">
                    <p class="form-hint">{{ __('How many you have in this branch now.') }}</p>
                    @error('opening_stock') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            @endif
        </form>

        <x-slot:footer>
            <button type="button" x-on:click="open = false" class="btn-secondary">{{ __('Cancel') }}</button>
            <button type="submit" form="quick-product-form" class="btn-primary" wire:loading.attr="disabled" wire:target="save">
                <x-icon name="check" class="size-4" /> {{ __('Save & add') }}
            </button>
        </x-slot:footer>
    </x-modal>
</div>
