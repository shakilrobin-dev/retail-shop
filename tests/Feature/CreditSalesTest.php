<?php

namespace Tests\Feature;

use App\Enums\PaymentMethodType;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Livewire\Customers\CustomerIndex;
use App\Livewire\Customers\CustomerShow;
use App\Livewire\Pos\PointOfSale;
use App\Livewire\Reports\ReportIndex;
use App\Livewire\Sales\SaleIndex;
use App\Livewire\Sales\SaleShow;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\CashShiftService;
use App\Services\CustomerDueService;
use App\Services\ReportDocument;
use App\Services\SaleService;
use App\Services\Settings;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class CreditSalesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private PaymentMethod $cash;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->actingAsRole('admin');
        $this->cash = PaymentMethod::factory()->create(['type' => PaymentMethodType::Cash, 'name' => 'Cash']);
        app(CashShiftService::class)->open($this->user->branch, 1000);
        $this->product = $this->makeProduct(['name' => 'Charger', 'cost_price' => 300], piecePrice: 500, pieceBarcode: 'CHG');
        app(StockService::class)->move($this->user->branch, $this->product, 50, StockMovementType::Opening);
    }

    /**
     * Sell $quantity chargers (500 each) and pay $paid in cash.
     */
    private function sell(int $quantity, string $paid, ?Customer $customer = null): Testable
    {
        $pos = Livewire::test(PointOfSale::class)
            ->set('productSearch', 'CHG')->call('scan')
            ->set('cart.0.quantity', (string) $quantity);

        if ($customer) {
            $pos->call('selectCustomer', $customer->id);
        }

        return $pos->call('openPayment')->set('payments.0.amount', $paid)->call('complete');
    }

    public function test_partly_paid_sale_leaves_the_rest_as_due(): void
    {
        $customer = Customer::factory()->create();

        $this->sell(2, '400', $customer)->assertHasNoErrors();

        $sale = Sale::query()->sole();
        $this->assertSame('600.00', $sale->due_amount);
        $this->assertSame(PaymentStatus::Partial, $sale->payment_status);
        $this->assertSame(today()->addDays(30)->toDateString(), $sale->due_date->toDateString());
        $this->assertSame(600.0, $customer->totalDue());
        $this->assertSame(1400.0, app(CashShiftService::class)->summary($sale->cashShift)['expected']);
    }

    public function test_unpaid_sale_is_fully_due(): void
    {
        $customer = Customer::factory()->create();

        $this->sell(1, '', $customer)->assertHasNoErrors();

        $sale = Sale::query()->sole();
        $this->assertSame(PaymentStatus::Due, $sale->payment_status);
        $this->assertSame('500.00', $sale->due_amount);
        $this->assertCount(0, $sale->payments);
    }

    public function test_walk_in_customer_cannot_buy_on_due(): void
    {
        $this->sell(2, '400')->assertHasErrors('payments');

        $this->assertSame(0, Sale::query()->count());
    }

    public function test_due_is_not_allowed_when_credit_sales_are_off(): void
    {
        app(Settings::class)->set('credit_sales_enabled', false);

        $this->sell(2, '400', Customer::factory()->create())->assertHasErrors('payments');
    }

    public function test_staff_without_permission_cannot_sell_on_due(): void
    {
        $cashier = User::factory()->create(['branch_id' => $this->user->branch_id]);
        $cashier->assignRole('cashier');
        $cashier->roles->first()->revokePermissionTo('pos.credit-sale');
        $this->actingAs($cashier);

        $this->sell(2, '400', Customer::factory()->create())->assertHasErrors('payments');
    }

    public function test_credit_limit_is_enforced(): void
    {
        $customer = Customer::factory()->create(['credit_limit' => 500]);

        $this->sell(1, '0', $customer)->assertHasNoErrors();
        $this->sell(1, '0', $customer)->assertHasErrors('payments');

        $this->assertSame(1, Sale::query()->count());
    }

    public function test_bill_is_rounded_as_set(): void
    {
        $cable = $this->makeProduct(['name' => 'Cable'], piecePrice: 247.6, pieceBarcode: 'CBL');
        app(StockService::class)->move($this->user->branch, $cable, 5, StockMovementType::Opening);
        app(Settings::class)->set('rounding_mode', 'nearest');
        app(Settings::class)->set('rounding_step', '5');

        Livewire::test(PointOfSale::class)
            ->set('productSearch', 'CBL')->call('scan')
            ->call('openPayment')->call('payExact', 0)->call('complete')
            ->assertHasNoErrors();

        $sale = Sale::query()->sole();
        $this->assertSame('250.00', $sale->total);
        $this->assertSame('2.40', $sale->rounding_amount);

        app(Settings::class)->set('rounding_mode', 'down');
        app(Settings::class)->set('rounding_step', '1');
        $this->assertSame(1247.0, app(SaleService::class)->roundBill(1247.6, $this->user->branch));
    }

    public function test_collection_pays_oldest_invoices_first(): void
    {
        $customer = Customer::factory()->create();
        $this->sell(2, '0', $customer);
        $this->travel(1)->days();
        $this->sell(1, '0', $customer);
        [$first, $second] = Sale::query()->orderBy('id')->get()->all();

        $receipt = app(CustomerDueService::class)->collect($this->user->branch, $customer, 1200, $this->cash);

        $this->assertStringContainsString('-MR-', $receipt->receipt_no);
        $this->assertSame(PaymentStatus::Paid, $first->fresh()->payment_status);
        $this->assertSame('300.00', $second->fresh()->due_amount);
        $this->assertSame(PaymentStatus::Partial, $second->fresh()->payment_status);
        $this->assertCount(2, $receipt->allocations);
        $this->assertSame(300.0, $customer->totalDue());
    }

    public function test_collection_cannot_be_more_than_the_due(): void
    {
        $customer = Customer::factory()->create();
        $this->sell(1, '0', $customer);

        $this->expectException(ValidationException::class);
        app(CustomerDueService::class)->collect($this->user->branch, $customer, 600, $this->cash);
    }

    public function test_collect_due_from_customer_page_and_invoice_page(): void
    {
        $customer = Customer::factory()->create();
        $this->sell(2, '0', $customer);
        $sale = Sale::query()->sole();

        Livewire::test(CustomerShow::class, ['customer' => $customer])
            ->call('openCollect')
            ->assertSet('collectAmount', '1000')
            ->set('collectAmount', '250')
            ->call('collect')
            ->assertHasNoErrors()
            ->assertDispatched('print-due-receipt');

        Livewire::test(SaleShow::class, ['sale' => $sale->fresh()])
            ->assertSet('collect_amount', '750')
            ->set('collect_amount', '750')
            ->call('collect')
            ->assertHasNoErrors();

        $this->assertSame(PaymentStatus::Paid, $sale->fresh()->payment_status);
        $this->assertSame(2, CustomerPayment::query()->count());

        $this->get(route('due-receipts.show', CustomerPayment::query()->first()))->assertOk()->assertSee('MONEY RECEIPT');
        $this->get(route('sales.receipt', $sale))->assertOk();
    }

    public function test_collect_link_opens_the_form_and_menu_shows_due_collection(): void
    {
        $customer = Customer::factory()->create(['name' => 'Karim Electronics']);
        $this->sell(1, '0', $customer);

        Livewire::withQueryParams(['collect' => 1])->test(CustomerShow::class, ['customer' => $customer])
            ->assertSet('showCollect', true)
            ->assertSet('collectAmount', '500');

        $this->get(route('customers.index', ['dueFilter' => 'due']))->assertOk()
            ->assertSee('Due collection')
            ->assertSee(route('customers.show', ['customer' => $customer, 'collect' => 1]), false);
        $this->get(route('pos'))->assertOk()->assertSee('Full screen');
    }

    public function test_return_on_due_sale_reduces_due_before_refunding(): void
    {
        $customer = Customer::factory()->create();
        $this->sell(2, '400', $customer);
        $sale = Sale::query()->sole();

        Livewire::test(SaleShow::class, ['sale' => $sale])
            ->set('showReturn', true)
            ->set("returns.{$sale->items->first()->id}.quantity", '1')
            ->call('saveReturn')
            ->assertHasNoErrors();

        $return = $sale->returns()->sole();
        $this->assertSame('500.00', $return->total);
        $this->assertSame('0.00', $return->refund_amount);
        $this->assertSame('100.00', $sale->fresh()->due_amount);
    }

    public function test_voiding_a_due_sale_clears_the_due(): void
    {
        $customer = Customer::factory()->create();
        $this->sell(2, '400', $customer);
        $sale = Sale::query()->sole();

        Livewire::test(SaleShow::class, ['sale' => $sale])->set('void_reason', 'Wrong item')->call('void')->assertHasNoErrors();

        $this->assertSame('0.00', $sale->fresh()->due_amount);
        $this->assertSame(0.0, $customer->totalDue());
    }

    public function test_lists_show_due(): void
    {
        $customer = Customer::factory()->create(['name' => 'Karim Electronics']);
        $this->sell(1, '0', $customer);
        $this->sell(1, '500');

        Livewire::test(SaleIndex::class)->set('status', 'due')->assertViewHas('count', 1);
        Livewire::test(CustomerIndex::class)->set('dueFilter', 'due')->assertSee('Karim Electronics');
        Livewire::test(CustomerIndex::class)->call('edit', $customer->id)->set('credit_limit', '20000')->call('save')->assertHasNoErrors();

        $this->assertSame('20000.00', $customer->fresh()->credit_limit);
    }

    public function test_customer_due_report_in_every_detail_level(): void
    {
        $customer = Customer::factory()->create(['name' => 'Hasan Mobile']);
        $this->sell(2, '300', $customer);
        $this->sell(1, '0', $customer);
        app(CustomerDueService::class)->collect($this->user->branch, $customer, 200, $this->cash);

        $documents = app(ReportDocument::class);
        $summary = $documents->build('customer-dues', $this->user->branch_id, today(), today());
        $this->assertSame(1000.0, $summary['totals']['due']);
        $this->assertSame(1000.0, $summary['rows'][0]['age_30']);

        $invoices = $documents->build('customer-dues', $this->user->branch_id, today(), today(), ['due_detail' => 'items', 'customer_id' => $customer->id]);
        $this->assertSame('Customer due statement', $invoices['title']);
        $this->assertSame(['group', null, 'item', null, 'item', 'subtotal'], array_map(fn (array $row) => $row['_style'] ?? null, $invoices['rows']));

        $collections = $documents->build('due-collections', $this->user->branch_id, today(), today());
        $this->assertSame(200.0, $collections['totals']['amount']);

        foreach (['summary', 'invoices', 'items'] as $detail) {
            Livewire::test(ReportIndex::class)->set('tab', 'customer-dues')->set('dueDetail', $detail)->assertSee('Hasan Mobile');

            foreach (['pdf', 'xlsx', 'csv'] as $format) {
                $this->get(route('reports.export', ['tab' => 'customer-dues', 'format' => $format, 'detail' => $detail, 'customer' => $customer->id, 'from' => today()->toDateString(), 'to' => today()->toDateString()]))->assertOk();
            }
        }
    }
}
