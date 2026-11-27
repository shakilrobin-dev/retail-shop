<?php

namespace Tests\Feature;

use App\Enums\PaymentMethodType;
use App\Enums\StockMovementType;
use App\Livewire\Pos\PointOfSale;
use App\Livewire\Quotations\QuotationForm;
use App\Livewire\Quotations\QuotationIndex;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Sale;
use App\Services\CashShiftService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuotationTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create(['code' => 'B1', 'name' => 'Mirpur Branch']);
        PaymentMethod::factory()->create(['name' => 'Cash', 'type' => PaymentMethodType::Cash, 'sort_order' => 0]);
    }

    private function tiles(): Product
    {
        $product = $this->makeProduct(['name' => 'Mirror tiles', 'cost_price' => 150, 'measure_unit' => 'sqft', 'measure_per_unit' => 1.77, 'measure_price' => 120],
            piecePrice: 212.40, boxQuantity: 10, boxPrice: 2124);
        $product->units->firstWhere('is_base', false)->update(['name' => 'Carton']);
        app(StockService::class)->move($this->branch, $product, 500, StockMovementType::Opening);

        return $product->load('units');
    }

    public function test_cashier_makes_a_quotation_with_their_own_prices_and_sqft(): void
    {
        $this->actingAsRole('cashier', $this->branch);
        $tiles = $this->tiles();
        $cement = $this->makeProduct(['name' => 'Cement'], piecePrice: 550);

        Livewire::test(QuotationForm::class)
            ->set('customerName', 'Abdul Karim')
            ->set('customerPhone', '01700000000')
            ->set('customerAddress', 'House 5, Mirpur')
            ->call('pickUnit', $tiles->units->firstWhere('is_base', true)->id)
            ->set('cart.0.quantity', '500')
            ->assertSet('cart.0.pieces', '283')
            ->set('cart.0.unit_price', '110')
            ->call('pickUnit', $cement->units->first()->id)
            ->set('cart.0.quantity', '20')
            ->set('cart.0.unit_price', '520')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('quotations.index'));

        $quotation = Quotation::query()->with('items')->sole();
        $this->assertSame('Abdul Karim', $quotation->customer_name);
        $this->assertSame($this->branch->id, $quotation->branch_id);
        $this->assertSame('B1-QT-000001', $quotation->quotation_no);
        $tilesItem = $quotation->items->firstWhere('product_id', $tiles->id);
        $this->assertEquals(283, (float) $tilesItem->quantity);
        $this->assertEquals(500.91, (float) $tilesItem->measure_quantity);
        $this->assertEquals(110, (float) $tilesItem->measure_rate);
        $this->assertEquals(55100.10 + 10400, (float) $quotation->total);
        $this->assertEquals(500, app(StockService::class)->quantity($this->branch, $tiles));

        $this->get(route('quotations.print', $quotation))
            ->assertOk()
            ->assertSee('QUOTATION')
            ->assertSee('Mirpur Branch')
            ->assertSee('500.91 sqft')
            ->assertSee('28 Carton + 3 Piece')
            ->assertSee('Abdul Karim');
    }

    public function test_quotation_is_sold_in_the_pos_with_its_prices(): void
    {
        $this->actingAsRole('cashier', $this->branch);
        app(CashShiftService::class)->open($this->branch, 0);
        $tiles = $this->tiles();
        $customer = Customer::factory()->create(['phone' => '01700000000']);

        Livewire::test(QuotationForm::class)
            ->set('customerPhone', '01700000000')
            ->call('pickUnit', $tiles->units->firstWhere('is_base', false)->id)
            ->set('cart.0.unit_price', '100')
            ->call('save');

        $quotation = Quotation::query()->sole();
        $this->assertSame($customer->id, $quotation->customer_id);

        $this->get(route('pos', ['quotation' => $quotation->id]))->assertOk()->assertSee($quotation->quotation_no);

        Livewire::withQueryParams(['quotation' => $quotation->id])->test(PointOfSale::class)
            ->assertSet('quotationId', $quotation->id)
            ->assertSet('customerId', $customer->id)
            ->assertSet('cart.0.pieces', '10')
            ->assertSet('cart.0.unit_price', '100')
            ->call('openPayment')->call('payExact', 0)->call('complete')
            ->assertHasNoErrors();

        $sale = Sale::query()->sole();
        $this->assertEquals(1770, (float) $sale->total);
        $this->assertSame($sale->id, $quotation->fresh()->sale_id);

        Livewire::test(QuotationIndex::class)->assertSee(__('Sold'))->assertSee($sale->invoice_no);
    }

    public function test_quotation_can_be_edited_and_another_branch_cannot_open_it(): void
    {
        $this->actingAsRole('cashier', $this->branch);
        $cement = $this->makeProduct(['name' => 'Cement'], piecePrice: 550);

        Livewire::test(QuotationForm::class)->call('pickUnit', $cement->units->first()->id)->call('save');
        $quotation = Quotation::query()->sole();

        Livewire::test(QuotationForm::class, ['quotation' => $quotation])
            ->assertSet('cart.0.unit_price', '550')
            ->set('cart.0.quantity', '3')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(1650, (float) $quotation->fresh()->total);
        $this->assertSame(1, $quotation->items()->count());

        $other = Branch::factory()->create(['code' => 'B2']);
        $this->actingAsRole('cashier', $other);
        $this->get(route('quotations.edit', $quotation))->assertNotFound();
        $this->get(route('quotations.print', $quotation))->assertNotFound();
    }

    public function test_empty_quotation_is_not_saved(): void
    {
        $this->actingAsRole('cashier', $this->branch);

        Livewire::test(QuotationForm::class)->call('save')->assertHasErrors('cart');
    }
}
