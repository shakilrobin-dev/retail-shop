<?php

namespace App\Livewire\Products;

use App\Models\Barcode;
use App\Models\Unit;
use App\Models\User;
use App\Services\CategoryTree;
use App\Services\CurrentBranch;
use App\Services\ProductService;
use App\Services\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Pop-up to add a product without leaving the POS / purchase / stock screen.
 *
 * Open it with: $dispatch('open-quick-product', { term: 'searched text', withStock: true }).
 * After saving it dispatches "quick-product-created" with the new base unit id,
 * so the screen that opened it can put the product on its list.
 */
class QuickProductForm extends Component
{
    public bool $open = false;

    /** Show the "opening stock" field (not needed on a purchase, which adds stock itself). */
    public bool $withStock = true;

    public string $name = '';

    public string $name_bn = '';

    public ?int $category_id = null;

    public ?int $unit_id = null;

    public string $barcode = '';

    public string $cost_price = '';

    public string $selling_price = '';

    public string $wholesale_price = '';

    public string $opening_stock = '0';

    /** Sold by measurement, e.g. tiles by sqft. */
    public bool $is_measured = false;

    public string $measure_unit = 'sqft';

    public string $measure_per_unit = '';

    /** Selling rate per measurement (replaces the retail price). */
    public string $measure_price = '';

    /** Optional pack, e.g. Carton of 10 pieces. */
    public string $pack_name = '';

    public string $pack_quantity = '';

    public string $pack_price = '';

    #[On('open-quick-product')]
    public function openForm(string $term = '', bool $withStock = true): void
    {
        $this->authorize('products.manage');

        $this->resetValidation();
        $this->reset('name', 'name_bn', 'barcode', 'cost_price', 'selling_price', 'wholesale_price', 'opening_stock', 'is_measured', 'measure_unit', 'measure_per_unit', 'measure_price', 'pack_name', 'pack_quantity', 'pack_price');
        $this->unit_id ??= Unit::query()->orderBy('id')->value('id');
        $this->withStock = $withStock;

        $term = trim($term);

        if ($this->looksLikeBarcode($term)) {
            $this->barcode = $term;
        } else {
            $this->name = $term;
        }

        $this->open = true;
    }

    /**
     * A scanned code has no spaces and contains digits (e.g. "8901234567890" or "PIN-001").
     */
    private function looksLikeBarcode(string $term): bool
    {
        return $term !== '' && ! str_contains($term, ' ') && preg_match('/\d/', $term) === 1 && preg_match('/^[\x21-\x7E]+$/', $term) === 1 && mb_strlen($term) >= 4;
    }

    public function save(ProductService $products, CurrentBranch $currentBranch): void
    {
        $this->authorize('products.manage');

        /** @var User $user */
        $user = Auth::user();
        $canViewCost = $user->can('products.view-cost');

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'barcode' => ['nullable', 'string', 'max:64', 'regex:/^[\x20-\x7E]+$/'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => [$this->is_measured ? 'nullable' : 'required', 'numeric', 'min:0'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'opening_stock' => ['nullable', 'numeric', 'min:0'],
            'measure_unit' => ['exclude_unless:is_measured,true', 'required', 'string', 'max:20'],
            'measure_per_unit' => ['exclude_unless:is_measured,true', 'required', 'numeric', 'gt:0'],
            'measure_price' => ['exclude_unless:is_measured,true', 'required', 'numeric', 'min:0'],
            'pack_name' => ['nullable', 'string', 'max:50'],
            'pack_quantity' => ['nullable', 'numeric', 'gt:1'],
            'pack_price' => [$this->pack_quantity !== '' && ! $this->is_measured ? 'required' : 'nullable', 'numeric', 'min:0'],
        ], [], [
            'measure_unit' => __('measurement'),
            'measure_per_unit' => __('measurement per piece'),
            'measure_price' => __('rate per measurement'),
            'pack_name' => __('pack name'),
            'pack_quantity' => __('pieces in pack'),
            'pack_price' => __('pack price'),
            'category_id' => __('category'),
            'unit_id' => __('unit'),
            'cost_price' => __('buying price'),
            'selling_price' => __('retail price'),
            'wholesale_price' => __('wholesale price'),
            'opening_stock' => __('opening stock'),
        ]);

        $code = trim($this->barcode);
        $existing = $code !== '' ? Barcode::query()->with('product')->where('code', $code)->first() : null;

        if ($existing) {
            throw ValidationException::withMessages(['barcode' => __('Barcode already used by ":product".', ['product' => $existing->product?->name])]);
        }

        $unit = Unit::query()->findOrFail($this->unit_id);
        $piecePrice = $this->is_measured
            ? round((float) $this->measure_price * (float) $this->measure_per_unit, 2)
            : (float) $this->selling_price;
        $units = [[
            'name' => $unit->name,
            'name_bn' => $unit->name_bn,
            'quantity' => 1,
            'selling_price' => $piecePrice,
            'wholesale_price' => $this->wholesale_price,
            'is_base' => true,
            'barcodes' => $code !== '' ? [['code' => $code]] : [],
        ]];

        if ($this->pack_quantity !== '') {
            $units[] = [
                'name' => trim($this->pack_name) ?: 'Carton',
                'quantity' => (float) $this->pack_quantity,
                'selling_price' => $this->is_measured ? round($piecePrice * (float) $this->pack_quantity, 2) : (float) $this->pack_price,
                'is_base' => false,
                'barcodes' => [],
            ];
        }

        $product = $products->save(null, [
            'name' => trim($this->name),
            'name_bn' => $this->name_bn ?: null,
            'category_id' => $this->category_id,
            'unit_id' => $unit->id,
            'measure_unit' => $this->is_measured ? trim($this->measure_unit) : null,
            'measure_per_unit' => $this->is_measured ? $this->measure_per_unit : null,
            'measure_price' => $this->is_measured ? $this->measure_price : null,
            'cost_price' => $canViewCost && $this->cost_price !== '' ? $this->cost_price : 0,
            'vat_rate' => app(Settings::class)->get('vat_default_rate') ?? 0,
            'reorder_level' => 0,
            'is_active' => true,
        ], $units, $currentBranch->get(), $this->withStock ? (float) $this->opening_stock : 0);

        $this->open = false;
        $this->dispatch('toast', message: __('Product created.'));
        $this->dispatch('quick-product-created', productUnitId: $product->units->firstWhere('is_base', true)->id);
    }

    public function render(): View
    {
        /** @var User|null $user */
        $user = Auth::user();

        return view('livewire.products.quick-product-form', [
            'categoryOptions' => $this->open ? app(CategoryTree::class)->flat(onlyActive: true) : collect(),
            'unitOptions' => $this->open ? Unit::query()->orderBy('id')->get() : collect(),
            'canViewCost' => (bool) $user?->can('products.view-cost'),
        ]);
    }
}
