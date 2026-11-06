@php $unitsHelp = __('Scan the barcode printed on the product, or press "Generate" to make a new one. Add a pack (e.g. Box of 10) if you also sell by box.'); @endphp
<form wire:submit="save">
    <x-flash />
    <x-page-header :title="$product ? __('Edit product') : __('New product')" :subtitle="$product ? $product->sku : __('Fill the details, add barcodes, then save.')" :back="route('products.index')" :backLabel="__('Products')">
        <a href="{{ route('products.index') }}" class="btn-secondary" wire:navigate>{{ __('Cancel') }}</a>
        <button type="submit" class="btn-primary" wire:loading.attr="disabled">{{ __('Save product') }}</button>
    </x-page-header>

    @if ($createdProduct)
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <span class="flex items-center gap-2"><x-icon name="check" class="size-5" /> {{ __('":product" saved. Add the next product, or print its labels.', ['product' => $createdProduct->name]) }}</span>
            <a href="{{ route('labels.index', ['product' => $createdProduct->id]) }}" class="btn-secondary btn-sm" wire:navigate><x-icon name="printer" class="size-3.5" /> {{ __('Print labels') }}</a>
        </div>
    @endif

    <div class="grid gap-5 xl:grid-cols-3">
        <div class="space-y-5 xl:col-span-2">
            <x-section :title="__('Product details')">
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
                        <x-category-select wire:model="category_id" id="category_id" :options="$categoryOptions" :placeholder="__('Choose...')" />
                        @error('category_id') <p class="form-error">{{ $message }}</p> @enderror
                        <p class="form-hint"><a href="{{ route('catalog.setup') }}" target="_blank" class="text-brand-600 hover:underline">{{ __('Manage categories') }} ↗</a></p>
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
                        <input wire:model="sku" id="sku" type="text" class="form-input font-mono" placeholder="{{ __('Leave empty to create automatically') }}">
                        @error('sku') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="form-label" for="description">{{ __('Notes') }}</label>
                        <textarea wire:model="description" id="description" rows="2" class="form-input"></textarea>
                    </div>
                </div>
            </x-section>

            <x-section :title="__('Selling units & barcodes')" :description="$unitsHelp">
                <x-slot:actions>
                    <button type="button" wire:click="addPack" class="btn-secondary btn-sm"><x-icon name="plus" class="size-3.5" /> {{ __('Add pack / box') }}</button>
                </x-slot:actions>
                @error('units') <p class="form-error mb-3">{{ $message }}</p> @enderror

                <div class="space-y-4">
                    @foreach ($units as $unitIndex => $unit)
                        <div wire:key="unit-{{ $unitIndex }}" class="rounded-lg border border-slate-200 bg-slate-50/50">
                            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-2">
                                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $unit['is_base'] ? __('Base unit') : __('Pack / box') }}</span>
                                @unless ($unit['is_base'])
                                    <button type="button" wire:click="removeUnit({{ $unitIndex }})" class="btn-ghost btn-sm text-red-600"><x-icon name="close" class="size-3.5" /> {{ __('Remove') }}</button>
                                @endunless
                            </div>
                            <div class="grid gap-3 p-4 md:grid-cols-4">
                                <div>
                                    <label class="form-label">{{ $unit['is_base'] ? __('Base unit name') : __('Pack name') }}</label>
                                    <input wire:model="units.{{ $unitIndex }}.name" type="text" class="form-input" @disabled($unit['is_base'])>
                                    @error("units.$unitIndex.name") <p class="form-error">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="form-label">{{ __('Name (Bangla)') }}</label>
                                    <input wire:model="units.{{ $unitIndex }}.name_bn" type="text" class="form-input" @disabled($unit['is_base'])>
                                </div>
                                <div>
                                    <label class="form-label">{{ __('Pieces inside') }}</label>
                                    <input wire:model="units.{{ $unitIndex }}.quantity" type="number" step="1" min="1" class="form-input" @disabled($unit['is_base'])>
                                    @error("units.$unitIndex.quantity") <p class="form-error">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="form-label">{{ __('Selling price') }} *</label>
                                    <input wire:model="units.{{ $unitIndex }}.selling_price" type="number" step="0.01" min="0" class="form-input text-right">
                                    @error("units.$unitIndex.selling_price") <p class="form-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="space-y-2 md:col-span-4">
                                    <label class="form-label mb-0">{{ __('Barcode') }}</label>
                                    @foreach ($unit['barcodes'] as $barcodeIndex => $barcode)
                                        <div wire:key="unit-{{ $unitIndex }}-barcode-{{ $barcodeIndex }}" class="flex flex-wrap items-center gap-2">
                                            <input wire:model="units.{{ $unitIndex }}.barcodes.{{ $barcodeIndex }}.code" type="text" wire:keydown.enter.prevent=""
                                                placeholder="{{ __('Scan or type barcode') }}" class="form-input max-w-xs font-mono">
                                            <button type="button" wire:click="generateBarcode({{ $unitIndex }}, {{ $barcodeIndex }})" class="btn-secondary btn-sm">{{ __('Generate') }}</button>
                                            <button type="button" wire:click="removeBarcode({{ $unitIndex }}, {{ $barcodeIndex }})" class="btn-ghost btn-sm">{{ __('Clear') }}</button>
                                            @if ($loop->last)
                                                <button type="button" wire:click="addBarcode({{ $unitIndex }})" class="text-xs font-medium text-brand-600 hover:underline">+ {{ __('another barcode') }}</button>
                                            @endif
                                            @error("units.$unitIndex.barcodes.$barcodeIndex.code") <p class="form-error w-full">{{ $message }}</p> @enderror
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-section>
        </div>

        <div class="space-y-5">
            <x-section :title="__('Status')">
                <label class="flex items-start gap-3 text-sm">
                    <input wire:model="is_active" type="checkbox" class="mt-0.5 rounded border-slate-300">
                    <span><span class="font-medium text-slate-800">{{ __('Active (can be sold)') }}</span>
                        <span class="block text-xs text-slate-500">{{ __('Inactive products are hidden from POS and search.') }}</span></span>
                </label>
            </x-section>

            <x-section :title="__('Price & stock')">
                <div class="space-y-4">
                    <div>
                        <label class="form-label" for="unit_id">{{ __('Base unit') }} *</label>
                        <select wire:model.live="unit_id" id="unit_id" class="form-input">
                            @foreach ($unitOptions as $unitOption)
                                <option value="{{ $unitOption->id }}">{{ $unitOption->localName() }}</option>
                            @endforeach
                        </select>
                        <p class="form-hint">{{ __('Stock is counted in this unit.') }}</p>
                    </div>
                    @can('products.view-cost')
                        <div>
                            <label class="form-label" for="cost_price">{{ __('Cost price (per base unit)') }} *</label>
                            <input wire:model="cost_price" id="cost_price" type="number" step="0.01" min="0" class="form-input text-right">
                            @error('cost_price') <p class="form-error">{{ $message }}</p> @enderror
                            <p class="form-hint">{{ __('Updated automatically from purchases (average cost).') }}</p>
                        </div>
                    @endcan
                    <div>
                        <label class="form-label" for="min_selling_price">{{ __('Minimum selling price') }}</label>
                        <input wire:model="min_selling_price" id="min_selling_price" type="number" step="0.01" min="0" class="form-input text-right">
                        <p class="form-hint">{{ __('POS will not sell below this (per base unit).') }}</p>
                    </div>
                    @if ($vatEnabled)
                        <div>
                            <label class="form-label" for="vat_rate">{{ __('VAT %') }}</label>
                            <input wire:model="vat_rate" id="vat_rate" type="number" step="0.01" min="0" class="form-input text-right">
                        </div>
                    @endif
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label" for="reorder_level">{{ __('Low stock alert at') }}</label>
                            <input wire:model="reorder_level" id="reorder_level" type="number" step="1" min="0" class="form-input text-right">
                        </div>
                        <div>
                            <label class="form-label" for="warranty_days">{{ __('Warranty (days)') }}</label>
                            <input wire:model="warranty_days" id="warranty_days" type="number" min="0" class="form-input text-right" placeholder="—">
                        </div>
                    </div>
                    @if (! $product)
                        <div class="rounded-lg border border-dashed border-slate-300 p-3">
                            <label class="form-label" for="opening_stock">{{ __('Opening stock (base units)') }}</label>
                            <input wire:model="opening_stock" id="opening_stock" type="number" step="1" min="0" class="form-input text-right">
                            @error('opening_stock') <p class="form-error">{{ $message }}</p> @enderror
                            <p class="form-hint">{{ __('Stock you already have in this branch. Later stock comes from Purchases.') }}</p>
                        </div>
                    @endif
                </div>
            </x-section>
        </div>
    </div>
</form>
