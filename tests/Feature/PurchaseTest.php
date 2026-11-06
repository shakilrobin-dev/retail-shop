<?php

namespace Tests\Feature;

use App\Livewire\Purchases\PurchaseForm;
use App\Livewire\Purchases\PurchaseShow;
use App\Livewire\Suppliers\SupplierIndex;
use App\Models\Branch;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PurchaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PaymentMethod::factory()->create();
    }

    public function test_supplier_can_be_added(): void
    {
        $this->actingAsRole('admin');

        Livewire::test(SupplierIndex::class)
            ->call('create')
            ->set('name', 'Dhaka Mobile Wholesale')
            ->set('phone', '01711000000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('suppliers', ['name' => 'Dhaka Mobile Wholesale', 'phone' => '01711000000']);
    }

    public function test_purchase_by_box_scanned_with_barcode_adds_pieces_to_stock_and_updates_cost(): void
    {
        $this->actingAsRole('admin');
        $supplier = Supplier::factory()->create();
        $product = $this->makeProduct(['cost_price' => 0], piecePrice: 5, boxQuantity: 10, boxPrice: 45, boxBarcode: 'BOX-1');

        Livewire::test(PurchaseForm::class)
            ->set('supplier_id', $supplier->id)
            ->set('productSearch', 'BOX-1')
            ->call('scan')
            ->assertCount('items', 1)
            ->set('items.0.quantity', '3')
            ->set('items.0.unit_cost', '30')
            ->set('items.0.new_selling_price', '50')
            ->call('save')
            ->assertHasNoErrors();

        $purchase = Purchase::query()->with('items', 'payments')->sole();
        $this->assertSame('90.00', $purchase->total);
        $this->assertSame('30.000', $purchase->items->first()->base_quantity);
        $this->assertSame('90.00', $purchase->payments->first()->amount);
        $this->assertSame('out', $purchase->payments->first()->direction->value);

        $branch = Branch::query()->first();
        $this->assertSame(30.0, app(StockService::class)->quantity($branch, $product));
        $this->assertSame('3.00', $product->fresh()->cost_price);
        $this->assertSame('50.00', $product->units->firstWhere('is_base', false)->fresh()->selling_price);
    }

    public function test_bill_discount_lowers_average_cost(): void
    {
        $this->actingAsRole('admin');
        $product = $this->makeProduct(['cost_price' => 0], pieceBarcode: 'P1');

        Livewire::test(PurchaseForm::class)
            ->set('productSearch', 'P1')
            ->call('scan')
            ->set('items.0.quantity', '10')
            ->set('items.0.unit_cost', '10')
            ->set('discount', '20')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('8.00', $product->fresh()->cost_price);
    }

    public function test_purchase_without_items_is_rejected(): void
    {
        $this->actingAsRole('admin');

        Livewire::test(PurchaseForm::class)->call('save')->assertHasErrors('items');
    }

    public function test_return_to_supplier_reduces_stock_and_records_refund(): void
    {
        $this->actingAsRole('admin');
        $product = $this->makeProduct(['cost_price' => 0], pieceBarcode: 'P1');

        Livewire::test(PurchaseForm::class)
            ->set('productSearch', 'P1')
            ->call('scan')
            ->set('items.0.quantity', '10')
            ->set('items.0.unit_cost', '20')
            ->call('save');

        $purchase = Purchase::query()->with('items')->sole();
        $item = $purchase->items->first();

        Livewire::test(PurchaseShow::class, ['purchase' => $purchase])
            ->set('showReturn', true)
            ->set("returns.{$item->id}", '4')
            ->call('saveReturn')
            ->assertHasNoErrors();

        $this->assertSame(6.0, app(StockService::class)->quantity(Branch::query()->first(), $product));
        $this->assertSame('4.000', $item->fresh()->returned_base_quantity);
        $this->assertDatabaseHas('purchase_returns', ['purchase_id' => $purchase->id, 'total' => 80]);

        Livewire::test(PurchaseShow::class, ['purchase' => $purchase])
            ->set("returns.{$item->id}", '7')
            ->call('saveReturn')
            ->assertHasErrors("returns.{$item->id}");
    }

    public function test_cashier_cannot_open_purchases(): void
    {
        $this->actingAsRole('cashier');

        $this->get(route('purchases.index'))->assertForbidden();
    }

    public function test_purchase_pages_render(): void
    {
        $this->actingAsRole('admin');

        $this->get(route('purchases.index'))->assertOk();
        $this->get(route('purchases.create'))->assertOk();
        $this->get(route('suppliers.index'))->assertOk();
    }
}
