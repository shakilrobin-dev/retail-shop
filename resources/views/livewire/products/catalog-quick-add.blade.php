<div>
    <x-modal wire:model="open" :title="$title" width="max-w-md" layer="top">
        <form wire:submit="save" id="catalog-quick-add-form" class="space-y-3">
            @if ($type === 'subcategory')
                <div>
                    <label for="cqa-parent" class="form-label">{{ __('Main category') }} <span class="text-red-500">*</span></label>
                    <select wire:model="parentId" id="cqa-parent" class="form-input">
                        <option value="">{{ __('Choose...') }}</option>
                        @foreach ($mainCategories as $mainCategory)
                            <option value="{{ $mainCategory->id }}">{{ $mainCategory->localName() }}</option>
                        @endforeach
                    </select>
                    @error('parentId') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            @endif

            <div @class(['grid gap-3', 'sm:grid-cols-2' => $type !== 'brand'])>
                <div>
                    <label for="cqa-name" class="form-label">{{ $type === 'brand' ? __('Brand name') : __('Name (English)') }} <span class="text-red-500">*</span></label>
                    <input wire:model="name" id="cqa-name" type="text" class="form-input" x-init="$watch('open', value => value && setTimeout(() => $el.focus(), 60))"
                        placeholder="{{ ['category' => __('e.g. Paint'), 'subcategory' => __('e.g. Floor Tiles'), 'brand' => __('e.g. RAK'), 'unit' => __('e.g. Bundle')][$type] }}">
                    @error('name') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                @if ($type !== 'brand')
                    <div>
                        <label for="cqa-name-bn" class="form-label">{{ __('Name (Bangla)') }}</label>
                        <input wire:model="name_bn" id="cqa-name-bn" type="text" class="form-input">
                        @error('name_bn') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                @endif
            </div>

            @if ($type === 'unit')
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="cqa-short" class="form-label">{{ __('Short name') }} <span class="text-red-500">*</span></label>
                        <input wire:model="short_name" id="cqa-short" type="text" maxlength="10" class="form-input" placeholder="{{ __('e.g. bdl') }}">
                        @error('short_name') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex cursor-pointer items-center gap-2 self-end rounded-lg border border-card-line px-3 py-2 text-sm">
                        <input wire:model="allows_decimal" type="checkbox" class="rounded border-layer-line">
                        {{ __('Allows decimal (2.5)') }}
                    </label>
                </div>
                <p class="form-hint">{{ __('Base unit is how stock is counted (piece, meter...). Boxes and packs are added on each product.') }}</p>
            @endif
        </form>

        <x-slot:footer>
            <button type="button" x-on:click="open = false" class="btn-secondary">{{ __('Cancel') }}</button>
            <button type="submit" form="catalog-quick-add-form" class="btn-primary" wire:loading.attr="disabled" wire:target="save">
                <x-icon name="check" class="size-4" /> {{ __('Save & choose') }}
            </button>
        </x-slot:footer>
    </x-modal>
</div>
