<?php

namespace Tests\Feature;

use App\Enums\PaymentMethodType;
use App\Enums\StockMovementType;
use App\Livewire\Expenses\ExpenseIndex;
use App\Livewire\Pos\PointOfSale;
use App\Livewire\Reports\ReportIndex;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Services\CashShiftService;
use App\Services\ReportDocument;
use App\Services\ReportService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportAndExpenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_from_drawer_reduces_expected_cash(): void
    {
        $user = $this->actingAsRole('admin');
        PaymentMethod::factory()->create(['type' => PaymentMethodType::Cash]);
        $shift = app(CashShiftService::class)->open($user->branch, 1000);
        $category = ExpenseCategory::factory()->create(['name' => 'Tea']);

        Livewire::test(ExpenseIndex::class)
            ->set('expense_category_id', $category->id)
            ->set('amount', '150')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Tea');

        $this->assertSame(850.0, app(CashShiftService::class)->summary($shift)['expected']);
    }

    public function test_profit_report_counts_sales_cost_and_expenses(): void
    {
        $user = $this->actingAsRole('admin');
        PaymentMethod::factory()->create(['type' => PaymentMethodType::Cash]);
        app(CashShiftService::class)->open($user->branch, 0);
        $product = $this->makeProduct(['name' => 'Charger', 'cost_price' => 300], piecePrice: 500, pieceBarcode: 'CHG');
        app(StockService::class)->move($user->branch, $product, 10, StockMovementType::Opening);

        Livewire::test(PointOfSale::class)
            ->set('productSearch', 'CHG')->call('scan')
            ->set('cart.0.quantity', '2')
            ->call('openPayment')->call('payExact', 0)->call('complete')
            ->assertHasNoErrors();

        Livewire::test(ExpenseIndex::class)
            ->set('expense_category_id', ExpenseCategory::factory()->create()->id)
            ->set('amount', '100')
            ->call('save');

        $summary = app(ReportService::class)->summary($user->branch_id, today(), today());

        $this->assertSame(1000.0, $summary['net_sales']);
        $this->assertSame(600.0, $summary['cost_of_goods']);
        $this->assertSame(400.0, $summary['sales_profit']);
        $this->assertSame(300.0, $summary['net_profit']);

        $top = app(ReportService::class)->topProducts($user->branch_id, today(), today());
        $this->assertSame('Charger', $top->first()->name);
    }

    public function test_every_report_tab_renders_and_exports(): void
    {
        $this->actingAsRole('admin');

        foreach (array_keys(ReportDocument::REPORTS) as $tab) {
            Livewire::test(ReportIndex::class)->set('tab', $tab)->assertOk();

            $this->get(route('reports.export', ['tab' => $tab, 'from' => today()->toDateString(), 'to' => today()->toDateString()]))
                ->assertOk()
                ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        }

        $this->get(route('expenses.index'))->assertOk();
        $this->get(route('reports.index'))->assertOk();
    }

    public function test_cashier_cannot_see_reports(): void
    {
        $this->actingAsRole('cashier');

        $this->get(route('reports.index'))->assertForbidden();
    }
}
