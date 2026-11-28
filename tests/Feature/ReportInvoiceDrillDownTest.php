<?php

namespace Tests\Feature;

use App\Enums\PaymentMethodType;
use App\Enums\StockMovementType;
use App\Livewire\Reports\ReportIndex;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Referrer;
use App\Models\Sale;
use App\Services\CashShiftService;
use App\Services\ReportDocument;
use App\Services\SaleService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportInvoiceDrillDownTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private PaymentMethod $cash;

    private Product $product;

    private Referrer $karim;

    private Customer $customer;

    /** @var list<Sale> */
    private array $sales = [];

    /**
     * Three invoices today: one with reference Karim, one on credit to a customer, one walk-in.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create(['code' => 'B1']);
        $this->actingAsRole('admin', $this->branch);
        $this->cash = PaymentMethod::factory()->create(['name' => 'Cash', 'type' => PaymentMethodType::Cash]);
        app(CashShiftService::class)->open($this->branch, 0);

        $this->product = $this->makeProduct(['name' => 'Cement Bag', 'cost_price' => 450], piecePrice: 500);
        app(StockService::class)->move($this->branch, $this->product, 100, StockMovementType::Opening);
        $this->karim = Referrer::factory()->create(['name' => 'Karim Mistri']);
        $this->customer = Customer::factory()->create(['name' => 'Jamal Builder']);

        $this->sales[] = $this->sell(2, 1000, referrerId: $this->karim->id);
        $this->sales[] = $this->sell(3, 500, customerId: $this->customer->id);
        $this->sales[] = $this->sell(1, 500);
    }

    private function sell(int $quantity, float $paid, ?int $customerId = null, ?int $referrerId = null): Sale
    {
        $unit = $this->product->units->firstWhere('is_base', true);

        return app(SaleService::class)->complete($this->branch, [['product_unit_id' => $unit->id, 'quantity' => $quantity, 'unit_price' => 500.0]],
            [['payment_method_id' => $this->cash->id, 'amount' => $paid]], customerId: $customerId, dueDate: $customerId ? today()->addWeek()->toDateString() : null, referrerId: $referrerId);
    }

    public function test_invoice_counts_carry_links_to_their_invoices(): void
    {
        $documents = app(ReportDocument::class);
        $daily = $documents->build('daily', $this->branch->id, today(), today());
        $referrers = $documents->build('referrers', $this->branch->id, today(), today());
        $register = $documents->build('sales-register', $this->branch->id, today(), today());

        $this->assertSame(['day' => today()->toDateString()], array_diff_key($daily['rows'][0]['_links']['invoices'], ['title' => 1]));
        $this->assertSame(['referrer' => $this->karim->id], array_diff_key($referrers['rows'][0]['_links']['invoices'], ['title' => 1]));
        $this->assertSame(['referrer' => null], array_diff_key($referrers['rows'][1]['_links']['invoices'], ['title' => 1]));
        $this->assertSame(['sale' => $this->sales[0]->id], $register['rows'][0]['_links']['invoice']);

        $this->get(route('reports.index', ['tab' => 'daily']))->assertOk()->assertSee('class="rpt-link"', false);
    }

    public function test_clicking_an_invoice_count_lists_those_invoices(): void
    {
        Livewire::test(ReportIndex::class)
            ->set('tab', 'referrers')
            ->call('openInvoices', ['title' => 'Invoices with reference Karim Mistri', 'referrer' => $this->karim->id])
            ->assertSet('showInvoiceList', true)
            ->assertViewHas('invoices', fn (array $invoices): bool => $invoices['count'] === 1 && $invoices['rows']->sole()->is($this->sales[0]) && $invoices['total'] == 1000)
            ->call('openInvoices', ['title' => 'Invoices without reference', 'referrer' => null])
            ->assertViewHas('invoices', fn (array $invoices): bool => $invoices['count'] === 2);

        Livewire::test(ReportIndex::class)
            ->set('tab', 'summary')
            ->call('openInvoices', ['title' => 'Invoices'])
            ->assertViewHas('invoices', fn (array $invoices): bool => $invoices['count'] === 3)
            ->assertSee($this->sales[2]->invoice_no);
    }

    public function test_due_invoices_ignore_the_dates_and_follow_the_customer(): void
    {
        Livewire::test(ReportIndex::class)
            ->set('tab', 'customer-dues')
            ->set('from', today()->addMonth()->toDateString())
            ->set('to', today()->addMonth()->toDateString())
            ->call('openInvoices', ['title' => 'Unpaid invoices of Jamal Builder', 'due' => true, 'customer' => $this->customer->id])
            ->assertViewHas('invoices', fn (array $invoices): bool => $invoices['count'] === 1 && $invoices['due'] == 1000 && $invoices['period'] === null);
    }

    public function test_an_invoice_opens_in_a_pop_up(): void
    {
        Livewire::test(ReportIndex::class)
            ->call('viewInvoice', $this->sales[1]->id)
            ->assertSet('showInvoice', true)
            ->assertViewHas('viewingSale', fn (?Sale $sale): bool => $sale?->is($this->sales[1]) ?? false)
            ->assertSee('Jamal Builder')
            ->assertSee('Cement Bag');
    }

    public function test_invoices_of_another_branch_are_not_shown(): void
    {
        $other = Sale::factory()->create(['branch_id' => Branch::factory()->create()->id]);

        Livewire::test(ReportIndex::class)
            ->call('viewInvoice', $other->id)
            ->assertNotFound();
    }
}
