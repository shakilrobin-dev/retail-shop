<div>
    <x-flash />
    <x-page-header :title="__('Excel import')" :subtitle="__('Add categories, products and stock in bulk from an Excel file.')" />

    @php
        $typeLabels = ['categories' => __('Categories & sub-categories'), 'brands' => __('Brands'), 'units' => __('Units'), 'products' => __('Products'), 'stock' => __('Stock')];
        $typeHelp = [
            'categories' => __('Adds main categories and sub-categories. Ones that already exist are kept as they are.'),
            'brands' => __('Adds brands. Brands that already exist are skipped.'),
            'units' => __('Adds base units (how stock is counted: piece, kg, bag, ft...). Units that already exist are skipped.'),
            'products' => __('Adds new products with prices, pack/carton, tiles measurement and opening stock. If a product already exists (same SKU, barcode or name), its prices and details are updated.'),
            'stock' => __('Adds stock to products that already exist, in the branch you are logged in to. Find products by SKU, barcode or name.'),
        ];
        $statusTone = ['new' => 'green', 'update' => 'sky', 'skip' => 'slate', 'error' => 'red'];
        $statusLabel = ['new' => __('New'), 'update' => __('Update'), 'skip' => __('No change'), 'error' => __('Error')];
    @endphp

    <div class="mb-5 flex gap-6 overflow-x-auto border-b border-card-line">
        @foreach ($types as $key)
            <button type="button" wire:click="$set('type', '{{ $key }}')" class="{{ $type === $key ? 'tab-link-active' : 'tab-link' }}">{{ $typeLabels[$key] }}</button>
        @endforeach
    </div>

    @if ($done)
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
            <div class="flex items-center gap-2 font-semibold"><x-icon name="check" class="size-4" /> {{ __('Import finished.') }}</div>
            <p class="mt-1">
                {{ __(':new added, :update updated, :skip unchanged.', ['new' => shop_digits($done['new']), 'update' => shop_digits($done['update']), 'skip' => shop_digits($done['skip'])]) }}
                @if ($done['error'] > 0) {{ __(':count rows with errors were skipped.', ['count' => shop_digits($done['error'])]) }} @endif
            </p>
            <div class="mt-2 flex flex-wrap gap-3">
                @if (in_array($done['type'], ['categories', 'brands', 'units'], true))
                    <a href="{{ route('catalog.setup', ['tab' => $done['type']]) }}" wire:navigate class="font-medium underline">{{ __('See :what', ['what' => $typeLabels[$done['type']]]) }}</a>
                @elseif ($done['type'] === 'products')
                    <a href="{{ route('products.index') }}" wire:navigate class="font-medium underline">{{ __('See products') }}</a>
                @else
                    <a href="{{ route('stock.movements') }}" wire:navigate class="font-medium underline">{{ __('See stock history') }}</a>
                @endif
            </div>
        </div>
    @endif

    <div class="grid gap-5 lg:grid-cols-3">
        <x-section :title="__('1. Download the sample')" :description="$typeHelp[$type]">
            <button type="button" wire:click="downloadSample" class="btn-secondary w-full justify-center" wire:loading.attr="disabled" wire:target="downloadSample">
                <x-icon name="table" class="size-4" /> {{ __('Download sample Excel') }}
            </button>
            <p class="mt-3 text-xs text-muted-foreground-1">{{ __('The file has example rows, an Instructions sheet that explains every column, and a Lists sheet with your categories and units.') }}</p>
        </x-section>

        <x-section :title="__('2. Fill it in')">
            <ul @class(['gap-x-4 text-sm', 'columns-2' => count($columns) > 8])>
                @foreach ($columns as $column)
                    <li class="mb-1.5 flex break-inside-avoid gap-2">
                        <span @class(['font-medium text-foreground' => $column['required'] ?? false, 'text-muted-foreground-2' => ! ($column['required'] ?? false)])>{{ $column['label'] }}@if ($column['required'] ?? false)<span class="text-red-500"> *</span>@endif</span>
                    </li>
                @endforeach
            </ul>
            <p class="mt-3 text-xs text-muted-foreground-1">{{ __('Keep the first row (column names) as it is. Delete the example rows and type your own.') }}</p>
        </x-section>

        <x-section :title="__('3. Upload')">
            @if ($type === 'stock')
                <fieldset class="mb-4 space-y-2">
                    <legend class="form-label">{{ __('What do the quantities mean?') }}</legend>
                    <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-card-line px-3 py-2 text-sm">
                        <input wire:model.live="mode" type="radio" value="add" class="mt-0.5">
                        <span><span class="font-medium">{{ __('Add to current stock') }}</span><span class="block text-xs text-muted-foreground-1">{{ __('New goods or opening stock. 50 means 50 more.') }}</span></span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-card-line px-3 py-2 text-sm">
                        <input wire:model.live="mode" type="radio" value="set" class="mt-0.5">
                        <span><span class="font-medium">{{ __('Replace with counted stock') }}</span><span class="block text-xs text-muted-foreground-1">{{ __('After a stock count. 50 means stock becomes 50.') }}</span></span>
                    </label>
                </fieldset>
            @endif

            @if (in_array($type, ['stock', 'products'], true) && $branch)
                <p class="mb-3 text-xs text-muted-foreground-1">
                    {{ $type === 'stock' ? __('Stock goes to branch:') : __('Opening stock goes to branch:') }} <x-branch-badge :branch="$branch" />
                </p>
            @endif

            <input wire:model="file" type="file" accept=".xlsx,.xls,.csv" class="w-full text-sm text-muted-foreground-2 file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100">
            <div wire:loading wire:target="file" class="mt-2 text-xs text-muted-foreground-1">{{ __('Reading the file...') }}</div>
            @error('file') <p class="form-error mt-2">{{ $message }}</p> @enderror
            <p class="mt-3 text-xs text-muted-foreground-1">{{ __('Excel (.xlsx, .xls) or CSV, up to :max rows. Nothing is saved until you press Import.', ['max' => shop_digits(\App\Services\Imports\ImportWorkbook::MAX_ROWS)]) }}</p>
        </x-section>
    </div>

    @if ($preview !== [])
        <x-section :padding="false" class="mt-5 scroll-mt-20" x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'start' })">
            <x-slot:title>{{ __('Check before importing') }}</x-slot:title>
            <x-slot:actions>
                <button type="button" wire:click="clear" class="btn-secondary btn-sm">{{ __('Cancel') }}</button>
                <button type="button" wire:click="import" class="btn-primary btn-sm" wire:loading.attr="disabled" wire:target="import" @disabled($toImport === 0)>
                    <x-icon name="check" class="size-3.5" /> {{ __('Import :count rows', ['count' => shop_digits($toImport)]) }}
                </button>
            </x-slot:actions>

            @if ($counts['error'] > 0)
                <div class="mx-4 mt-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                    <x-icon name="warning" class="mt-0.5 size-4" />
                    <span>{{ __(':count rows have errors and will be skipped. Fix them in Excel and upload again, or import the other rows now.', ['count' => shop_digits($counts['error'])]) }}</span>
                </div>
            @endif

            <div class="mt-3 border-b border-card-line">
                <x-filter-tabs model="filter" :active="$filter" :tabs="[
                    'all' => [__('All'), array_sum($counts)],
                    'new' => [__('New'), $counts['new']],
                    'update' => [__('Update'), $counts['update']],
                    'skip' => [__('No change'), $counts['skip']],
                    'error' => [__('Errors'), $counts['error']],
                ]" />
            </div>

            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr>
                        <th class="w-16">{{ __('Row') }}</th>
                        <th>{{ ['categories' => __('Category'), 'brands' => __('Brand'), 'units' => __('Unit')][$type] ?? __('Product') }}</th>
                        <th class="w-28">{{ __('Status') }}</th>
                        <th>{{ __('What will happen') }}</th>
                    </tr></thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr wire:key="import-row-{{ $row['row'] }}">
                                <td class="text-muted-foreground-1 tabular-nums">{{ shop_digits($row['row']) }}</td>
                                <td class="font-medium text-foreground">{{ $row['label'] }}</td>
                                <td><x-badge :tone="$statusTone[$row['status']]">{{ $statusLabel[$row['status']] }}</x-badge></td>
                                <td class="text-sm">
                                    @if ($row['errors'] !== [])
                                        <ul class="space-y-0.5 text-red-600 dark:text-red-400">
                                            @foreach ($row['errors'] as $error)<li>{{ $error }}</li>@endforeach
                                        </ul>
                                    @else
                                        <span class="text-muted-foreground-2">{{ $row['detail'] ?: '—' }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-8 text-center text-muted-foreground-1">{{ __('No rows here.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if (count($preview) < array_sum($counts))
                <p class="px-4 py-3 text-xs text-muted-foreground-1">{{ __('Showing the first :shown rows. All :total rows will be imported.', ['shown' => shop_digits(count($preview)), 'total' => shop_digits($toImport)]) }}</p>
            @endif
        </x-section>
    @endif
</div>
