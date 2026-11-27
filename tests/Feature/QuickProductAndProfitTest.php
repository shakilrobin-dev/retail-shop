<?php

namespace Tests\Feature;

use App\Enums\PaymentMethodType;
use App\Enums\StockMovementType;
use App\Livewire\Pos\PointOfSale;
use App\Livewire\Products\ProductForm;
use App\Livewire\Products\ProductInfo;
use App\Livewire\Products\QuickProductForm;
use App\Livewire\Purchases\PurchaseForm;
use App\Models\Branch;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Unit;
use App\Services\CashShiftService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuickProductAndProfitTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create(['code' => 'B1']);
        PaymentMethod::factory()->create(['name' => 'Cash', 'type' => PaymentMethodType::Cash, 'sort_order' => 0]);
        Category::factory()->create();
        Unit::factory()->create();
    }

    public function test_search_with_no_match_offers_to_add_the_product(): void
    {
        $this->actingAsRole('manager', $this->branch);

        Livewire::test(PurchaseForm::class)
            ->set('productSearch', 'Type-C cable')
            ->assertSee(__('Add ":name" as new product', ['name' => 'Type-C cable']));
    }

    public function test_cashier_without_product_permission_is_not_offered_quick_add(): void
    {
        $this->actingAsRole('cashier', $this->branch);
        app(CashShiftService::class)->open($this->branch, 0);

        Livewire::test(PointOfSale::class)
            ->set('productSearch', 'Type-C cable')
            ->assertDontSee(__('Add ":name" as new product', ['name' => 'Type-C cable']));

        Livewire::test(QuickProductForm::class)
            ->call('openForm', 'Type-C cable')
            ->assertForbidden();
    }

    public function test_scanned_code_is_put_in_barcode_and_name_in_name(): void
    {
        $this->actingAsRole('manager', $this->branch);

        Livewire::test(QuickProductForm::class)
            ->call('openForm', '8901234567890')
            ->assertSet('barcode', '8901234567890')
            ->assertSet('name', '')
            ->call('openForm', 'Type-C cable')
            ->assertSet('name', 'Type-C cable')
            ->assertSet('barcode', '');
    }

    public function test_quick_product_is_created_with_prices_stock_and_barcode(): void
    {
        $this->actingAsRole('manager', $this->branch);

        Livewire::test(QuickProductForm::class)
            ->call('openForm', '8901234567890', true)
            ->set('name', 'Type-C cable')
            ->set('category_id', Category::query()->value('id'))
            ->set('cost_price', '80')
            ->set('wholesale_price', '110')
            ->set('selling_price', '150')
            ->set('opening_stock', '6')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('open', false)
            ->assertDispatched('quick-product-created');

        $product = Product::query()->with('units.barcodes')->where('name', 'Type-C cable')->firstOrFail();
        $unit = $product->units->first();

        $this->assertEquals(80, $product->cost_price);
        $this->assertEquals(110, $unit->wholesale_price);
        $this->assertEquals(150, $unit->selling_price);
        $this->assertSame('8901234567890', $unit->barcodes->first()->code);
        $this->assertEquals(6, app(StockService::class)->quantity($this->branch, $product));
    }

    public function test_quick_product_from_purchase_adds_no_opening_stock(): void
    {
        $this->actingAsRole('manager', $this->branch);

        Livewire::test(QuickProductForm::class)
            ->call('openForm', 'Screen guard', false)
            ->set('category_id', Category::query()->value('id'))
            ->set('selling_price', '100')
            ->set('opening_stock', '9')
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::query()->where('name', 'Screen guard')->firstOrFail();
        $this->assertEquals(0, app(StockService::class)->quantity($this->branch, $product));
    }

    public function test_quick_product_rejects_a_barcode_already_in_use(): void
    {
        $this->actingAsRole('manager', $this->branch);
        $this->makeProduct(['name' => 'Pin'], pieceBarcode: 'PIN-1');

        Livewire::test(QuickProductForm::class)
            ->call('openForm', 'PIN-1')
            ->set('name', 'Another pin')
            ->set('category_id', Category::query()->value('id'))
            ->set('selling_price', '10')
            ->call('save')
            ->assertHasErrors('barcode');
    }

    public function test_created_product_is_added_to_the_purchase_list(): void
    {
        $this->actingAsRole('manager', $this->branch);
        $product = $this->makeProduct(['name' => 'Charger']);

        Livewire::test(PurchaseForm::class)
            ->dispatch('quick-product-created', productUnitId: $product->units->first()->id)
            ->assertCount('items', 1)
            ->assertSet('items.0.product_id', $product->id);
    }

    public function test_product_form_saves_wholesale_price(): void
    {
        $this->actingAsRole('admin', $this->branch);

        Livewire::test(ProductForm::class)
            ->set('name', 'Earphone')
            ->set('category_id', Category::query()->value('id'))
            ->set('cost_price', '100')
            ->set('units.0.selling_price', '200')
            ->set('units.0.wholesale_price', '160')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(160, Product::query()->where('name', 'Earphone')->firstOrFail()->units->first()->wholesale_price);
    }

    public function test_product_info_shows_stock_and_prices(): void
    {
        $this->actingAsRole('manager', $this->branch);
        $product = $this->makeProduct(['name' => 'Charger', 'cost_price' => 321], piecePrice: 450);
        $product->units->first()->update(['wholesale_price' => 399]);
        app(StockService::class)->move($this->branch, $product, 7, StockMovementType::Opening);

        Livewire::test(ProductInfo::class)
            ->call('show', $product->id)
            ->assertSet('open', true)
            ->assertSee(__('Buying price'))
            ->assertSee('321')
            ->assertSee('399')
            ->assertSee('450')
            ->assertSee('7');
    }

    public function test_product_info_hides_buying_price_without_permission(): void
    {
        $this->actingAsRole('cashier', $this->branch);
        $product = $this->makeProduct(['name' => 'Charger', 'cost_price' => 321], piecePrice: 450);

        Livewire::test(ProductInfo::class)
            ->call('show', $product->id)
            ->assertDontSee(__('Buying price'))
            ->assertDontSee('321')
            ->assertSee('450');
    }

    public function test_pos_shows_cost_and_profit_only_to_users_who_may_see_costs(): void
    {
        $product = $this->makeProduct(['name' => 'Charger', 'cost_price' => 321], piecePrice: 450);
        app(StockService::class)->move($this->branch, $product, 10, StockMovementType::Opening);

        $this->actingAsRole('manager', $this->branch)->givePermissionTo('pos.sell');
        app(CashShiftService::class)->open($this->branch, 0);

        Livewire::test(PointOfSale::class)
            ->call('pickUnit', $product->units->first()->id)
            ->call('increment', 0)
            ->assertSee(__('Show cost & profit'))
            ->assertSee(money(642))
            ->assertSee(money(258));

        $this->actingAsRole('cashier', $this->branch);

        Livewire::test(PointOfSale::class)
            ->call('pickUnit', $product->units->first()->id)
            ->assertDontSee(__('Show cost & profit'))
            ->assertDontSee(money(321));
    }
}
