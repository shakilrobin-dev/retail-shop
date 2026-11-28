<?php

namespace App\Services\Imports;

use App\Models\Barcode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Services\ProductService;
use App\Services\Settings;
use Illuminate\Support\Facades\Auth;

/**
 * Bulk add products (with a pack/carton, tiles measurement and opening stock), or update
 * the prices and details of products that already exist (matched by SKU, barcode or name).
 */
class ProductImport extends ImportSheet
{
    /** Columns that hold numbers; thousands separators are removed before checking. */
    private const NUMBER_COLUMNS = ['cost_price', 'retail_price', 'wholesale_price', 'pack_quantity', 'pack_price', 'measure_rate', 'reorder_level', 'opening_stock'];

    public function __construct(private ProductService $products, private Settings $settings) {}

    public function key(): string
    {
        return 'products';
    }

    public function sheetName(): string
    {
        return 'Products';
    }

    public function columns(): array
    {
        return [
            'name' => ['label' => 'Product name', 'required' => true, 'hint' => 'Name of the product. If a product with this name (or SKU / barcode) already exists, its prices and details are updated instead.', 'example' => 'Floor Tiles 16x16 Matt', 'width' => 30],
            'name_bn' => ['label' => 'Name (Bangla)', 'hint' => 'Optional Bangla name.', 'example' => 'ফ্লোর টাইলস ১৬x১৬', 'width' => 24],
            'category' => ['label' => 'Category', 'required' => true, 'hint' => 'Main category. A new category is created if it does not exist yet.', 'example' => 'Tiles', 'width' => 18, 'list' => 'Categories'],
            'sub_category' => ['label' => 'Sub-category', 'hint' => 'Optional sub-category under the category. Created if it does not exist yet.', 'example' => 'Floor Tiles', 'width' => 20, 'list' => 'Sub-categories'],
            'brand' => ['label' => 'Brand', 'hint' => 'Optional. A new brand is created if it does not exist yet.', 'example' => 'RAK', 'width' => 14, 'list' => 'Brands'],
            'unit' => ['label' => 'Unit', 'required' => true, 'hint' => 'The smallest unit you sell and count stock in: pcs, kg, bag, m, ft... (see the Lists sheet). Tiles are counted in pcs.', 'example' => 'pcs', 'width' => 10, 'list' => 'Units'],
            'sku' => ['label' => 'SKU', 'hint' => 'Optional product code. Left empty, one is made automatically.', 'example' => 'TL-1616', 'width' => 14, 'text' => true],
            'barcode' => ['label' => 'Barcode', 'hint' => 'Optional barcode of one piece.', 'example' => '8901000000123', 'width' => 18, 'text' => true],
            'cost_price' => ['label' => 'Buying price', 'hint' => 'Buying price of one unit (one piece for tiles).', 'example' => '120', 'width' => 13],
            'retail_price' => ['label' => 'Retail price', 'hint' => 'Selling price of one unit. Required for new products, except tiles (their price comes from Rate per measure).', 'example' => '160', 'width' => 13],
            'wholesale_price' => ['label' => 'Wholesale price', 'hint' => 'Optional wholesale price of one unit.', 'example' => '150', 'width' => 15],
            'pack_name' => ['label' => 'Pack name', 'hint' => 'Optional bigger unit: Carton, Box, Ton, Coil...', 'example' => 'Carton', 'width' => 12],
            'pack_quantity' => ['label' => 'Pieces in pack', 'hint' => 'How many units are in one pack, e.g. 10 pieces in a carton, 1000 kg in a ton.', 'example' => '6', 'width' => 14],
            'pack_price' => ['label' => 'Pack price', 'hint' => 'Selling price of one pack. Left empty, it is retail price × pieces. Not used for tiles.', 'example' => '1500', 'width' => 12],
            'measure_unit' => ['label' => 'Measurement', 'hint' => 'Only for products sold by measurement, like tiles: sqft. Leave empty for others.', 'example' => 'sqft', 'width' => 13],
            'measure_per_unit' => ['label' => 'Measure per piece', 'hint' => 'How much one piece measures, e.g. 1.77 (sqft). For sqft you can also write the tile size in inches, like 16x16.', 'example' => '16x16', 'width' => 17, 'text' => true],
            'measure_rate' => ['label' => 'Rate per measure', 'hint' => 'Selling rate per sqft. Piece and carton prices are worked out from it.', 'example' => '85', 'width' => 16],
            'reorder_level' => ['label' => 'Alert at', 'hint' => 'Optional. Show a low-stock alert when stock falls to this quantity.', 'example' => '50', 'width' => 10],
            'opening_stock' => ['label' => 'Opening stock', 'hint' => 'Optional. Stock you have now in the branch you are logged in to, in the unit above (pieces for tiles). Only for new products — use the Stock import for existing ones.', 'example' => '300', 'width' => 14],
        ];
    }

