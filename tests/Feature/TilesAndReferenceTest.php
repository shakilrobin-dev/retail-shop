<?php

namespace Tests\Feature;

use App\Enums\CommissionType;
use App\Enums\PaymentMethodType;
use App\Enums\StockMovementType;
use App\Livewire\Pos\PointOfSale;
use App\Livewire\Products\ProductForm;
use App\Livewire\Referrers\ReferrerIndex;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Expense;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Referrer;
use App\Models\Sale;
use App\Models\Unit;
use App\Services\CashShiftService;
use App\Services\ReportDocument;
use App\Services\ReportService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TilesAndReferenceTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private PaymentMethod $cash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create(['code' => 'B1']);
        $this->cash = PaymentMethod::factory()->create(['name' => 'Cash', 'type' => PaymentMethodType::Cash, 'sort_order' => 0]);
    }

    /**
     * 1.77 sqft tiles, 10 pieces in a carton, sold at 120 per sqft, bought at 150 per piece.
     */
    private function tiles(float $pieces = 500): Product
    {
        $product = $this->makeProduct(['name' => 'Floor tiles 1.77', 'cost_price' => 150, 'measure_unit' => 'sqft', 'measure_per_unit' => 1.77, 'measure_price' => 120],
            piecePrice: 212.40, boxQuantity: 10, boxPrice: 2124);
        $product->units->firstWhere('is_base', false)->update(['name' => 'Carton']);
        app(StockService::class)->move($this->branch, $product, $pieces, StockMovementType::Opening);

        return $product->load('units');
    }

    private function startSelling(string $role = 'manager'): void
    {
        $user = $this->actingAsRole($role, $this->branch);
        $user->givePermissionTo('pos.sell');
        app(CashShiftService::class)->open($this->branch, 0);
    }

    public function test_pieces_needed_for_a_measurement_are_rounded_up(): void
    {
        $product = new Product(['measure_unit' => 'sqft', 'measure_per_unit' => 1.77]);

        $this->assertSame(283, $product->piecesForMeasure(500));
        $this->assertSame(1, $product->piecesForMeasure(1.77));
        $this->assertSame(2, $product->piecesForMeasure(1.78));
        $this->assertEquals(500.91, $product->measureForPieces(283));
    }

    public function test_typing_sqft_works_out_pieces_and_bills_whole_pieces(): void
    {
        $product = $this->tiles();
        $this->startSelling();

        $component = Livewire::test(PointOfSale::class)
            ->call('pickUnit', $product->units->firstWhere('is_base', true)->id)
            ->assertSet('cart.0.quantity', '1.77')
            ->assertSet('cart.0.unit_price', '120')
            ->set('cart.0.quantity', '500')
            ->assertSet('cart.0.pieces', '283')
            ->assertSee('28 Carton + 3 Piece')
            ->assertSee('500.91');

        $this->assertEquals(60109.20, $component->instance()->totals()['total']);

        $component->call('openPayment')->call('payExact', 0)->call('complete')->assertHasNoErrors();

        $sale = Sale::query()->with('items')->sole();
        $item = $sale->items->first();
        $this->assertEquals(60109.20, (float) $sale->total);
        $this->assertEquals(283, (float) $item->quantity);
        $this->assertEquals(500.91, (float) $item->measure_quantity);
        $this->assertEquals(120, (float) $item->measure_rate);
        $this->assertSame('sqft', $item->measure_unit);
        $this->assertEquals(217, app(StockService::class)->quantity($this->branch, $product));
    }

    public function test_typing_pieces_works_out_sqft_and_carton_adds_ten_pieces(): void
    {
        $product = $this->tiles();
        $this->startSelling();

        Livewire::test(PointOfSale::class)
            ->call('pickUnit', $product->units->firstWhere('is_base', false)->id)
            ->assertCount('cart', 1)
            ->assertSet('cart.0.pieces', '10')
            ->assertSet('cart.0.quantity', '17.7')
            ->call('pickUnit', $product->units->firstWhere('is_base', false)->id)
            ->assertCount('cart', 1)
            ->assertSet('cart.0.pieces', '20')
            ->set('cart.0.pieces', '12')
            ->assertSet('cart.0.quantity', '21.24')
            ->call('increment', 0)
            ->assertSet('cart.0.pieces', '13');
    }

    public function test_held_tiles_sale_comes_back_with_pieces_rate_and_reference(): void
    {
        $product = $this->tiles();
        $referrer = Referrer::factory()->withPercent(10)->create();
        $this->startSelling();
        auth()->user()->givePermissionTo('pos.edit-price');

        $component = Livewire::test(PointOfSale::class)
            ->call('pickUnit', $product->units->firstWhere('is_base', true)->id)
            ->set('cart.0.quantity', '100')
            ->set('cart.0.unit_price', '110')
            ->call('selectReferrer', $referrer->id)
            ->call('hold')
            ->assertCount('cart', 0)
            ->assertSet('referrerId', null);

        $component->call('resume', Sale::query()->sole()->id)
            ->assertSet('cart.0.pieces', '57')
            ->assertSet('cart.0.unit_price', '110')
            ->assertSet('referrerId', $referrer->id);
    }

    public function test_product_form_saves_measurement_and_works_out_unit_prices(): void
    {
        $this->actingAsRole('admin', $this->branch);
        $category = Category::factory()->create();
        Unit::factory()->create(['name' => 'Piece']);

        Livewire::test(ProductForm::class)
            ->set('name', 'Wall tiles 12x24')
            ->set('category_id', $category->id)
            ->call('addPack')
            ->set('units.1.name', 'Carton')
            ->set('units.1.quantity', '10')
            ->set('is_measured', true)
            ->set('measure_unit', 'sqft')
            ->set('measure_per_unit', '1.77')
            ->set('measure_price', '120')
            ->assertSet('units.0.selling_price', '212.4')
            ->assertSet('units.1.selling_price', '2124')
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::query()->with('units')->where('name', 'Wall tiles 12x24')->sole();
        $this->assertTrue($product->isMeasured());
        $this->assertEquals(1.77, (float) $product->measure_per_unit);
        $this->assertEquals(120, (float) $product->measure_price);
        $this->assertEquals(2124, (float) $product->units->firstWhere('name', 'Carton')->selling_price);
    }

    public function test_measurement_per_piece_is_required_when_selling_by_measurement(): void
    {
        $this->actingAsRole('admin', $this->branch);
        $category = Category::factory()->create();
        Unit::factory()->create();

        Livewire::test(ProductForm::class)
            ->set('name', 'Tiles')
            ->set('category_id', $category->id)
            ->set('units.0.selling_price', '100')
            ->set('is_measured', true)
            ->set('measure_per_unit', '')
            ->call('save')
            ->assertHasErrors('measure_per_unit');
    }

    public function test_reference_can_be_added_at_the_pos_and_gets_percent_of_profit(): void
    {
        $product = $this->makeProduct(['name' => 'Cement', 'cost_price' => 500], piecePrice: 550);
        app(StockService::class)->move($this->branch, $product, 100, StockMovementType::Opening);
        $this->startSelling();

        Livewire::test(PointOfSale::class)
            ->set('referrerSearch', '01712345678')
            ->assertSet('newReferrerPhone', '01712345678')
            ->set('newReferrerName', 'Rahim Mistri')
            ->call('createReferrer')
            ->assertHasNoErrors()
            ->call('pickUnit', $product->units->first()->id)
            ->set('cart.0.quantity', '10')
            ->set('commissionType', CommissionType::ProfitPercent->value)
            ->set('commissionValue', '20')
            ->assertSee(money(100))
            ->call('openPayment')->call('payExact', 0)->call('complete')
            ->assertHasNoErrors();

        $sale = Sale::query()->sole();
        $referrer = Referrer::query()->sole();
        $this->assertSame('Rahim Mistri', $referrer->name);
        $this->assertSame($referrer->id, $sale->referrer_id);
        $this->assertSame(CommissionType::ProfitPercent, $sale->commission_type);
        $this->assertEquals(100, (float) $sale->commission_amount);
        $this->assertEquals(100, $referrer->commissionDue());
    }

    public function test_fixed_commission_amount(): void
    {
        $product = $this->makeProduct(['cost_price' => 500], piecePrice: 550);
        app(StockService::class)->move($this->branch, $product, 100, StockMovementType::Opening);
        $referrer = Referrer::factory()->create();
        $this->startSelling();

        Livewire::test(PointOfSale::class)
            ->call('pickUnit', $product->units->first()->id)
            ->call('selectReferrer', $referrer->id)
            ->set('commissionType', CommissionType::Amount->value)
            ->set('commissionValue', '75')
            ->call('openPayment')->call('payExact', 0)->call('complete')
            ->assertHasNoErrors();

        $this->assertEquals(75, (float) Sale::query()->sole()->commission_amount);
    }

    public function test_cashier_cannot_change_commission_and_gets_the_reference_default(): void
    {
        $product = $this->makeProduct(['cost_price' => 500], piecePrice: 550);
        app(StockService::class)->move($this->branch, $product, 100, StockMovementType::Opening);
        $referrer = Referrer::factory()->withPercent(10)->create();
        $this->startSelling('cashier');

        Livewire::test(PointOfSale::class)
            ->call('pickUnit', $product->units->first()->id)
            ->set('cart.0.quantity', '10')
            ->call('selectReferrer', $referrer->id)
            ->assertDontSee(__('Commission on this invoice'))
            ->set('commissionType', CommissionType::Amount->value)
            ->set('commissionValue', '5000')
            ->call('openPayment')->call('payExact', 0)->call('complete')
            ->assertHasNoErrors();

        $this->assertEquals(50, (float) Sale::query()->sole()->commission_amount);
    }

    public function test_paying_commission_is_an_expense_and_shows_in_profit_and_loss(): void
    {
        $product = $this->makeProduct(['cost_price' => 500], piecePrice: 550);
        app(StockService::class)->move($this->branch, $product, 100, StockMovementType::Opening);
        $referrer = Referrer::factory()->withPercent(20)->create();
        $this->startSelling();

        Livewire::test(PointOfSale::class)
            ->call('pickUnit', $product->units->first()->id)
            ->set('cart.0.quantity', '10')
            ->call('selectReferrer', $referrer->id)
            ->call('openPayment')->call('payExact', 0)->call('complete')
            ->assertHasNoErrors();

        Livewire::test(ReferrerIndex::class)
            ->call('openPay', $referrer->id)
            ->assertSet('payAmount', '100')
            ->set('payAmount', '150')
            ->call('pay')
            ->assertHasErrors('payAmount')
            ->set('payAmount', '60')
            ->call('pay')
            ->assertHasNoErrors();

        $expense = Expense::query()->sole();
        $this->assertSame($referrer->id, $expense->referrer_id);
        $this->assertSame('Commission', $expense->category->name);
        $this->assertEquals(40, $referrer->fresh()->commissionDue());

        $summary = app(ReportService::class)->summary($this->branch->id, today(), today());
        $this->assertEquals(60, $summary['commission_given']);
        $this->assertEquals(60, $summary['expenses']);
        $this->assertEquals(500 - 60, $summary['net_profit']);
    }

    public function test_reference_and_commission_reports(): void
    {
        $product = $this->makeProduct(['cost_price' => 500], piecePrice: 550);
        app(StockService::class)->move($this->branch, $product, 100, StockMovementType::Opening);
        $referrer = Referrer::factory()->withPercent(20)->create(['name' => 'Karim Contractor']);
        $this->startSelling();
        $unitId = $product->units->first()->id;

        Livewire::test(PointOfSale::class)
            ->call('pickUnit', $unitId)->set('cart.0.quantity', '10')->call('selectReferrer', $referrer->id)
            ->call('openPayment')->call('payExact', 0)->call('complete')
            ->call('newSale')
            ->call('pickUnit', $unitId)
            ->call('openPayment')->call('payExact', 0)->call('complete')
            ->assertHasNoErrors();

        $document = app(ReportDocument::class)->build('referrers', $this->branch->id, today(), today());
        $karim = collect($document['rows'])->firstWhere('name', 'Karim Contractor ('.$referrer->phone.')');
        $this->assertEquals(5500, $karim['sales']);
        $this->assertEquals(500, $karim['profit']);
        $this->assertEquals(100, $karim['commission']);
        $this->assertEquals(400, $karim['after']);
        $this->assertEqualsWithDelta(9.09, $karim['margin'], 0.01);
        $this->assertEquals(__('Without reference'), $document['rows'][1]['name']);
        $this->assertEquals(6050, $document['totals']['sales']);

        $commissions = app(ReportDocument::class)->build('commissions', $this->branch->id, today(), today());
        $this->assertEquals(100, $commissions['rows'][0]['earned']);
        $this->assertEquals(100, $commissions['rows'][0]['due']);

        $this->get(route('reports.index', ['tab' => 'referrers']))->assertOk()->assertSee('Karim Contractor');
        $this->get(route('referrers.index'))->assertOk()->assertSee('Karim Contractor');
    }

    public function test_references_page_needs_commission_permission(): void
    {
        $this->actingAsRole('cashier', $this->branch);

        $this->get(route('referrers.index'))->assertForbidden();
    }

    public function test_receipt_shows_sqft_and_cartons(): void
    {
        $product = $this->tiles();
        $this->startSelling();

        Livewire::test(PointOfSale::class)
            ->call('pickUnit', $product->units->firstWhere('is_base', true)->id)
            ->set('cart.0.quantity', '500')
            ->call('openPayment')->call('payExact', 0)->call('complete');

        $this->get(route('sales.receipt', Sale::query()->sole()))
            ->assertOk()
            ->assertSee('500.91 sqft')
            ->assertSee('28 Carton + 3 Piece');
    }
}
