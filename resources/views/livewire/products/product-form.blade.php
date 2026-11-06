<form wire:submit="save" class="mx-auto max-w-5xl space-y-5">
    <x-flash />

    @if ($createdProduct)
        <div class="card flex flex-wrap items-center justify-between gap-3 p-4 text-sm">
            <span>{{ __('":product" saved. Add the next product, or print its labels.', ['product' => $createdProduct->name]) }}</span>
            <a href="{{ route('labels.index', ['product' => $createdProduct->id]) }}" class="btn-secondary btn-sm" wire:navigate>{{ __('Print labels') }}</a>
        </div>
    @endif

    <div class="card space-y-4 p-5">
        <h2 class="font-semibold">{{ __('Product details') }}</h2>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="form-label" for="name">{{ __('Name (English)') }} *</label>
                <input wire:model="name" id="name" type="text" class="form-input" autofocus>
                @error('name') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label" for="name_bn">{{ __('Name (Bangla)') }}</label>
                <input wire:model="name_bn" id="name_bn" type="text" class="form-input">
            </div>
            <div>
                <label class="form-label" for="category_id">{{ __('Category') }} *</label>
                <select wire:model="category_id" id="category_id" class="form-input">
                    <option value="">{{ __('Choose...') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->parent ? $category->parent->localName().' › ' : '' }}{{ $category->localName() }}</option>
                    @endforeach
                </select>
                @error('category_id') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label" for="brand_id">{{ __('Brand') }}</label>
                <div class="flex gap-2">
                    <select wire:model="brand_id" id="brand_id" class="form-input">
                        <option value="">{{ __('No brand') }}</option>
                        @foreach ($brands as $brand)
                            <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                        @endforeach
                    </select>
                    <input wire:model="newBrand" type="text" placeholder="{{ __('or new brand') }}" class="form-input">
                </div>
            </div>
            <div>
                <label class="form-label" for="compatible_models">{{ __('Model / compatible devices') }}</label>
                <input wire:model="compatible_models" id="compatible_models" type="text" class="form-input" placeholder="{{ __('e.g. Samsung A15, A25') }}">
            </div>
            <div>
                <label class="form-label" for="sku">{{ __('SKU / code') }}</label>
                <input wire:model="sku" id="sku" type="text" class="form-input" placeholder="{{ __('Leave empty to create automatically') }}">
                @error('sku') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div class="card space-y-4 p-5">
        <h2 class="font-semibold">{{ __('Price & stock') }}</h2>
        <div class="grid gap-4 md:grid-cols-4">
            <div>
                <label class="form-label" for="unit_id">{{ __('Base unit') }} *</label>
                <select wire:model.live="unit_id" id="unit_id" class="form-input">
                    @foreach ($unitOptions as $unitOption)
                        <option value="{{ $unitOption->id }}">{{ $unitOption->localName() }}</option>
                    @endforeach
                </select>
            </div>
            @can('products.view-cost')
                <div>
                    <label class="form-label" for="cost_price">{{ __('Cost price (per base unit)') }} *</label>
                    <input wire:model="cost_price" id="cost_price" type="number" step="0.01" min="0" class="form-input">
                    @error('cost_price') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            @endcan
            <div>
                <label class="form-label" for="min_selling_price">{{ __('Minimum selling price') }}</label>
                <input wire:model="min_selling_price" id="min_selling_price" type="number" step="0.01" min="0" class="form-input">
            </div>
            <div>
                <label class="form-label" for="reorder_level">{{ __('Low stock alert at') }}</label>
                <input wire:model="reorder_level" id="reorder_level" type="number" step="1" min="0" class="form-input">
            </div>
            <div>
                <label class="form-label" for="warranty_days">{{ __('Warranty (days)') }}</label>
                <input wire:model="warranty_days" id="warranty_days" type="number" min="0" class="form-input" placeholder="{{ __('No warranty') }}">
            </div>
            @if ($vatEnabled)
                <div>
                    <label class="form-label" for="vat_rate">{{ __('VAT %') }}</label>
                    <input wire:model="vat_rate" id="vat_rate" type="number" step="0.01" min="0" class="form-input">
                </div>
            @endif
            @if (! $product)
                <div>
                    <label class="form-label" for="opening_stock">{{ __('Opening stock (base units)') }}</label>
                    <input wire:model="opening_stock" id="opening_stock" type="number" step="1" min="0" class="form-input">
                    @error('opening_stock') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            @endif
            <label class="flex items-center gap-2 self-end pb-2 text-sm">
                <input wire:model="is_active" type="checkbox" class="rounded border-slate-300"> {{ __('Active (can be sold)') }}
            </label>
        </div>
    </div>

    <div class="card space-y-4 p-5">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold">{{ __('Selling units & barcodes') }}</h2>
                <p class="text-sm text-slate-500">{{ __('Scan the barcode printed on the product, or press "Generate" to make a new one. Add a pack (e.g. Box of 10) if you also sell by box.') }}</p>
            </div>
            <button type="button" wire:click="addPack" class="btn-secondary btn-sm">+ {{ __('Add pack / box') }}</button>
        </div>
        @error('units') <p class="form-error">{{ $message }}</p> @enderror

        @foreach ($units as $unitIndex => $unit)
            <div wire:key="unit-{{ $unitIndex }}" class="rounded-lg border border-slate-200 p-4">
                <div class="grid gap-3 md:grid-cols-12">
                    <div class="md:col-span-3">
                        <label class="form-label">{{ $unit['is_base'] ? __('Base unit name') : __('Pack name') }}</label>
                        <input wire:model="units.{{ $unitIndex }}.name" type="text" class="form-input" @disabled($unit['is_base'])>
                        @error("units.$unitIndex.name") <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-3">
                        <label class="form-label">{{ __('Name (Bangla)') }}</label>
                        <input wire:model="units.{{ $unitIndex }}.name_bn" type="text" class="form-input" @disabled($unit['is_base'])>
                    </div>
                    <div class="md:col-span-2">
                        <label class="form-label">{{ __('Pieces inside') }}</label>
                        <input wire:model="units.{{ $unitIndex }}.quantity" type="number" step="1" min="1" class="form-input" @disabled($unit['is_base'])>
                        @error("units.$unitIndex.quantity") <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-3">
                        <label class="form-label">{{ __('Selling price') }} *</label>
                        <input wire:model="units.{{ $unitIndex }}.selling_price" type="number" step="0.01" min="0" class="form-input">
                        @error("units.$unitIndex.selling_price") <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-end md:col-span-1">
                        @unless ($unit['is_base'])
                            <button type="button" wire:click="removeUnit({{ $unitIndex }})" class="btn-secondary btn-sm text-red-600" title="{{ __('Remove') }}">✕</button>
                        @endunless
                    </div>
                </div>

                <div class="mt-3 space-y-2">
                    @foreach ($unit['barcodes'] as $barcodeIndex => $barcode)
                        <div wire:key="unit-{{ $unitIndex }}-barcode-{{ $barcodeIndex }}" class="flex flex-wrap items-center gap-2">
                            <input wire:model="units.{{ $unitIndex }}.barcodes.{{ $barcodeIndex }}.code" type="text"
                                wire:keydown.enter.prevent=""
                                placeholder="{{ __('Scan or type barcode') }}" class="form-input max-w-xs font-mono">
                            <button type="button" wire:click="generateBarcode({{ $unitIndex }}, {{ $barcodeIndex }})" class="btn-secondary btn-sm">{{ __('Generate') }}</button>
                            <button type="button" wire:click="removeBarcode({{ $unitIndex }}, {{ $barcodeIndex }})" class="btn-secondary btn-sm">{{ __('Clear') }}</button>
                            @if ($loop->last)
                                <button type="button" wire:click="addBarcode({{ $unitIndex }})" class="text-xs text-indigo-600 hover:underline">+ {{ __('another barcode') }}</button>
                            @endif
                            @error("units.$unitIndex.barcodes.$barcodeIndex.code") <p class="form-error w-full">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div class="card p-5">
        <label class="form-label" for="description">{{ __('Notes') }}</label>
        <textarea wire:model="description" id="description" rows="2" class="form-input"></textarea>
    </div>

    <div class="flex justify-end gap-2">
        <a href="{{ route('products.index') }}" class="btn-secondary" wire:navigate>{{ __('Cancel') }}</a>
        <button type="submit" class="btn-primary" wire:loading.attr="disabled">{{ __('Save product') }}</button>
    </div>
</form>
