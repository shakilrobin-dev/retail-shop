@php
    $unitsHelp = __('Scan the barcode printed on the product, or press "Generate" to make a new one. Add a pack (e.g. Box of 10) if you also sell by box.');
    $tabErrors = [
        'general' => $errors->hasAny(['category_id', 'sku', 'compatible_models', 'description']),
        'units' => collect($errors->keys())->contains(fn ($key) => str_starts_with($key, 'units')),
        'pricing' => $errors->hasAny(['cost_price', 'min_selling_price', 'vat_rate', 'reorder_level', 'warranty_days', 'opening_stock']),
    ];
    $firstErrorTab = collect($tabErrors)->filter()->keys()->first();
    $baseUnitName = collect($units)->firstWhere('is_base', true)['name'] ?? '';
@endphp
<form wire:submit="save" x-data="{ tab: @js($firstErrorTab ?? 'general') }" x-effect="if (@js($firstErrorTab)) tab = @js($firstErrorTab)">
    <x-flash />

    {{-- Control panel --}}
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <a href="{{ route('products.index') }}" wire:navigate class="text-sm font-medium text-primary-600 hover:underline">{{ __('Products') }}</a>
            <span class="mx-1 text-muted-foreground">/</span>
            <span class="text-sm text-foreground">{{ $product ? $product->name : __('New') }}</span>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($product)
                <x-dropdown :label="__('Action')">
                    <x-dropdown-item icon="clipboard" wire:click="duplicate">{{ __('Duplicate') }}</x-dropdown-item>
                    <x-dropdown-item icon="tag" :href="route('labels.index', ['product' => $product->id])" wire:navigate>{{ __('Print labels') }}</x-dropdown-item>
                    <x-dropdown-item icon="history" :href="route('stock.movements', ['productId' => $product->id])" wire:navigate>{{ __('Stock history') }}</x-dropdown-item>
                    <div class="my-1 border-t border-card-divider"></div>
                    <x-dropdown-item :icon="$is_active ? 'eye-off' : 'eye'" wire:click="toggleArchive" :data-confirm="$is_active ? __('Archive this product? It will be hidden from POS.') : null" :data-confirm-button="$is_active ? __('Archive') : null" :danger="$is_active">{{ $is_active ? __('Archive') : __('Restore') }}</x-dropdown-item>
                </x-dropdown>
            @endif
            <a href="{{ route('products.index') }}" class="btn-secondary" wire:navigate>{{ __('Discard') }}</a>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled"><x-icon name="check" class="size-4" /> {{ __('Save') }}</button>
        </div>
    </div>

    @if ($createdProduct)
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <span class="flex items-center gap-2"><x-icon name="check" class="size-5" /> {{ __('":product" saved. Add the next product, or print its labels.', ['product' => $createdProduct->name]) }}</span>
            <a href="{{ route('labels.index', ['product' => $createdProduct->id]) }}" class="btn-secondary btn-sm" wire:navigate><x-icon name="printer" class="size-3.5" /> {{ __('Print labels') }}</a>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ __('Please fix the highlighted fields.') }}</div>
    @endif

    <div class="grid gap-5 2xl:grid-cols-[1fr_24rem]">
        {{-- Sheet --}}
        <div class="card">
            @if ($product)
                <div class="flex flex-wrap justify-end gap-2 border-b border-card-divider px-5 py-3">
                    <x-smart-button icon="archive" :label="__('On hand')" :value="shop_digits($stats['onHand'] + 0).' '.$baseUnitName" :href="route('stock.movements', ['productId' => $product->id])" />
                    <x-smart-button icon="building" :label="__('All branches')" :value="shop_digits($stats['allBranches'] + 0)" />
                    <x-smart-button icon="trending-up" :label="__('Sold (30 days)')" :value="shop_digits($stats['sold30'] + 0)" />
                    <x-smart-button icon="truck" :label="__('Purchases')" :value="shop_digits($stats['purchases'])" />
                </div>
            @endif

            <div class="px-6 pb-2 pt-6">
                <div class="flex items-start gap-4">
                    <span class="flex size-16 shrink-0 items-center justify-center rounded-xl border border-dashed border-layer-line bg-muted text-muted-foreground"><x-icon name="cube" class="size-7" /></span>
                    <div class="min-w-0 flex-1">
                        <label for="name" class="text-xs font-medium text-muted-foreground-1">{{ __('Product name') }}</label>
                        <input wire:model="name" id="name" type="text" autofocus placeholder="{{ __('e.g. 20W Fast Charger Type-C') }}"
                            class="mt-0.5 block w-full border-0 border-b-2 border-card-line bg-transparent px-0 py-1 text-2xl font-semibold text-foreground placeholder:font-normal placeholder:text-muted-foreground focus:border-primary-500 focus:outline-none focus:ring-0">
                        @error('name') <p class="form-error">{{ $message }}</p> @enderror
                        <input wire:model="name_bn" type="text" placeholder="{{ __('Name in Bangla (optional)') }}"
                            class="mt-2 block w-full border-0 border-b border-dashed border-card-line bg-transparent px-0 py-1 text-sm text-foreground placeholder:text-muted-foreground focus:border-primary-500 focus:outline-none focus:ring-0">
                    </div>
                    <label class="flex shrink-0 cursor-pointer items-center gap-2 rounded-full border px-3 py-1 text-xs font-medium {{ $is_active ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-card-line bg-muted text-muted-foreground-1' }}">
                        <input wire:model.live="is_active" type="checkbox" class="sr-only">
                        <span class="size-2 rounded-full {{ $is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                        {{ $is_active ? __('Active') : __('Archived') }}
                    </label>
                </div>
            </div>

            {{-- Notebook tabs --}}
            <div class="mt-4 flex gap-6 border-b border-card-line px-6">
                @foreach (['general' => __('General information'), 'units' => __('Units & barcodes'), 'pricing' => __('Pricing & stock')] as $key => $label)
                    <button type="button" x-on:click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'tab-link-active' : 'tab-link'">
                        {{ $label }}
                        @if ($tabErrors[$key])<span class="size-1.5 rounded-full bg-red-500"></span>@endif
                    </button>
                @endforeach
            </div>

            <div class="p-6">
                {{-- General --}}
                <div x-show="tab === 'general'" class="grid gap-x-10 gap-y-4 lg:grid-cols-2">
                    <div class="space-y-4">
                        <x-form.field :label="__('Category')" for="category_id" required error="category_id">
                            <x-category-select wire:model="category_id" id="category_id" :options="$categoryOptions" :placeholder="__('Choose...')" />
                            <a href="{{ route('catalog.setup') }}" target="_blank" class="mt-1 inline-block text-xs text-primary-600 hover:underline">{{ __('Manage categories') }} ↗</a>
                        </x-form.field>
                        <x-form.field :label="__('Brand')" for="brand_id">
                            <div class="flex gap-2">
                                <select wire:model="brand_id" id="brand_id" class="form-input">
                                    <option value="">{{ __('No brand') }}</option>
                                    @foreach ($brands as $brand)
                                        <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                                    @endforeach
                                </select>
                                <input wire:model="newBrand" type="text" placeholder="{{ __('or new brand') }}" class="form-input">
                            </div>
                        </x-form.field>
                        <x-form.field :label="__('Model / compatible devices')" for="compatible_models">
                            <input wire:model="compatible_models" id="compatible_models" type="text" class="form-input" placeholder="{{ __('e.g. Samsung A15, A25') }}">
                        </x-form.field>
                    </div>
                    <div class="space-y-4">
                        <x-form.field :label="__('SKU / code')" for="sku" error="sku" :hint="__('Leave empty to create automatically')">
                            <input wire:model="sku" id="sku" type="text" class="form-input font-mono">
                        </x-form.field>
                        <x-form.field :label="__('Base unit')" for="unit_id" required :hint="__('Stock is counted in this unit.')">
                            <select wire:model.live="unit_id" id="unit_id" class="form-input">
                                @foreach ($unitOptions as $unitOption)
                                    <option value="{{ $unitOption->id }}">{{ $unitOption->localName() }}</option>
                                @endforeach
                            </select>
                        </x-form.field>
                        <x-form.field :label="__('Warranty (days)')" for="warranty_days" :hint="__('Printed on the receipt. Leave empty for no warranty.')">
                            <input wire:model="warranty_days" id="warranty_days" type="number" min="0" class="form-input w-40 text-right">
                        </x-form.field>
                    </div>
                    <div class="lg:col-span-2">
                        <x-form.field :label="__('Internal notes')" for="description">
                            <textarea wire:model="description" id="description" rows="3" class="form-input" placeholder="{{ __('Only staff can see this.') }}"></textarea>
                        </x-form.field>
                    </div>
                </div>

                {{-- Units & barcodes --}}
                <div x-show="tab === 'units'" x-cloak class="space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <p class="max-w-2xl text-sm text-muted-foreground-1">{{ $unitsHelp }}</p>
                        <button type="button" wire:click="addPack" class="btn-secondary btn-sm"><x-icon name="plus" class="size-3.5" /> {{ __('Add pack / box') }}</button>
                    </div>
                    @error('units') <p class="form-error">{{ $message }}</p> @enderror

                    <div class="overflow-hidden rounded-lg border border-card-line">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Unit') }}</th>
                                    <th>{{ __('Name (Bangla)') }}</th>
                                    <th class="w-28 text-right">{{ __('Pieces inside') }}</th>
                                    <th class="w-36 text-right">{{ __('Selling price') }}</th>
                                    <th>{{ __('Barcode') }}</th>
                                    <th class="w-10"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($units as $unitIndex => $unit)
                                    <tr wire:key="unit-{{ $unitIndex }}" class="hover:bg-transparent">
                                        <td class="align-top">
                                            <input wire:model="units.{{ $unitIndex }}.name" type="text" class="form-input" @disabled($unit['is_base'])>
                                            <span class="mt-1 block text-[11px] font-medium {{ $unit['is_base'] ? 'text-primary-600' : 'text-muted-foreground' }}">{{ $unit['is_base'] ? __('Base unit') : __('Pack / box') }}</span>
                                            @error("units.$unitIndex.name") <p class="form-error">{{ $message }}</p> @enderror
                                        </td>
                                        <td class="align-top"><input wire:model="units.{{ $unitIndex }}.name_bn" type="text" class="form-input" @disabled($unit['is_base'])></td>
                                        <td class="align-top">
                                            <input wire:model="units.{{ $unitIndex }}.quantity" type="number" step="1" min="1" class="form-input text-right" @disabled($unit['is_base'])>
                                            @error("units.$unitIndex.quantity") <p class="form-error">{{ $message }}</p> @enderror
                                        </td>
                                        <td class="align-top">
                                            <input wire:model="units.{{ $unitIndex }}.selling_price" type="number" step="0.01" min="0" class="form-input text-right font-medium">
                                            @error("units.$unitIndex.selling_price") <p class="form-error">{{ $message }}</p> @enderror
                                        </td>
                                        <td class="space-y-1.5 align-top">
                                            @foreach ($unit['barcodes'] as $barcodeIndex => $barcode)
                                                <div wire:key="unit-{{ $unitIndex }}-barcode-{{ $barcodeIndex }}">
                                                    <div class="flex items-center gap-1">
                                                        <input wire:model="units.{{ $unitIndex }}.barcodes.{{ $barcodeIndex }}.code" type="text" wire:keydown.enter.prevent=""
                                                            placeholder="{{ __('Scan or type barcode') }}" class="form-input min-w-40 font-mono">
                                                        <button type="button" wire:click="generateBarcode({{ $unitIndex }}, {{ $barcodeIndex }})" class="btn-secondary btn-sm" title="{{ __('Generate') }}">{{ __('Generate') }}</button>
                                                        <button type="button" wire:click="removeBarcode({{ $unitIndex }}, {{ $barcodeIndex }})" data-confirm="{{ __('Remove this barcode? Save the product to apply.') }}" class="btn-ghost btn-sm" title="{{ __('Clear') }}">✕</button>
                                                    </div>
                                                    @error("units.$unitIndex.barcodes.$barcodeIndex.code") <p class="form-error">{{ $message }}</p> @enderror
                                                </div>
                                            @endforeach
                                            <button type="button" wire:click="addBarcode({{ $unitIndex }})" class="text-xs font-medium text-primary-600 hover:underline">+ {{ __('another barcode') }}</button>
                                        </td>
                                        <td class="align-top">
                                            @unless ($unit['is_base'])
                                                <button type="button" wire:click="removeUnit({{ $unitIndex }})" data-confirm="{{ __('Remove this pack / box unit? Save the product to apply.') }}" class="btn-ghost btn-sm text-red-600" title="{{ __('Remove') }}">✕</button>
                                            @endunless
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Pricing & stock --}}
                <div x-show="tab === 'pricing'" x-cloak class="grid gap-x-10 gap-y-4 lg:grid-cols-2">
                    <div class="space-y-4">
                        <h3 class="text-xs font-medium text-muted-foreground-1">{{ __('Pricing') }}</h3>
                        @can('products.view-cost')
                            <x-form.field :label="__('Cost price')" for="cost_price" required error="cost_price" :hint="__('Per base unit. Updated automatically from purchases (average cost).')">
                                <input wire:model="cost_price" id="cost_price" type="number" step="0.01" min="0" class="form-input w-44 text-right">
                            </x-form.field>
                        @endcan
                        <x-form.field :label="__('Minimum selling price')" for="min_selling_price" :hint="__('POS will not sell below this (per base unit).')">
                            <input wire:model="min_selling_price" id="min_selling_price" type="number" step="0.01" min="0" class="form-input w-44 text-right">
                        </x-form.field>
                        @if ($vatEnabled)
                            <x-form.field :label="__('VAT %')" for="vat_rate">
                                <input wire:model="vat_rate" id="vat_rate" type="number" step="0.01" min="0" class="form-input w-28 text-right">
                            </x-form.field>
                        @endif
                        <p class="text-xs text-muted-foreground-1">{{ __('Selling prices are set per unit in "Units & barcodes".') }}</p>
                    </div>
                    <div class="space-y-4">
                        <h3 class="text-xs font-medium text-muted-foreground-1">{{ __('Inventory') }}</h3>
                        <x-form.field :label="__('Low stock alert at')" for="reorder_level" :hint="__('Shown on the dashboard when stock falls to this level.')">
                            <input wire:model="reorder_level" id="reorder_level" type="number" step="1" min="0" class="form-input w-28 text-right">
                        </x-form.field>
                        @if (! $product)
                            <x-form.field :label="__('Opening stock')" for="opening_stock" error="opening_stock" :hint="__('Stock you already have in this branch. Later stock comes from Purchases.')">
                                <input wire:model="opening_stock" id="opening_stock" type="number" step="1" min="0" class="form-input w-28 text-right">
                            </x-form.field>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Side panel --}}
        <div class="space-y-5">
            @if ($product)
                <x-section :title="__('History')">
                    <x-activity-feed :subjects="[$product, ...$product->units]" />
                </x-section>
            @else
                <x-section :title="__('Tips')">
                    <ul class="space-y-3 text-sm text-muted-foreground-2">
                        <li class="flex gap-2"><x-icon name="info" class="mt-0.5 size-4 shrink-0 text-primary-500" /> {{ __('Scan the maker barcode into the barcode box, or press Generate to make a shop barcode.') }}</li>
                        <li class="flex gap-2"><x-icon name="info" class="mt-0.5 size-4 shrink-0 text-primary-500" /> {{ __('Sell by box too? Add a pack in "Units & barcodes" with its own price and barcode.') }}</li>
                        <li class="flex gap-2"><x-icon name="info" class="mt-0.5 size-4 shrink-0 text-primary-500" /> {{ __('After saving you can print barcode labels straight away.') }}</li>
                    </ul>
                </x-section>
            @endif
        </div>
    </div>
</form>
