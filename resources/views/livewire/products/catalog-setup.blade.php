<div class="grid gap-5 xl:grid-cols-3">
    {{-- Categories --}}
    <div class="card p-5 xl:col-span-2">
        <h2 class="mb-3 font-semibold">{{ __('Categories') }}</h2>
        <form wire:submit="saveCategory" class="mb-4 grid gap-2 md:grid-cols-4">
            <input wire:model="categoryName" type="text" placeholder="{{ __('Name (English)') }}" class="form-input">
            <input wire:model="categoryNameBn" type="text" placeholder="{{ __('Name (Bangla)') }}" class="form-input">
            <select wire:model="categoryParentId" class="form-input">
                <option value="">{{ __('Main category') }}</option>
                @foreach ($parentOptions as $parent)
                    <option value="{{ $parent->id }}">{{ __('Under') }}: {{ $parent->localName() }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button type="submit" class="btn-primary flex-1">{{ $editingCategoryId ? __('Update') : __('Add') }}</button>
                @if ($editingCategoryId)
                    <button type="button" wire:click="$set('editingCategoryId', null)" class="btn-secondary">{{ __('Cancel') }}</button>
                @endif
            </div>
            @error('categoryName') <p class="form-error md:col-span-4">{{ $message }}</p> @enderror
        </form>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Bangla') }}</th><th class="text-right">{{ __('Products') }}</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($categories as $category)
                        <tr wire:key="cat-{{ $category->id }}" @class(['opacity-50' => ! $category->is_active])>
                            <td>@if ($category->parent)<span class="text-slate-400">{{ $category->parent->name }} ›</span>@endif {{ $category->name }}</td>
                            <td>{{ $category->name_bn }}</td>
                            <td class="text-right">{{ shop_digits($category->products_count) }}</td>
                            <td class="whitespace-nowrap text-right">
                                <button type="button" wire:click="editCategory({{ $category->id }})" class="btn-secondary btn-sm">{{ __('Edit') }}</button>
                                <button type="button" wire:click="toggleCategory({{ $category->id }})" class="btn-secondary btn-sm">{{ $category->is_active ? __('Hide') : __('Show') }}</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="space-y-5">
        {{-- Brands --}}
        <div class="card p-5">
            <h2 class="mb-3 font-semibold">{{ __('Brands') }}</h2>
            <form wire:submit="saveBrand" class="mb-3 flex gap-2">
                <input wire:model="brandName" type="text" placeholder="{{ __('Brand name') }}" class="form-input">
                <button type="submit" class="btn-primary">{{ $editingBrandId ? __('Update') : __('Add') }}</button>
            </form>
            @error('brandName') <p class="form-error mb-2">{{ $message }}</p> @enderror
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($brands as $brand)
                    <li wire:key="brand-{{ $brand->id }}" @class(['flex items-center justify-between py-2', 'opacity-50' => ! $brand->is_active])>
                        <span>{{ $brand->name }} <span class="text-xs text-slate-400">({{ shop_digits($brand->products_count) }})</span></span>
                        <span class="space-x-1">
                            <button type="button" wire:click="editBrand({{ $brand->id }})" class="text-xs text-indigo-600 hover:underline">{{ __('Edit') }}</button>
                            <button type="button" wire:click="toggleBrand({{ $brand->id }})" class="text-xs text-slate-500 hover:underline">{{ $brand->is_active ? __('Hide') : __('Show') }}</button>
                        </span>
                    </li>
                @empty
                    <li class="py-2 text-slate-500">{{ __('No brands yet.') }}</li>
                @endforelse
            </ul>
        </div>

        {{-- Units --}}
        <div class="card p-5">
            <h2 class="mb-3 font-semibold">{{ __('Base units') }}</h2>
            <ul class="mb-3 space-y-1 text-sm">
                @foreach ($units as $unit)
                    <li wire:key="unit-{{ $unit->id }}">{{ $unit->name }} @if ($unit->name_bn)· {{ $unit->name_bn }}@endif <span class="text-slate-400">({{ $unit->short_name }})</span></li>
                @endforeach
            </ul>
            <form wire:submit="saveUnit" class="grid grid-cols-2 gap-2">
                <input wire:model="unitName" type="text" placeholder="{{ __('Name (English)') }}" class="form-input">
                <input wire:model="unitNameBn" type="text" placeholder="{{ __('Name (Bangla)') }}" class="form-input">
                <input wire:model="unitShortName" type="text" placeholder="{{ __('Short name') }}" class="form-input">
                <label class="flex items-center gap-2 text-xs"><input wire:model="unitAllowsDecimal" type="checkbox" class="rounded border-slate-300"> {{ __('Allows decimals (meter)') }}</label>
                <button type="submit" class="btn-secondary col-span-2">{{ __('Add unit') }}</button>
                @error('unitName') <p class="form-error col-span-2">{{ $message }}</p> @enderror
                @error('unitShortName') <p class="form-error col-span-2">{{ $message }}</p> @enderror
            </form>
        </div>
    </div>
</div>