    public function examples(): array
    {
        return [
            ['name' => 'MS Rod 16mm 500W', 'name_bn' => 'এমএস রড ১৬মিমি', 'category' => 'Rod & Steel', 'sub_category' => 'MS Rod', 'brand' => 'BSRM', 'unit' => 'kg', 'cost_price' => 89, 'retail_price' => 96, 'wholesale_price' => 94, 'pack_name' => 'Ton', 'pack_quantity' => 1000, 'pack_price' => 95000, 'reorder_level' => 500, 'opening_stock' => 2000],
            ['name' => 'Special Cement 50kg', 'category' => 'Cement', 'brand' => 'Shah Cement', 'unit' => 'bag', 'cost_price' => 490, 'retail_price' => 540, 'wholesale_price' => 525, 'reorder_level' => 50, 'opening_stock' => 200],
            ['name' => 'Floor Tiles 16x16 Matt', 'category' => 'Tiles', 'sub_category' => 'Floor Tiles', 'brand' => 'RAK', 'unit' => 'pcs', 'sku' => 'TL-1616', 'cost_price' => 120, 'pack_name' => 'Carton', 'pack_quantity' => 6, 'measure_unit' => 'sqft', 'measure_per_unit' => '16x16', 'measure_rate' => 85, 'reorder_level' => 60, 'opening_stock' => 300],
            ['name' => 'Wall Tiles 10x16 Glossy', 'category' => 'Tiles', 'sub_category' => 'Wall Tiles', 'brand' => 'DBL', 'unit' => 'pcs', 'cost_price' => 60, 'pack_name' => 'Carton', 'pack_quantity' => 9, 'measure_unit' => 'sqft', 'measure_per_unit' => '1.111111', 'measure_rate' => 70, 'opening_stock' => 450],
            ['name' => 'Socket 3 Pin', 'category' => 'Electrical', 'sub_category' => 'Switches & Sockets', 'brand' => 'Walton', 'unit' => 'pcs', 'barcode' => '8901000000123', 'cost_price' => 85, 'retail_price' => 120, 'pack_name' => 'Box', 'pack_quantity' => 10, 'pack_price' => 1100, 'opening_stock' => 40],
        ];
    }

    public function lists(): array
    {
        return [
            'Categories' => Category::query()->whereNull('parent_id')->orderBy('name')->pluck('name')->all(),
            'Sub-categories' => Category::query()->whereNotNull('parent_id')->orderBy('name')->pluck('name')->unique()->values()->all(),
            'Brands' => Brand::query()->orderBy('name')->pluck('name')->all(),
            'Units' => Unit::query()->orderBy('id')->pluck('short_name')->all(),
        ];
    }

