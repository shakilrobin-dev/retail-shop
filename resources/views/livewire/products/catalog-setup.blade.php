<div>
    <x-flash />
    <x-page-header :title="__('Categories & brands')" :subtitle="__('Organise products in a category tree, e.g. Accessories › Chargers › Fast chargers.')">
        @if ($tab === 'categories')
            <button type="button" wire:click="newCategory" class="btn-primary"><x-icon name="plus" class="size-4" /> {{ __('New main category') }}</button>
        @endif
    </x-page-header>

    <div class="mb-5 flex gap-6 border-b border-slate-200">
        @foreach (['categories' => [__('Categories'), $categoryCount], 'brands' => [__('Brands'), $brands->count()], 'units' => [__('Base units'), $units->count()]] as $key => [$label, $count])
            <button type="button" wire:click="$set('tab', '{{ $key }}')" class="{{ $tab === $key ? 'tab-link-active' : 'tab-link' }}">
                {{ $label }} <span class="rounded-full bg-slate-100 px-1.5 text-[11px] text-slate-600">{{ shop_digits($count) }}</span>
            </button>
        @endforeach
    </div>

    @if ($tab === 'categories')
        <div class="grid gap-5 xl:grid-cols-3">
            <x-section :padding="false" class="xl:col-span-2">
                <x-slot:actions>
                    <input wire:model.live.debounce.300ms="categorySearch" type="search" placeholder="{{ __('Filter categories...') }}" class="form-input w-56 py-1.5">
                </x-slot:actions>
                <x-slot:title>{{ __('Category tree') }}</x-slot:title>

                <div x-data="{ closed: $persist([]).as('category-tree-closed') }" class="divide-y divide-slate-100">
                    @forelse ($rows as $row)
                        @php
                            $category = $row['category'];
                            $ancestorIds = [];
                            $parentId = $category->parent_id;
                            $guard = 0;
                            while ($parentId && $guard++ < 10) { $ancestorIds[] = $parentId; $parentId = $rows->firstWhere('category.id', $parentId)['category']->parent_id ?? null; }
                        @endphp
                        <div wire:key="cat-{{ $category->id }}"
                            @if ($categorySearch === '') x-show="! {{ json_encode($ancestorIds) }}.some(id => closed.includes(id))" @endif
                            @class(['group flex items-center gap-2 px-4 py-2.5 hover:bg-slate-50', 'opacity-50' => ! $category->is_active])>
                            <div class="flex min-w-0 flex-1 items-center gap-1.5" style="padding-left: {{ $row['depth'] * 22 }}px">
                                @if ($row['has_children'] && $categorySearch === '')
                                    <button type="button" x-on:click="closed.includes({{ $category->id }}) ? closed = closed.filter(i => i !== {{ $category->id }}) : closed.push({{ $category->id }})"
                                        class="rounded p-0.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700" aria-label="{{ __('Expand / collapse') }}">
                                        <span x-show="! closed.includes({{ $category->id }})"><x-icon name="chevron-down" class="size-4" /></span>
                                        <span x-show="closed.includes({{ $category->id }})" x-cloak><x-icon name="chevron-right" class="size-4" /></span>
                                    </button>
                                @else
                                    <span class="w-5"></span>
                                @endif
                                <x-icon name="folder" @class(['size-4 shrink-0', 'text-brand-500' => $row['depth'] === 0, 'text-amber-500' => $row['depth'] === 1, 'text-slate-400' => $row['depth'] > 1]) />
                                <div class="min-w-0">
                                    <div @class(['truncate text-sm', 'font-semibold text-slate-900' => $row['depth'] === 0, 'text-slate-700' => $row['depth'] > 0])>
                                        {{ $categorySearch !== '' ? $row['path'] : $category->name }}
                                        @if ($category->name_bn && app()->getLocale() !== 'bn')<span class="ml-1 text-xs font-normal text-slate-400">{{ $category->name_bn }}</span>@endif
                                    </div>
                                </div>
                                @unless ($category->is_active)<x-badge>{{ __('Hidden') }}</x-badge>@endunless
                            </div>
                            <a href="{{ route('products.index', ['categoryId' => $category->id]) }}" wire:navigate class="shrink-0 text-xs text-slate-500 hover:text-brand-700" title="{{ __('Products in this category and below') }}">
                                {{ trans_choice(':count product|:count products', $totals[$category->id] ?? 0, ['count' => shop_digits($totals[$category->id] ?? 0)]) }}
                            </a>
                            <div class="flex shrink-0 items-center gap-1 opacity-60 transition group-hover:opacity-100">
                                @if ($row['depth'] < \App\Services\CategoryTree::MAX_DEPTH - 1)
                                    <button type="button" wire:click="newCategory({{ $category->id }})" class="btn-ghost btn-sm" title="{{ __('Add sub-category') }}"><x-icon name="plus" class="size-3.5" /> {{ __('Sub') }}</button>
                                @endif
                                <button type="button" wire:click="editCategory({{ $category->id }})" class="btn-ghost btn-sm" title="{{ __('Edit') }}"><x-icon name="pencil" class="size-3.5" /></button>
                                <button type="button" wire:click="toggleCategory({{ $category->id }})" class="btn-ghost btn-sm" title="{{ $category->is_active ? __('Hide') : __('Show') }}">
                                    <x-icon :name="$category->is_active ? 'eye-off' : 'eye'" class="size-3.5" />
                                </button>
                                @if (! $row['has_children'] && ! ($directCounts[$category->id] ?? 0))
                                    <button type="button" wire:click="deleteCategory({{ $category->id }})" wire:confirm="{{ __('Delete this category?') }}" class="btn-ghost btn-sm text-red-600" title="{{ __('Delete') }}"><x-icon name="close" class="size-3.5" /></button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="py-10"><x-empty icon="folder" :title="__('No categories yet')" :text="__('Create a main category like Accessories, then add sub-categories under it.')" /></div>
                    @endforelse
                </div>
            </x-section>

            <div class="space-y-5">
                @if ($showCategoryForm)
                    <x-section :title="$editingCategoryId ? __('Edit category') : ($categoryParentId ? __('New sub-category') : __('New main category'))">
                        <form wire:submit="saveCategory" class="space-y-4">
                            <div>
                                <label class="form-label">{{ __('Parent category') }}</label>
                                <x-category-select wire:model="categoryParentId" :options="$parentOptions" :exclude="$excludeFromParents" :placeholder="'— '.__('None (main category)').' —'" />
                                @error('categoryParentId') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label">{{ __('Name (English)') }} *</label>
                                <input wire:model="categoryName" type="text" class="form-input" autofocus>
                                @error('categoryName') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label">{{ __('Name (Bangla)') }}</label>
                                <input wire:model="categoryNameBn" type="text" class="form-input">
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" wire:click="$set('showCategoryForm', false)" class="btn-secondary">{{ __('Cancel') }}</button>
                                <button type="submit" class="btn-primary">{{ __('Save') }}</button>
                            </div>
                        </form>
                    </x-section>
                @endif

                <x-section :title="__('How categories work')">
                    <ul class="space-y-2 text-sm text-slate-600">
                        <li class="flex gap-2"><x-icon name="info" class="mt-0.5 size-4 shrink-0 text-brand-500" /> {{ __('Use "Sub" to add a category inside another. Up to :levels levels.', ['levels' => \App\Services\CategoryTree::MAX_DEPTH]) }}</li>
                        <li class="flex gap-2"><x-icon name="info" class="mt-0.5 size-4 shrink-0 text-brand-500" /> {{ __('Filtering by a category also shows products in all its sub-categories.') }}</li>
                        <li class="flex gap-2"><x-icon name="info" class="mt-0.5 size-4 shrink-0 text-brand-500" /> {{ __('To move a category, edit it and choose a new parent.') }}</li>
                        <li class="flex gap-2"><x-icon name="info" class="mt-0.5 size-4 shrink-0 text-brand-500" /> {{ __('Hidden categories cannot be chosen for new products.') }}</li>
                    </ul>
                </x-section>
            </div>
        </div>
    @endif

    @if ($tab === 'brands')
        <div class="grid gap-5 lg:grid-cols-3">
            <x-section :title="$editingBrandId ? __('Edit brand') : __('Add brand')">
                <form wire:submit="saveBrand" class="space-y-3">
                    <input wire:model="brandName" type="text" placeholder="{{ __('Brand name') }}" class="form-input">
                    @error('brandName') <p class="form-error">{{ $message }}</p> @enderror
                    <div class="flex justify-end gap-2">
                        @if ($editingBrandId)<button type="button" wire:click="$set('editingBrandId', null)" class="btn-secondary">{{ __('Cancel') }}</button>@endif
                        <button type="submit" class="btn-primary">{{ $editingBrandId ? __('Update') : __('Add') }}</button>
                    </div>
                </form>
            </x-section>
            <x-section :padding="false" class="lg:col-span-2">
                <table class="data-table">
                    <thead><tr><th>{{ __('Brand') }}</th><th class="text-right">{{ __('Products') }}</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($brands as $brand)
                            <tr wire:key="brand-{{ $brand->id }}" @class(['opacity-50' => ! $brand->is_active])>
                                <td class="font-medium">{{ $brand->name }}</td>
                                <td class="text-right">{{ shop_digits($brand->products_count) }}</td>
                                <td class="whitespace-nowrap text-right">
                                    <button type="button" wire:click="editBrand({{ $brand->id }})" class="btn-ghost btn-sm"><x-icon name="pencil" class="size-3.5" /></button>
                                    <button type="button" wire:click="toggleBrand({{ $brand->id }})" class="btn-ghost btn-sm"><x-icon :name="$brand->is_active ? 'eye-off' : 'eye'" class="size-3.5" /></button>
                                </td>
                            </tr>
                        @empty
                            <x-empty :colspan="3" icon="tag" :title="__('No brands yet.')" />
                        @endforelse
                    </tbody>
                </table>
            </x-section>
        </div>
    @endif

    @if ($tab === 'units')
        <div class="grid gap-5 lg:grid-cols-3">
            <x-section :title="__('Add unit')" :description="__('Base unit is how stock is counted (piece, meter...). Boxes and packs are added on each product.')">
                <form wire:submit="saveUnit" class="space-y-3">
                    <input wire:model="unitName" type="text" placeholder="{{ __('Name (English)') }}" class="form-input">
                    @error('unitName') <p class="form-error">{{ $message }}</p> @enderror
                    <input wire:model="unitNameBn" type="text" placeholder="{{ __('Name (Bangla)') }}" class="form-input">
                    <input wire:model="unitShortName" type="text" placeholder="{{ __('Short name') }}" class="form-input">
                    @error('unitShortName') <p class="form-error">{{ $message }}</p> @enderror
                    <label class="flex items-center gap-2 text-sm text-slate-600"><input wire:model="unitAllowsDecimal" type="checkbox" class="rounded border-slate-300"> {{ __('Allows decimals (meter)') }}</label>
                    <div class="flex justify-end"><button type="submit" class="btn-primary">{{ __('Add unit') }}</button></div>
                </form>
            </x-section>
            <x-section :padding="false" class="lg:col-span-2">
                <table class="data-table">
                    <thead><tr><th>{{ __('Unit') }}</th><th>{{ __('Bangla') }}</th><th>{{ __('Short name') }}</th><th class="text-right">{{ __('Products') }}</th></tr></thead>
                    <tbody>
                        @foreach ($units as $unit)
                            <tr wire:key="unit-{{ $unit->id }}">
                                <td class="font-medium">{{ $unit->name }}</td>
                                <td>{{ $unit->name_bn }}</td>
                                <td class="font-mono text-xs">{{ $unit->short_name }}</td>
                                <td class="text-right">{{ shop_digits($unit->products_count) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-section>
        </div>
    @endif
</div>
