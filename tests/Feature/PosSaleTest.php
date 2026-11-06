<?php

namespace Tests\Feature;

use App\Enums\PaymentMethodType;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Livewire\Pos\CashRegister;
use App\Livewire\Pos\PointOfSale;
use App\Livewire\Sales\SaleShow;
use App\Models\Branch;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Services\CashShiftService;
use App\Services\Settings;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PosSaleTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private PaymentMethod $cash;

    private PaymentMethod $bkash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create(['code' => 'B1']);
        $this->cash = PaymentMethod::factory()->create(['name' => 'Cash', 'type' => PaymentMethodType::Cash, 'sort_order' => 0]);
        $this->bkash = PaymentMethod::factory()->create(['name' => 'bKash', 'type' => PaymentMethodType::MobileBanking, 'sort_order' => 1]);
    }

    private function stockedPinBox(float $pieces = 50): Product
    {
        $product = $this->makeProduct(['name' => 'Pin'], piecePrice: 5, pieceBarcode: 'PIN-1', boxQuantity: 10, boxPrice: 45, boxBarcode: 'PIN-BOX');
        app(StockService::class)->move($this->branch, $product, $pieces, StockMovementType::Opening);

        return $product;
    }

    private function openRegister(float $cash = 1000): void
    {
        app(CashShiftService::class)->open($this->branch, $cash);
    }

    public function test_pos_asks_to_open_register_first(): void
    {
        $this->actingAsRole('cashier', $this->branch);

        Livewire::test(PointOfSale::class)
            ->assertSee(__('Cash register is closed'))
            ->set('openingCash', '500')
            ->call('openRegister')
            ->assertDontSee(__('Cash register is closed'));
    }

    public function test_scanning_box_barcode_sells_a_box_and_takes_ten_pieces(): void
    {
        $this->actingAsRole('cashier', $this->branch);
        $product = $this->stockedPinBox();
        $this->openRegister();

        Livewire::test(PointOfSale::class)
            ->set('productSearch', 'PIN-BOX')
            ->call('scan')
            ->assertCount('cart', 1)
            ->assertSet('cart.0.unit_price', '45.00')
            ->call('openPayment')
            ->set('payments.0.amount', '50')
            ->call('complete')
            ->assertHasNoErrors();

        $sale = Sale::query()->with('items', 'payments')->sole();
        $this->assertSame('B1-INV-000001', $sale->invoice_no);
        $this->assertSame('45.00', $sale->total);
        $this->assertSame('5.00', $sale->change_amount);
        $this->assertSame('45.00', $sale->payments->sole()->amount, 'Only cash kept is recorded.');
        $this->assertSame(40.0, app(StockService::class)->quantity($this->branch, $product));
    }

    public function test_scanning_same_item_twice_increases_quantity_and_unit_can_switch_to_piece(): void
    {
        $this->actingAsRole('cashier', $this->branch);
        $product = $this->stockedPinBox();
        $this->openRegister();
        $pieceUnit = $product->units->firstWhere('is_base', true);

        Livewire::test(PointOfSale::class)
            ->set('productSearch', 'PIN-BOX')->call('scan')
            ->set('cart.0.product_unit_id', $pieceUnit->id)
            ->assertSet('cart.0.unit_price', '5.00')
            ->set('productSearch', 'PIN-1')->call('scan')
            ->assertCount('cart', 1)
            ->assertSet('cart.0.quantity', '2')
            ->call('openPayment')
            ->call('payExact', 0)
            ->call('complete')
            ->assertHasNoErrors();

        $this->assertSame(48.0, app(StockService::class)->quantity($this->branch, $product));
    }

    public function test_split_payment_cash_and_bkash(): void
    {
        $this->actingAsRole('cashier', $this->branch);
        $this->stockedPinBox();
        $this->openRegister();

        Livewire::test(PointOfSale::class)
            ->set('productSearch', 'PIN-BOX')->call('scan')
            ->set('cart.0.quantity', '2')
            ->call('openPayment')
            ->set('payments.0.amount', '40')
            ->call('addPayment', $this->bkash->id)
            ->set('payments.1.transaction_ref', 'TRX123')
            ->call('complete')
            ->assertHasNoErrors();

        $sale = Sale::query()->with('payments')->sole();
        $this->assertSame('90.00', $sale->total);
        $this->assertSame(['40.00', '50.00'], $sale->payments->pluck('amount')->sort()->values()->all());
        $this->assertSame('TRX123', $sale->payments->firstWhere('payment_method_id', $this->bkash->id)->transaction_ref);
    }

    public function test_short_payment_is_rejected(): void
    {
        $this->actingAsRole('cashier', $this->branch);
        $this->stockedPinBox();
        $this->openRegister();

        Livewire::test(PointOfSale::class)
            ->set('productSearch', 'PIN-BOX')->call('scan')
            ->call('openPayment')
            ->set('payments.0.amount', '20')
            ->call('complete')
            ->assertHasErrors('payments');

        $this->assertSame(0, Sale::query()->count());
    }

    public function test_cannot_sell_more_than_stock(): void
    {
        $this->actingAsRole('cashier', $this->branch);
        $this->stockedPinBox(5);
        $this->openRegister();

        Livewire::test(PointOfSale::class)
            ->set('productSearch', 'PIN-BOX')->call('scan')
            ->call('openPayment')
            ->set('payments.0.amount', '45')
            ->call('complete')
            ->assertHasErrors('stock');

        $this->assertSame(0, Sale::query()->where('status', SaleStatus::Completed)->count());
    }

    public function test_cashier_discount_is_limited_by_setting(): void
    {
        $this->actingAsRole('cashier', $this->branch);
        $this->stockedPinBox();
        $this->openRegister();
        app(Settings::class)->set('pos_max_discount_percent', 10);

        Livewire::test(PointOfSale::class)
            ->set('productSearch', 'PIN-BOX')->call('scan')
            ->set('discountType', 'percent')
            ->set('discountValue', '20')
            ->call('openPayment')
            ->call('payExact', 0)
            ->call('complete')
            ->assertHasErrors('discountValue')
            ->set('discountValue', '10')
            ->call('payExact', 0)
            ->call('complete')
            ->assertHasNoErrors();

        $this->assertSame('40.50', Sale::query()->sole()->total);
    }

    public function test_vat_is_added_when_enabled(): void
    {
        $this->actingAsRole('admin', $this->branch);
        $product = $this->stockedPinBox();
        $product->update(['vat_rate' => 5]);
        app(Settings::class)->set('vat_enabled', true);
        $this->openRegister();

        Livewire::test(PointOfSale::class)
            ->set('productSearch', 'PIN-BOX')->call('scan')
            ->call('openPayment')->call('payExact', 0)->call('complete')
            ->assertHasNoErrors();

        $sale = Sale::query()->sole();
        $this->assertSame('2.25', $sale->vat_amount);
        $this->assertSame('47.25', $sale->total);
    }

    public function test_hold_and_resume_sale(): void
    {
        $this->actingAsRole('cashier', $this->branch);
        $this->stockedPinBox();
        $this->openRegister();

        $component = Livewire::test(PointOfSale::class)
            ->set('productSearch', 'PIN-BOX')->call('scan')
            ->set('holdNote', 'Rahim')
            ->call('hold')
            ->assertCount('cart', 0);

        $held = Sale::query()->where('status', SaleStatus::Held)->sole();
        $this->assertNull($held->invoice_no);

        $component->call('resume', $held->id)->assertCount('cart', 1);
        $this->assertSame(0, Sale::query()->count());
    }

    public function test_void_returns_stock_and_refunds(): void
    {
        $this->actingAsRole('admin', $this->branch);
        $product = $this->stockedPinBox();
        $this->openRegister();

        Livewire::test(PointOfSale::class)
            ->set('productSearch', 'PIN-BOX')->call('scan')
            ->call('openPayment')->call('payExact', 0)->call('complete');

        $sale = Sale::query()->sole();

        Livewire::test(SaleShow::class, ['sale' => $sale])
            ->set('void_reason', 'Wrong item')
            ->call('void')
            ->assertHasNoErrors();

        $this->assertSame(SaleStatus::Voided, $sale->fresh()->status);
        $this->assertSame(50.0, app(StockService::class)->quantity($this->branch, $product));
        $this->assertDatabaseHas('payments', ['payable_id' => $sale->id, 'direction' => 'out', 'amount' => 45]);
    }

    public function test_partial_return_refunds_paid_price_and_restocks(): void
    {
        $this->actingAsRole('admin', $this->branch);
        $product = $this->stockedPinBox();
        $this->openRegister();

        Livewire::test(PointOfSale::class)
            ->set('productSearch', 'PIN-1')->call('scan')
            ->set('cart.0.quantity', '4')
            ->set('discountValue', '2')
            ->call('openPayment')->call('payExact', 0)->call('complete')
            ->assertHasNoErrors();

        $sale = Sale::query()->with('items')->sole();
        $item = $sale->items->first();

        Livewire::test(SaleShow::class, ['sale' => $sale])
            ->set("returns.{$item->id}.quantity", '2')
            ->call('saveReturn')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sale_returns', ['sale_id' => $sale->id, 'total' => 9]);
        $this->assertSame(48.0, app(StockService::class)->quantity($this->branch, $product));
    }

    public function test_cash_register_expected_cash_and_difference(): void
    {
        $this->actingAsRole('cashier', $this->branch);
        $this->stockedPinBox();
        $this->openRegister(500);

        Livewire::test(PointOfSale::class)
            ->set('productSearch', 'PIN-BOX')->call('scan')
            ->call('openPayment')->set('payments.0.amount', '100')->call('complete');

        Livewire::test(CashRegister::class)
            ->assertSee(money(545))
            ->set('countedCash', '540')
            ->call('close')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cash_shifts', ['expected_cash' => 545, 'counted_cash' => 540, 'difference' => -5, 'status' => 'closed']);
    }

    public function test_receipt_and_sales_pages_render(): void
    {
        $this->actingAsRole('admin', $this->branch);
        $this->stockedPinBox();
        $this->openRegister();

        Livewire::test(PointOfSale::class)
            ->set('productSearch', 'PIN-BOX')->call('scan')
            ->call('openPayment')->call('payExact', 0)->call('complete');

        $sale = Sale::query()->sole();

        $this->get(route('sales.receipt', $sale))->assertOk()->assertSee($sale->invoice_no)->assertSee('Pin');
        $this->get(route('sales.receipt', ['sale' => $sale, 'lang' => 'bn']))->assertOk()->assertSee('ইনভয়েস', false);
        $this->get(route('sales.index'))->assertOk()->assertSee($sale->invoice_no);
        $this->get(route('sales.show', $sale))->assertOk();
        $this->get(route('pos'))->assertOk();
        $this->get(route('customers.index'))->assertOk();
    }
}
