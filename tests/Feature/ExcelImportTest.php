<?php

namespace Tests\Feature;

use App\Enums\AdjustmentReason;
use App\Enums\StockMovementType;
use App\Livewire\Products\ExcelImport;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\Unit;
use App\Services\Imports\ImportWorkbook;
use App\Services\Imports\ProductImport;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ExcelImportTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create(['code' => 'B1']);
        Unit::factory()->create();
        Unit::factory()->create(['name' => 'Kg', 'name_bn' => 'কেজি', 'short_name' => 'kg', 'allows_decimal' => true]);
    }

    /**
     * An uploaded Excel file with one sheet: a header row and data rows.
     *
     * @param  list<string>  $header
     * @param  list<list<string|int|float|null>>  $rows
     */
    private function excel(string $sheetName, array $header, array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->setTitle($sheetName)->fromArray([$header, ...$rows], null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'import-test-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return UploadedFile::fake()->createWithContent('import.xlsx', (string) file_get_contents($path));
    }

    public function test_import_page_and_sample_files(): void
    {
        $this->actingAsRole('admin', $this->branch);

        $this->get(route('import.index'))->assertOk()->assertSee('Download sample Excel');

        foreach (['categories', 'products', 'stock'] as $type) {
            Livewire::test(ExcelImport::class)
                ->set('type', $type)
                ->call('downloadSample')
                ->assertFileDownloaded($type.'-import-sample.xlsx');
        }

        $sample = IOFactory::load(app(ImportWorkbook::class)->sample(app(ProductImport::class)));

        $this->assertSame(['Products', 'Instructions', 'Lists'], $sample->getSheetNames());
        $this->assertSame('Product name *', $sample->getSheet(0)->getCell('A1')->getValue());
        $this->assertSame('MS Rod 16mm 500W', $sample->getSheet(0)->getCell('A2')->getValue());
        $this->assertContains('pcs', array_column($sample->getSheetByName('Lists')->toArray(), 3));
    }

    public function test_cashier_cannot_import(): void
    {
        $this->actingAsRole('cashier', $this->branch);

        $this->get(route('import.index'))->assertForbidden();
    }

    public function test_categories_and_sub_categories_are_imported(): void
    {
        $this->actingAsRole('admin', $this->branch);
        $tiles = Category::factory()->create(['name' => 'Tiles', 'name_bn' => null, 'parent_id' => null]);
        Category::factory()->create(['name' => 'Floor Tiles', 'parent_id' => $tiles->id]);

        $file = $this->excel('Categories', ['Category *', 'Category (Bangla)', 'Sub-category', 'Sub-category (Bangla)'], [
            ['tiles', 'টাইলস', 'Floor Tiles', null],
            ['Tiles', null, 'Wall Tiles', 'ওয়াল টাইলস'],
            ['Paint', 'রং', null, null],
            ['PAINT', null, null, null],
            [null, null, 'Orphan', null],
        ]);

        Livewire::test(ExcelImport::class)
            ->set('type', 'categories')
            ->set('file', $file)
            ->assertSet('counts', ['new' => 2, 'update' => 1, 'skip' => 1, 'error' => 1])
            ->call('import')
            ->assertHasNoErrors()
            ->assertSet('done.new', 2);

        $this->assertSame('টাইলস', $tiles->fresh()->name_bn);
        $this->assertDatabaseHas('categories', ['name' => 'Wall Tiles', 'name_bn' => 'ওয়াল টাইলস', 'parent_id' => $tiles->id]);
        $this->assertSame(1, Category::query()->where('name', 'Paint')->whereNull('parent_id')->count());
        $this->assertDatabaseMissing('categories', ['name' => 'Orphan']);
    }

    public function test_products_are_added_and_existing_ones_updated(): void
    {
        $this->actingAsRole('admin', $this->branch);
        $cement = $this->makeProduct(['name' => 'Cement Bag', 'cost_price' => 450], piecePrice: 500);

        $header = ['Product name *', 'Name (Bangla)', 'Category *', 'Sub-category', 'Brand', 'Unit *', 'SKU', 'Barcode', 'Buying price', 'Retail price', 'Wholesale price', 'Pack name', 'Pieces in pack', 'Pack price', 'Measurement', 'Measure per piece', 'Rate per measure', 'Alert at', 'Opening stock'];
        $file = $this->excel('Products', $header, [
            ['MS Rod 12mm', 'এমএস রড', 'Rod & Steel', 'MS Rod', 'BSRM', 'kg', null, null, 88, 95, 93, 'Ton', 1000, '94,000', null, null, null, 100, 2500],
            ['Floor Tiles 16x16', null, 'Tiles', 'Floor Tiles', null, 'pcs', 'TL-1616', 8901000000123, 120, null, null, 'Carton', 6, null, 'sqft', '16x16', 85, null, 300],
            ['cement bag', null, 'Cement', null, null, 'pcs', null, null, 490, 540, null, null, null, null, null, null, null, null, 50],
            ['Mystery', null, 'Hardware', null, null, 'litre', null, null, null, 10, null, null, null, null, null, null, null, null, null],
            ['No Price', null, 'Hardware', null, null, 'pcs', null, null, null, null, null, null, null, null, null, null, null, null, null],
        ]);

        $component = Livewire::test(ExcelImport::class)
            ->set('type', 'products')
            ->set('file', $file)
            ->assertSet('counts', ['new' => 2, 'update' => 1, 'skip' => 0, 'error' => 2])
            ->assertSee('Unknown unit "litre"')
            ->assertSee('Retail price is required for a new product.')
            ->assertSee('Opening stock is ignored for an existing product');

        $component->call('import')->assertHasNoErrors();

        $rod = Product::query()->where('name', 'MS Rod 12mm')->with(['units', 'category.parent', 'brand'])->sole();
        $this->assertSame('MS Rod', $rod->category->name);
        $this->assertSame('Rod & Steel', $rod->category->parent->name);
        $this->assertSame('BSRM', $rod->brand->name);
        $this->assertEquals(95, $rod->units->firstWhere('is_base', true)->selling_price);
        $this->assertEquals(93, $rod->units->firstWhere('is_base', true)->wholesale_price);
        $this->assertEquals(94000, $rod->units->firstWhere('name', 'Ton')->selling_price);
        $this->assertEquals(2500, app(StockService::class)->quantity($this->branch, $rod));

        $tiles = Product::query()->where('sku', 'TL-1616')->with('units.barcodes')->sole();
        $this->assertEqualsWithDelta(1.777778, (float) $tiles->measure_per_unit, 0.000001);
        $this->assertEquals(151.11, $tiles->units->firstWhere('is_base', true)->selling_price);
        $this->assertEquals(906.67, $tiles->units->firstWhere('name', 'Carton')->selling_price);
        $this->assertSame('8901000000123', $tiles->units->firstWhere('is_base', true)->barcodes->first()->code);
        $this->assertEquals(300, app(StockService::class)->quantity($this->branch, $tiles));

        $cement->refresh()->load('units', 'category');
        $this->assertEquals(540, $cement->units->firstWhere('is_base', true)->selling_price);
        $this->assertEquals(490, $cement->cost_price);
        $this->assertSame('Cement', $cement->category->name);
        $this->assertEquals(0, app(StockService::class)->quantity($this->branch, $cement));

        $this->assertDatabaseMissing('products', ['name' => 'Mystery']);
        $this->assertDatabaseMissing('categories', ['name' => 'Hardware']);
    }

    public function test_stock_is_added_by_sqft_and_pack_then_replaced_by_a_count(): void
    {
        $this->actingAsRole('admin', $this->branch);
        $tiles = $this->makeProduct(['name' => 'Floor tiles 1.77', 'measure_unit' => 'sqft', 'measure_per_unit' => 1.77, 'measure_price' => 120], piecePrice: 212.40, boxQuantity: 10);
        $bolts = $this->makeProduct(['name' => 'Bolt', 'sku' => 'BOLT', 'cost_price' => 10], boxQuantity: 10, boxBarcode: 'BOX-1');
        app(StockService::class)->move($this->branch, $tiles, 100, StockMovementType::Opening);

        $header = ['SKU', 'Barcode', 'Product name', 'Quantity *', 'Unit', 'Buying price'];

        Livewire::test(ExcelImport::class)
            ->set('type', 'stock')
            ->set('mode', 'add')
            ->set('file', $this->excel('Stock', $header, [
                [null, null, 'floor tiles 1.77', 500, 'sqft', null],
                [null, 'BOX-1', null, 3, null, 12],
                ['NOPE', null, null, 5, null, null],
            ]))
            ->assertSet('counts', ['new' => 0, 'update' => 2, 'skip' => 0, 'error' => 1])
            ->assertSee('+283 pcs (500 sqft)')
            ->assertSee('No product with SKU NOPE.')
            ->call('import')
            ->assertHasNoErrors();

        $this->assertEquals(383, app(StockService::class)->quantity($this->branch, $tiles));
        $this->assertEquals(30, app(StockService::class)->quantity($this->branch, $bolts));
        $this->assertEquals(12, $bolts->fresh()->cost_price);
        $this->assertSame(AdjustmentReason::CountCorrection, StockAdjustment::query()->sole()->reason);

        Livewire::test(ExcelImport::class)
            ->set('type', 'stock')
            ->set('mode', 'set')
            ->set('file', $this->excel('Stock', $header, [
                [null, null, 'Floor tiles 1.77', 200, null, null],
                ['BOLT', null, null, 30, null, null],
            ]))
            ->assertSet('counts', ['new' => 0, 'update' => 1, 'skip' => 1, 'error' => 0])
            ->call('import')
            ->assertHasNoErrors();

        $this->assertEquals(200, app(StockService::class)->quantity($this->branch, $tiles));
        $this->assertEquals(30, app(StockService::class)->quantity($this->branch, $bolts));
        $this->assertSame(2, StockAdjustment::query()->count());
    }

    public function test_a_file_without_the_required_columns_is_refused(): void
    {
        $this->actingAsRole('admin', $this->branch);

        Livewire::test(ExcelImport::class)
            ->set('type', 'products')
            ->set('file', $this->excel('Products', ['Name', 'Price'], [['Rod', 10]]))
            ->assertHasErrors('file')
            ->assertSet('preview', []);
    }
}