    public function check(array $rows, Branch $branch, array $options = []): array
    {
        $lookup = new CatalogLookup;
        $canViewCost = (bool) Auth::user()?->can('products.view-cost');
        $existing = Product::query()->with('unit')->get();
        $bySku = $existing->filter(fn (Product $product): bool => filled($product->sku))->keyBy(fn (Product $product): string => self::normalize($product->sku));
        $byName = $existing->groupBy(fn (Product $product): string => self::normalize($product->name));
        $barcodes = Barcode::query()->pluck('product_id', 'code');
        $seen = [];
        $seenBarcodes = [];
        $results = [];

        foreach ($rows as ['row' => $row, 'values' => $values]) {
            $values = $this->clean($values);
            $label = $values['name'] !== '' ? $values['name'] : __('Row :row', ['row' => $row]);
            $errors = $this->validateRow($values, [
                'name' => ['required', 'string', 'max:255'],
                'name_bn' => ['nullable', 'string', 'max:255'],
                'category' => ['required', 'string', 'max:255'],
                'sub_category' => ['nullable', 'string', 'max:255'],
                'brand' => ['nullable', 'string', 'max:255'],
                'unit' => ['required', 'string'],
                'sku' => ['nullable', 'string', 'max:50'],
                'barcode' => ['nullable', 'string', 'max:64', 'regex:/^[\x20-\x7E]+$/'],
                'cost_price' => ['nullable', 'numeric', 'min:0'],
                'retail_price' => ['nullable', 'numeric', 'min:0'],
                'wholesale_price' => ['nullable', 'numeric', 'min:0'],
                'pack_name' => ['nullable', 'string', 'max:50'],
                'pack_quantity' => ['nullable', 'required_with:pack_name,pack_price', 'numeric', 'gt:1'],
                'pack_price' => ['nullable', 'numeric', 'min:0'],
                'measure_unit' => ['nullable', 'required_with:measure_per_unit,measure_rate', 'string', 'max:20'],
                'measure_per_unit' => ['nullable', 'required_with:measure_unit', 'numeric', 'gt:0'],
                'measure_rate' => ['nullable', 'required_with:measure_unit', 'numeric', 'min:0'],
                'reorder_level' => ['nullable', 'numeric', 'min:0'],
                'opening_stock' => ['nullable', 'numeric', 'min:0'],
            ]);

            if ($errors !== []) {
                $results[] = $this->result($row, 'error', $label, errors: $errors);

                continue;
            }

            $product = $values['sku'] !== '' ? $bySku->get(self::normalize($values['sku'])) : null;

            if (! $product && $values['barcode'] !== '' && $barcodes->has($values['barcode'])) {
                $product = $existing->firstWhere('id', $barcodes->get($values['barcode']));
            }

            $sameName = $byName->get(self::normalize($values['name']), collect());

            if (! $product && $values['sku'] === '' && $sameName->count() > 1) {
                $errors[] = __('More than one product is named ":name". Add its SKU to say which one.', ['name' => $values['name']]);
            } elseif (! $product && $values['sku'] === '') {
                $product = $sameName->first();
            } elseif (! $product && $sameName->isNotEmpty()) {
                $errors[] = __('A product named ":name" already exists with SKU :sku. Use that SKU to update it, or give this product another name.', ['name' => $values['name'], 'sku' => $sameName->first()->sku]);
            }

            $unit = $lookup->unit($values['unit']);

            if (! $unit) {
                $errors[] = __('Unknown unit ":unit". Use one of: :units.', ['unit' => $values['unit'], 'units' => $lookup->unitNames()]);
            } elseif ($product && $product->unit_id !== $unit->id) {
                $errors[] = __('This product is counted in :unit. Change the unit from the product page, not by import.', ['unit' => $product->unit?->short_name]);
            }

            if (! $product && $values['retail_price'] === '' && $values['measure_unit'] === '') {
                $errors[] = __('Retail price is required for a new product.');
            }

            if ($values['cost_price'] !== '' && ! $canViewCost) {
                $errors[] = __('You are not allowed to set buying prices. Leave the Buying price column empty.');
            }

            if ($values['barcode'] !== '' && $barcodes->has($values['barcode']) && $barcodes->get($values['barcode']) !== $product?->id) {
                $errors[] = __('Barcode :code is already used by another product.', ['code' => $values['barcode']]);
            }

            $key = $product ? 'id:'.$product->id : ($values['sku'] !== '' ? 'sku:'.self::normalize($values['sku']) : 'name:'.self::normalize($values['name']));

            if (isset($seen[$key])) {
                $errors[] = __('Same product as row :row.', ['row' => $seen[$key]]);
            }

            if ($values['barcode'] !== '' && isset($seenBarcodes[$values['barcode']])) {
                $errors[] = __('Same barcode as row :row.', ['row' => $seenBarcodes[$values['barcode']]]);
            }

            if ($errors !== []) {
                $results[] = $this->result($row, 'error', $label, errors: $errors);

                continue;
            }

            $seen[$key] = $row;

            if ($values['barcode'] !== '') {
                $seenBarcodes[$values['barcode']] = $row;
            }

            $notes = [...$lookup->category($values['category'], $values['sub_category'], false)['new']];
            $notes[] = $lookup->brand($values['brand'], false)['new'];
            $notes[] = $this->priceNote($values);

            if ($product) {
                $notes[] = $values['opening_stock'] !== '' ? __('Opening stock is ignored for an existing product') : null;
            } elseif ((float) $values['opening_stock'] > 0) {
                $notes[] = __('Opening stock: :qty :unit', ['qty' => $values['opening_stock'] + 0, 'unit' => $unit->short_name]);
            }

            $results[] = $this->result(
                $row,
                $product ? 'update' : 'new',
                $label,
                implode(' · ', array_filter($notes)),
                data: $values + ['product_id' => $product?->id],
            );
        }

        return $results;
    }

