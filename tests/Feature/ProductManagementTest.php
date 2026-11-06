<?php

namespace Tests\Feature;

use App\Livewire\Products\CatalogSetup;
use App\Livewire\Products\LabelPrint;
use App\Livewire\Products\ProductForm;
use App\Livewire\Products\ProductIndex;
use App\Models\Barcode;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Services\BarcodeService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_cannot_open_products(): void
    {
        $this->actingAsRole('cashier');

        $this->get(route('products.index'))->assertForbidden();
    }

    public function test_admin_creates_product_with_box_unit_barcodes_and_opening_stock(): void
    {
        $this->actingAsRole('admin');
        $category = Category::factory()->create();
        Unit::factory()->create();

        Livewire::test(ProductForm::class)
            ->set('name', 'Small Pin')
            ->set('category_id', $category->id)
            ->set('cost_price', '3')
            ->set('opening_stock', '35')
            ->set('units.0.selling_price', '5')
            ->set('units.0.barcodes.0.code', '111')
            ->call('addPack')
            ->set('units.1.quantity', '10')
            ->set('units.1.selling_price', '45')
            ->set('units.1.barcodes.0.code', '8901234567890')
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::query()->with('units.barcodes')->where('name', 'Small Pin')->sole();

        $this->assertMatchesRegularExpression('/^P\d{6}$/', $product->sku);
        $this->assertCount(2, $product->units);
        $box = $product->units->firstWhere('is_base', false);
        $this->assertSame('10.000', $box->quantity);
        $this->assertSame('8901234567890', $box->barcodes->first()->code);

        $branch = Branch::query()->first();
        $this->assertSame(35.0, app(StockService::class)->quantity($branch, $product));
        $this->assertSame('3 Box + 5 Piece', $product->stockBreakdown(35));
    }

    public function test_barcode_used_by_another_product_is_rejected(): void
    {
        $this->actingAsRole('admin');
        $this->makeProduct(['name' => 'Charger'], pieceBarcode: '12345');

        Livewire::test(ProductForm::class)
            ->set('name', 'Cable')
            ->set('category_id', Category::query()->first()->id)
            ->set('units.0.selling_price', '100')
            ->set('units.0.barcodes.0.code', '12345')
            ->call('save')
            ->assertHasErrors('units.0.barcodes.0.code');

        $this->assertSame(1, Product::query()->count());
    }

    public function test_pack_must_contain_more_than_one_piece(): void
    {
        $this->actingAsRole('admin');
        Unit::factory()->create();

        Livewire::test(ProductForm::class)
            ->set('name', 'Pin')
            ->set('category_id', Category::factory()->create()->id)
            ->set('units.0.selling_price', '5')
            ->call('addPack')
            ->set('units.1.quantity', '1')
            ->set('units.1.selling_price', '5')
            ->call('save')
            ->assertHasErrors('units.1.quantity');
    }

    public function test_generate_button_fills_a_valid_shop_barcode(): void
    {
        $this->actingAsRole('admin');
        Unit::factory()->create();

        $component = Livewire::test(ProductForm::class)->call('generateBarcode', 0, 0);

        $code = $component->get('units.0.barcodes.0.code');
        $this->assertMatchesRegularExpression('/^20\d{11}$/', $code);
        $this->assertTrue($component->get('units.0.barcodes.0.is_generated'));
    }

    public function test_generated_barcodes_are_unique_and_have_correct_check_digit(): void
    {
        $service = app(BarcodeService::class);

        $first = $service->generate();
        $second = $service->generate([$first]);

        $this->assertNotSame($first, $second);
        $this->assertSame('2000000000015', $first);
    }

    public function test_admin_edits_product_and_keeps_barcodes(): void
    {
        $this->actingAsRole('admin');
        $product = $this->makeProduct(['name' => 'Old name'], pieceBarcode: '555');

        Livewire::test(ProductForm::class, ['product' => $product])
            ->assertSet('units.0.barcodes.0.code', '555')
            ->set('name', 'New name')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('New name', $product->fresh()->name);
        $this->assertTrue(Barcode::query()->where('code', '555')->where('product_id', $product->id)->exists());
    }

    public function test_product_list_finds_product_by_barcode(): void
    {
        $this->actingAsRole('admin');
        $this->makeProduct(['name' => 'Type-C Cable'], pieceBarcode: '777888');
        $this->makeProduct(['name' => 'Back Cover']);

        Livewire::test(ProductIndex::class)
            ->set('search', '777888')
            ->assertSee('Type-C Cable')
            ->assertDontSee('Back Cover');
    }

    public function test_catalog_setup_adds_sub_category_and_brand(): void
    {
        $this->actingAsRole('admin');
        $parent = Category::factory()->create(['name' => 'Accessories']);

        Livewire::test(CatalogSetup::class)
            ->set('categoryName', 'Chargers')
            ->set('categoryNameBn', 'চার্জার')
            ->set('categoryParentId', $parent->id)
            ->call('saveCategory')
            ->set('brandName', 'Anker')
            ->call('saveBrand')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categories', ['name' => 'Chargers', 'parent_id' => $parent->id, 'name_bn' => 'চার্জার']);
        $this->assertDatabaseHas('brands', ['name' => 'Anker']);
    }

    public function test_labels_page_and_print_page_show_barcodes(): void
    {
        $this->actingAsRole('admin');
        $product = $this->makeProduct(['name' => 'Earphone'], piecePrice: 250, pieceBarcode: '4444');
        $barcode = $product->units->first()->barcodes->first();

        Livewire::test(LabelPrint::class, ['product' => $product->id])
            ->assertSet('rows.0.code', '4444')
            ->set('rows.0.quantity', 3);

        $this->get(route('labels.print', ['items' => $barcode->id.':3']))
            ->assertOk()
            ->assertSee('Earphone')
            ->assertSee('4444')
            ->assertSee('<svg', false);
    }
}
