<?php

namespace App\Livewire\Products;

use App\Models\Barcode;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Services\BarcodeService;
use App\Services\CurrentBranch;
use App\Services\ProductService;
use App\Services\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ProductForm extends Component
{
    public ?Product $product = null;

    public string $name = '';

    public string $name_bn = '';

    public string $sku = '';

    public ?int $category_id = null;

    public ?int $brand_id = null;

    public string $newBrand = '';

    public ?int $unit_id = null;

    public string $compatible_models = '';

    public string $description = '';

    public string $cost_price = '0';

    public string $min_selling_price = '';

    public string $vat_rate = '0';

    public string $reorder_level = '0';

    public string $warranty_days = '';

    public bool $is_active = true;

    public string $opening_stock = '0';

    /**
     * @var list<array{id: int|null, name: string, name_bn: string, quantity: string, selling_price: string, is_base: bool, barcodes: list<array{id: int|null, code: string, is_generated: bool}>}>
     */
    public array $units = [];

    public function mount(?Product $product = null): void
    {
        if ($product?->exists) {
            $this->product = $product->load('units.barcodes');
            $this->fill($product->only(['name', 'sku', 'category_id', 'brand_id', 'unit_id', 'is_active']));
            $this->name_bn = (string) $product->name_bn;
            $this->compatible_models = (string) $product->compatible_models;
            $this->description = (string) $product->description;
            $this->cost_price = (string) $product->cost_price;
            $this->min_selling_price = (string) $product->min_selling_price;
            $this->vat_rate = (string) ($product->vat_rate + 0);
            $this->reorder_level = (string) ($product->reorder_level + 0);
            $this->warranty_days = (string) $product->warranty_days;

            $this->units = $product->units->sortByDesc('is_base')->values()->map(fn ($unit): array => [
                'id' => $unit->id,
                'name' => $unit->name,
                'name_bn' => (string) $unit->name_bn,
                'quantity' => (string) ($unit->quantity + 0),
                'selling_price' => (string) $unit->selling_price,
                'is_base' => $unit->is_base,
                'barcodes' => $unit->barcodes->map(fn ($barcode): array => [
                    'id' => $barcode->id,
                    'code' => $barcode->code,
                    'is_generated' => $barcode->is_generated,
                ])->all() ?: [$this->emptyBarcode()],
            ])->all();

            return;
        }

        $defaultUnit = Unit::query()->orderBy('id')->first();
        $this->unit_id = $defaultUnit?->id;
        $this->vat_rate = (string) app(Settings::class)->get('vat_default_rate');
        $this->units = [[
            'id' => null,
            'name' => $defaultUnit->name ?? 'Piece',
            'name_bn' => (string) ($defaultUnit->name_bn ?? ''),
            'quantity' => '1',
            'selling_price' => '',
            'is_base' => true,
            'barcodes' => [$this->emptyBarcode()],
        ]];
    }

    public function updatedUnitId(): void
    {
        $unit = Unit::query()->find($this->unit_id);

        if ($unit && isset($this->units[0])) {
            $this->units[0]['name'] = $unit->name;
            $this->units[0]['name_bn'] = (string) $unit->name_bn;
        }
    }

    public function addPack(): void
    {
        $this->units[] = [
            'id' => null,
            'name' => 'Box',
            'name_bn' => 'বক্স',
            'quantity' => '10',
            'selling_price' => '',
            'is_base' => false,
            'barcodes' => [$this->emptyBarcode()],
        ];
    }

    public function removeUnit(int $index): void
    {
        if (! ($this->units[$index]['is_base'] ?? true)) {
            unset($this->units[$index]);
            $this->units = array_values($this->units);
        }
    }

    public function addBarcode(int $unitIndex): void
    {
        $this->units[$unitIndex]['barcodes'][] = $this->emptyBarcode();
    }

    public function removeBarcode(int $unitIndex, int $barcodeIndex): void
    {
        unset($this->units[$unitIndex]['barcodes'][$barcodeIndex]);
        $this->units[$unitIndex]['barcodes'] = array_values($this->units[$unitIndex]['barcodes']) ?: [$this->emptyBarcode()];
    }

    public function generateBarcode(int $unitIndex, int $barcodeIndex): void
    {
        $code = app(BarcodeService::class)->generate($this->allCodes());
        $this->units[$unitIndex]['barcodes'][$barcodeIndex] = ['id' => null, 'code' => $code, 'is_generated' => true];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:50', 'unique:products,sku'.($this->product ? ','.$this->product->id : '')],
            'category_id' => ['required', 'exists:categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'newBrand' => ['nullable', 'string', 'max:255'],
            'unit_id' => ['required', 'exists:units,id'],
            'compatible_models' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'min_selling_price' => ['nullable', 'numeric', 'min:0'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'reorder_level' => ['required', 'numeric', 'min:0'],
            'warranty_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'opening_stock' => ['nullable', 'numeric', 'min:0'],
            'units' => ['required', 'array', 'min:1'],
            'units.*.name' => ['required', 'string', 'max:50'],
            'units.*.name_bn' => ['nullable', 'string', 'max:50'],
            'units.*.quantity' => ['required', 'numeric', 'gt:0'],
            'units.*.selling_price' => ['required', 'numeric', 'min:0'],
            'units.*.barcodes.*.code' => ['nullable', 'string', 'max:64', 'regex:/^[\x20-\x7E]+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'units.*.name' => __('unit name'),
            'units.*.quantity' => __('pieces in pack'),
            'units.*.selling_price' => __('selling price'),
            'units.*.barcodes.*.code' => __('barcode'),
        ];
    }

    public function save(ProductService $products, CurrentBranch $currentBranch): void
    {
        $this->authorize('products.manage');
        $this->validate();
        $this->validateBarcodes();

        foreach ($this->units as $index => $unit) {
            if (! $unit['is_base'] && (float) $unit['quantity'] <= 1) {
                throw ValidationException::withMessages(["units.{$index}.quantity" => __('A pack must contain more than 1 piece.')]);
            }
        }

        if (trim($this->newBrand) !== '') {
            $this->brand_id = Brand::query()->firstOrCreate(['name' => trim($this->newBrand)])->id;
            $this->newBrand = '';
        }

        $isNew = $this->product === null;

        $product = $products->save($this->product, [
            'name' => trim($this->name),
            'name_bn' => $this->name_bn ?: null,
            'sku' => $this->sku ?: null,
            'category_id' => $this->category_id,
            'brand_id' => $this->brand_id,
            'unit_id' => $this->unit_id,
            'compatible_models' => $this->compatible_models ?: null,
            'description' => $this->description ?: null,
            'cost_price' => $this->cost_price,
            'min_selling_price' => $this->min_selling_price !== '' ? $this->min_selling_price : null,
            'vat_rate' => $this->vat_rate,
            'reorder_level' => $this->reorder_level,
            'warranty_days' => $this->warranty_days !== '' ? (int) $this->warranty_days : null,
            'is_active' => $this->is_active,
        ], $this->units, $currentBranch->get(), (float) $this->opening_stock);

        session()->flash('success', $isNew ? __('Product created.') : __('Product updated.'));

        if ($isNew) {
            session()->flash('created_product_id', $product->id);
        }

        $this->redirectRoute($isNew ? 'products.create' : 'products.index', navigate: true);
    }

    private function validateBarcodes(): void
    {
        $seen = [];

        foreach ($this->units as $unitIndex => $unit) {
            foreach ($unit['barcodes'] as $barcodeIndex => $barcode) {
                $code = trim($barcode['code']);

                if ($code === '') {
                    continue;
                }

                $key = "units.{$unitIndex}.barcodes.{$barcodeIndex}.code";

                if (in_array($code, $seen, true)) {
                    throw ValidationException::withMessages([$key => __('This barcode is entered twice.')]);
                }

                $seen[] = $code;

                $existing = Barcode::query()->with('product')->where('code', $code)->first();

                if ($existing && $existing->product_id !== $this->product?->id) {
                    throw ValidationException::withMessages([$key => __('Barcode already used by ":product".', ['product' => $existing->product?->name])]);
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    private function allCodes(): array
    {
        return collect($this->units)->flatMap(fn (array $unit) => array_column($unit['barcodes'], 'code'))->filter()->values()->all();
    }

    /**
     * @return array{id: null, code: string, is_generated: bool}
     */
    private function emptyBarcode(): array
    {
        return ['id' => null, 'code' => '', 'is_generated' => false];
    }

    public function render(): View
    {
        return view('livewire.products.product-form', [
            'categories' => Category::query()->with('parent')->where('is_active', true)->orderBy('parent_id')->orderBy('name')->get(),
            'brands' => Brand::query()->where('is_active', true)->orderBy('name')->get(),
            'unitOptions' => Unit::query()->orderBy('id')->get(),
            'vatEnabled' => app(Settings::class)->bool('vat_enabled'),
            'createdProduct' => session('created_product_id') ? Product::query()->find(session('created_product_id')) : null,
        ])->title($this->product ? __('Edit product') : __('New product'));
    }
}