    public function apply(array $rows, Branch $branch, array $options = []): void
    {
        $lookup = new CatalogLookup;

        foreach ($rows as ['data' => $values]) {
            $categoryId = $lookup->category($values['category'], $values['sub_category'], true)['id'];
            $brandId = $lookup->brand($values['brand'], true)['id'];
            $unit = $lookup->unit($values['unit']);

            if ($values['product_id']) {
                $this->update(Product::query()->with('units.barcodes')->findOrFail($values['product_id']), $values, $categoryId, $brandId);
            } else {
                $this->create($values, $categoryId, $brandId, $unit, $branch);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function create(array $values, int $categoryId, ?int $brandId, Unit $unit, Branch $branch): void
    {
        $measured = $values['measure_unit'] !== '';
        $piecePrice = $measured
            ? (float) $values['measure_rate'] * (float) $values['measure_per_unit']
            : (float) $values['retail_price'];

        $units = [[
            'name' => $unit->name,
            'name_bn' => $unit->name_bn,
            'quantity' => 1,
            'selling_price' => round($piecePrice, 2),
            'wholesale_price' => $values['wholesale_price'],
            'is_base' => true,
            'barcodes' => $values['barcode'] !== '' ? [['code' => $values['barcode']]] : [],
        ]];

        if ($values['pack_quantity'] !== '') {
            $units[] = $this->packUnit($values, $piecePrice, $measured);
        }

        $this->products->save(null, [
            'name' => $values['name'],
            'name_bn' => $values['name_bn'] ?: null,
            'sku' => $values['sku'] ?: null,
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'unit_id' => $unit->id,
            'measure_unit' => $measured ? $values['measure_unit'] : null,
            'measure_per_unit' => $measured ? $values['measure_per_unit'] : null,
            'measure_price' => $measured ? $values['measure_rate'] : null,
            'cost_price' => $values['cost_price'] !== '' ? $values['cost_price'] : 0,
            'vat_rate' => $this->settings->get('vat_default_rate') ?? 0,
            'reorder_level' => $values['reorder_level'] !== '' ? $values['reorder_level'] : 0,
            'is_active' => true,
        ], $units, $branch, (float) $values['opening_stock']);
    }

    /**
     * Only the columns that were filled in are changed.
     *
     * @param  array<string, mixed>  $values
     */
    private function update(Product $product, array $values, int $categoryId, ?int $brandId): void
    {
        $attributes = ['name' => $values['name'], 'category_id' => $categoryId];

        foreach (['name_bn' => 'name_bn', 'cost_price' => 'cost_price', 'reorder_level' => 'reorder_level'] as $column => $attribute) {
            if ($values[$column] !== '') {
                $attributes[$attribute] = $values[$column];
            }
        }

        if ($brandId) {
            $attributes['brand_id'] = $brandId;
        }

        if ($values['measure_unit'] !== '') {
            $attributes += ['measure_unit' => $values['measure_unit'], 'measure_per_unit' => $values['measure_per_unit'], 'measure_price' => $values['measure_rate']];
        }

        $measureUnit = $attributes['measure_unit'] ?? $product->measure_unit;
        $perPiece = (float) ($attributes['measure_per_unit'] ?? $product->measure_per_unit);
        $rate = $attributes['measure_price'] ?? $product->measure_price;
        $measured = filled($measureUnit) && $perPiece > 0;

        /** @var list<array<string, mixed>> $units */
        $units = $product->units->sortByDesc('is_base')->values()->map(fn ($unit): array => [
            'id' => $unit->id,
            'name' => $unit->name,
            'name_bn' => $unit->name_bn,
            'quantity' => (float) $unit->quantity,
            'selling_price' => (float) $unit->selling_price,
            'wholesale_price' => $unit->wholesale_price,
            'is_base' => (bool) $unit->is_base,
            'barcodes' => $unit->barcodes->map(fn ($barcode): array => ['id' => $barcode->id, 'code' => $barcode->code, 'is_generated' => $barcode->is_generated])->all(),
        ])->all();

        if (! $measured && $values['retail_price'] !== '') {
            $units[0]['selling_price'] = (float) $values['retail_price'];
        }

        if ($values['wholesale_price'] !== '') {
            $units[0]['wholesale_price'] = $values['wholesale_price'];
        }

        if ($values['barcode'] !== '' && ! collect($units)->flatMap(fn (array $unit): array => $unit['barcodes'])->contains('code', $values['barcode'])) {
            $units[0]['barcodes'][] = ['code' => $values['barcode']];
        }

        if ($values['pack_quantity'] !== '') {
            $pack = $this->packUnit($values, (float) $units[0]['selling_price'], $measured);
            $index = collect($units)->search(fn (array $unit): bool => ! $unit['is_base'] && self::normalize($unit['name']) === self::normalize($pack['name']));

            if ($index === false) {
                $units[] = $pack;
            } else {
                $units[$index]['quantity'] = $pack['quantity'];
                $units[$index]['selling_price'] = $pack['selling_price'];
            }
        }

        if ($measured && $rate !== null && $rate !== '') {
            foreach ($units as $index => $unit) {
                $units[$index]['selling_price'] = round((float) $rate * $perPiece * ($unit['is_base'] ? 1 : (float) $unit['quantity']), 2);
            }
        }

        $this->products->save($product, $attributes, $units);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array{name: string, name_bn: null, quantity: float, selling_price: float, is_base: false, barcodes: array{}}
     */
    private function packUnit(array $values, float $piecePrice, bool $measured): array
    {
        $quantity = (float) $values['pack_quantity'];
        $price = ! $measured && $values['pack_price'] !== '' ? (float) $values['pack_price'] : round($piecePrice * $quantity, 2);

        return [
            'name' => $values['pack_name'] !== '' ? $values['pack_name'] : 'Carton',
            'name_bn' => null,
            'quantity' => $quantity,
            'selling_price' => $price,
            'is_base' => false,
            'barcodes' => [],
        ];
    }

    /**
     * Trim values, drop thousands separators from numbers and turn a tile size (16x16 inch) into sqft.
     *
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    private function clean(array $values): array
    {
        foreach (self::NUMBER_COLUMNS as $column) {
            $values[$column] = str_replace([',', ' '], '', $values[$column]);
        }

        $values['measure_unit'] = trim($values['measure_unit']);
        $size = str_replace(' ', '', mb_strtolower($values['measure_per_unit']));

        if (preg_match('/^(\d+(?:\.\d+)?)(?:"|in|inch)?[x×*](\d+(?:\.\d+)?)(?:"|in|inch)?$/u', $size, $match)) {
            $values['measure_per_unit'] = in_array(mb_strtolower($values['measure_unit']), ['sqft', 'sft', 'sq ft', 'sq.ft', 'sq.ft.'], true)
                ? (string) round((float) $match[1] * (float) $match[2] / 144, 6)
                : $values['measure_per_unit'];
        } else {
            $values['measure_per_unit'] = str_replace([',', ' '], '', $values['measure_per_unit']);
        }

        return $values;
    }

    /**
     * "1 piece = 1.78 sqft (৳151.11) · 1 Carton = 10.67 sqft" for tiles.
     *
     * @param  array<string, string>  $values
     */
    private function priceNote(array $values): ?string
    {
        if ($values['measure_unit'] === '') {
            return null;
        }

        $per = (float) $values['measure_per_unit'];
        $note = __('1 piece = :measure :unit (:price)', ['measure' => round($per, 3), 'unit' => $values['measure_unit'], 'price' => money(round($per * (float) $values['measure_rate'], 2))]);

        if ($values['pack_quantity'] !== '') {
            $note .= ' · '.__('1 :pack = :measure :unit', ['pack' => $values['pack_name'] ?: 'Carton', 'measure' => round($per * (float) $values['pack_quantity'], 3), 'unit' => $values['measure_unit']]);
        }

        return $note;
    }
}
