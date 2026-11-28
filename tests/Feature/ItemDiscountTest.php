<?php

namespace Tests\Feature;

use App\Enums\DiscountType;
use App\Enums\PaymentMethodType;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Livewire\Pos\PointOfSale;
use App\Livewire\Quotations\QuotationForm;
use App\Models\Branch;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Sale;
use App\Services\CashShiftService;
use App\Services\DiscountChain;
use App\Services\SaleService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ItemDiscountTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private PaymentMethod $cash;

    private Product $tap;

    private Product $pipe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create(['code' => 'B1']);
        $this->actingAsRole('admin', $this->branch);
        $this->cash = PaymentMethod::factory()->create(['name' => 'Cash', 'type' => PaymentMethodType::Cash]);
        app(CashShiftService::class)->open($this->branch, 0);

        $this->tap = $this->makeProduct(['name' => 'Bib Tap', 'cost_price' => 40], piecePrice: 100, pieceBarcode: 'TAP');
        $this->pipe = $this->makeProduct(['name' => 'PVC Pipe', 'cost_price' => 30], piecePrice: 50, pieceBarcode: 'PIPE');
        app(StockService::class)->move($this->branch, $this->tap, 20, StockMovementType::Opening);
        app(StockService::class)->move($this->branch, $this->pipe, 20, StockMovementType::Opening);
    }

    public function test_discount_percentages_are_taken_one_after_another(): void
    {
        $this->assertSame(31.6, DiscountChain::amount(100, '20,10,5'));
        $this->assertSame(31.6, DiscountChain::amount(100, ' 20%, 10 ,5% '));
        $this->assertSame(10.0, DiscountChain::amount(100, '10'));
        $this->assertSame(0.0, DiscountChain::amount(100, ''));
        $this->assertSame('20,10,5', DiscountChain::normalize('20, 10,5%'));
        $this->assertSame('20% + 10% + 5%', DiscountChain::label('20,10,5'));
        $this->assertFalse(DiscountChain::isValid('abc'));
        $this->assertFalse(DiscountChain::isValid('120'));
        $this->assertFalse(DiscountChain::isValid('10,-5'));
    }

    public function test_bill_discount_applies_only_to_ticked_items(): void
    {
        $tapUnit = $this->tap->units->first()->id;
        $pipeUnit = $this->pipe->units->first()->id;
        $cart = [
            ['product_unit_id' => $tapUnit, 'quantity' => 1, 'unit_price' => 100, 'discount' => '20,10,5', 'bill_discount' => true],
            ['product_unit_id' => $pipeUnit, 'quantity' => 1, 'unit_price' => 50, 'discount' => '', 'bill_discount' => false],
        ];

        $percent = app(SaleService::class)->calculate($this->branch, $cart, DiscountType::Percent, 10);
        $this->assertEquals(68.40, $percent['lines'][0]['line_total']);
        $this->assertEquals(68.40, $percent['discountable']);
        $this->assertEquals(6.84, $percent['discount_amount']);
        $this->assertEquals(0, $percent['lines'][1]['bill_discount_amount']);
        $this->assertEquals(111.56, $percent['total']);

        $amount = app(SaleService::class)->calculate($this->branch, $cart, DiscountType::Amount, 500);
        $this->assertEquals(68.40, $amount['discount_amount']);
        $this->assertEquals(50, $amount['total']);
    }

    public function test_pos_sale_with_chained_discount_and_unticked_item(): void
    {
        $component = Livewire::test(PointOfSale::class)
            ->set('productSearch', 'TAP')->call('scan')
            ->set('productSearch', 'PIPE')->call('scan')
            ->assertSet('cart.0.bill_discount', true)
            ->set('cart.1.discount', '20,10,5')
            ->assertSee('68.40')
            ->set('cart.0.bill_discount', false)
            ->set('discountType', 'percent')
            ->set('discountValue', '10')
            ->assertSee('On ticked items');

        $this->assertEquals(111.56, $component->instance()->totals()['total']);

        $component->call('openPayment')->call('payExact', 0)->call('complete')->assertHasNoErrors();

        $sale = Sale::query()->with('items')->sole();
        $tap = $sale->items->firstWhere('product_id', $this->tap->id);
        $pipe = $sale->items->firstWhere('product_id', $this->pipe->id);
        $this->assertSame('20,10,5', $tap->discount_percents);
        $this->assertEquals(31.60, $tap->discount_amount);
        $this->assertTrue($tap->bill_discount);
        $this->assertEquals(6.84, $tap->bill_discount_amount);
        $this->assertFalse($pipe->bill_discount);
        $this->assertEquals(0, $pipe->bill_discount_amount);

        $this->get(route('sales.receipt', $sale))->assertOk()->assertSee('Discount 20% + 10% + 5%');

        $return = app(SaleService::class)->return($sale, [$pipe->id => ['quantity' => 1, 'restock' => true]], $this->cash);
        $this->assertEquals(50, $return->total);
    }

    public function test_tick_all_and_wrong_discount_text(): void
    {
        Livewire::test(PointOfSale::class)
            ->set('productSearch', 'TAP')->call('scan')
            ->set('productSearch', 'PIPE')->call('scan')
            ->call('setBillDiscountForAll', false)
            ->assertSet('cart.0.bill_discount', false)
            ->assertSet('cart.1.bill_discount', false)
            ->call('setBillDiscountForAll', true)
            ->assertSet('cart.1.bill_discount', true)
            ->set('cart.0.discount', '10,abc')
            ->assertSee('e.g. 20,10,5')
            ->call('openPayment')
            ->assertHasErrors('cart.0.discount')
            ->assertSet('showPayment', false);
    }

    public function test_held_sale_and_quotation_keep_discounts_and_ticks(): void
    {
        Livewire::test(PointOfSale::class)
            ->set('productSearch', 'TAP')->call('scan')
            ->set('cart.0.discount', '15,5')
            ->set('cart.0.bill_discount', false)
            ->call('hold');

        $held = Sale::query()->where('status', SaleStatus::Held)->sole();

        Livewire::test(PointOfSale::class)
            ->call('resume', $held->id)
            ->assertSet('cart.0.discount', '15,5')
            ->assertSet('cart.0.bill_discount', false);

        Livewire::test(QuotationForm::class)
            ->set('productSearch', 'PIPE')->call('pickUnit', $this->pipe->units->first()->id)
            ->set('cart.0.discount', '10,10')
            ->set('cart.0.bill_discount', false)
            ->call('save')
            ->assertHasNoErrors();

        $quotation = Quotation::query()->with('items')->sole();
        $this->assertSame('10,10', $quotation->items->first()->discount_percents);
        $this->assertEquals(40.5, $quotation->items->first()->line_total);

        Livewire::test(QuotationForm::class, ['quotation' => $quotation])
            ->assertSet('cart.0.discount', '10,10')
            ->assertSet('cart.0.bill_discount', false);
    }
}
