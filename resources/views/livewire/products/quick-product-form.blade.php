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
            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-card-line px-3 py-2 text-sm">
                <input wire:model.live="is_measured" type="checkbox" class="rounded border-layer-line">
                <span>{{ __('Sell by measurement (sqft, rft, kg...)') }} <span class="text-xs text-muted-foreground-1">— {{ __('e.g. tiles') }}</span></span>
            </label>
            @if ($is_measured)
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label for="qp-measure" class="form-label">{{ __('Measurement') }} <span class="text-red-500">*</span></label>
                        <input wire:model.live.debounce.400ms="measure_unit" id="qp-measure" type="text" list="qp-measure-units" class="form-input" placeholder="sqft">
                        <datalist id="qp-measure-units"><option value="sqft"><option value="sqm"><option value="rft"><option value="kg"></datalist>
                        @error('measure_unit') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="qp-per" class="form-label">{{ __(':measure in 1 piece', ['measure' => $measure_unit ?: 'sqft']) }} <span class="text-red-500">*</span></label>
                        <input wire:model.live.debounce.400ms="measure_per_unit" id="qp-per" type="number" step="any" min="0" class="form-input text-right" placeholder="1.77">
                                    @if (in_array(strtolower(trim($measure_unit)), ['sqft', 'sft', 'sq ft'], true))
                                        <div class="mt-1.5 flex items-center gap-1 text-xs text-muted-foreground-1" x-data="{ w: '', h: '', fill() { if (this.w > 0 && this.h > 0) { $wire.set('measure_per_unit', (this.w * this.h / 144).toFixed(6)) } } }">
                                            {{ __('Tile size') }}
                                            <input x-model="w" x-on:input.debounce.400ms="fill()" type="number" step="any" min="0" placeholder="8" class="form-input h-7 w-14 px-1 py-0 text-center text-xs" aria-label="{{ __('Width (inch)') }}">
                                            ×
                                            <input x-model="h" x-on:input.debounce.400ms="fill()" type="number" step="any" min="0" placeholder="12" class="form-input h-7 w-14 px-1 py-0 text-center text-xs" aria-label="{{ __('Height (inch)') }}">
                                            {{ __('inch') }}
                                        </div>
                                    @endif
                        @error('measure_per_unit') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="qp-rate" class="form-label">{{ __('Selling rate per :measure', ['measure' => $measure_unit ?: 'sqft']) }} <span class="text-red-500">*</span></label>
                        <input wire:model.live.debounce.400ms="measure_price" id="qp-rate" type="number" step="0.01" min="0" class="form-input text-right">
                        @error('measure_price') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            @endif
            @php $priceFields = ($canViewCost ? 1 : 0) + 1 + ($is_measured ? 0 : 1); @endphp
            <div @class(['grid gap-3', 'sm:grid-cols-3' => $priceFields === 3, 'sm:grid-cols-2' => $priceFields === 2])>
                @if ($canViewCost)
                    <div>
                        <label for="qp-cost" class="form-label">{{ $is_measured ? __('Buying price per piece') : __('Buying price') }}</label>
                        <input wire:model="cost_price" id="qp-cost" type="number" step="0.01" min="0" class="form-input text-right">
                        @error('cost_price') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                @endif
                <div>
                    <label for="qp-wholesale" class="form-label">{{ __('Wholesale price') }}</label>
                    <input wire:model="wholesale_price" id="qp-wholesale" type="number" step="0.01" min="0" placeholder="{{ __('Optional') }}" class="form-input text-right">
                    @error('wholesale_price') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                @unless ($is_measured)
                    <div>
                        <label for="qp-price" class="form-label">{{ __('Retail price') }} <span class="text-red-500">*</span></label>
                        <input wire:model="selling_price" id="qp-price" type="number" step="0.01" min="0" class="form-input text-right font-medium">
                        @error('selling_price') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                @endunless
            </div>
            <div>
                <label class="form-label">{{ __('Pack / carton (optional)') }}</label>
                <div @class(['grid gap-2', 'grid-cols-3' => ! $is_measured, 'grid-cols-2' => $is_measured])>
                    <input wire:model="pack_name" type="text" placeholder="{{ __('Carton') }}" class="form-input">
                    <input wire:model.live.debounce.400ms="pack_quantity" type="number" step="1" min="2" placeholder="{{ __('Pieces inside') }}" class="form-input text-right">
                    @unless ($is_measured)
                        <input wire:model="pack_price" type="number" step="0.01" min="0" placeholder="{{ __('Pack price') }}" class="form-input text-right">
                    @endunless
                </div>
                @error('pack_name') <p class="form-error">{{ $message }}</p> @enderror
                @error('pack_quantity') <p class="form-error">{{ $message }}</p> @enderror
                @error('pack_price') <p class="form-error">{{ $message }}</p> @enderror
                @if ($is_measured && is_numeric($measure_per_unit) && is_numeric($measure_price) && (float) $measure_per_unit > 0)
                    <p class="form-hint">
                        1 {{ __('piece') }} = {{ shop_digits((float) $measure_per_unit + 0) }} {{ $measure_unit }} · {{ money((float) $measure_price * (float) $measure_per_unit) }}
                        @if (is_numeric($pack_quantity) && (float) $pack_quantity > 1)
                            · 1 {{ $pack_name ?: __('Carton') }} = {{ shop_digits(round((float) $measure_per_unit * (float) $pack_quantity, 3) + 0) }} {{ $measure_unit }} · {{ money((float) $measure_price * (float) $measure_per_unit * (float) $pack_quantity) }}
                        @endif
                    </p>
                @endif
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
