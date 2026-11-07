<?php

namespace Tests\Feature;

use App\Enums\PaymentMethodType;
use App\Enums\StockMovementType;
use App\Livewire\Pos\PointOfSale;
use App\Livewire\Reports\ReportIndex;
use App\Models\PaymentMethod;
use App\Services\CashShiftService;
use App\Services\ReportDocument;
use App\Services\ReportExporter;
use App\Services\Settings;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ReportDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function sellOneCharger(): void
    {
        $user = auth()->user();
        PaymentMethod::factory()->create(['type' => PaymentMethodType::Cash]);
        app(CashShiftService::class)->open($user->branch, 0);
        $product = $this->makeProduct(['name' => 'Charger', 'cost_price' => 300], piecePrice: 500, pieceBarcode: 'CHG');
        app(StockService::class)->move($user->branch, $product, 10, StockMovementType::Opening);

        Livewire::test(PointOfSale::class)
            ->set('productSearch', 'CHG')->call('scan')
            ->call('openPayment')->call('payExact', 0)->call('complete')
            ->assertHasNoErrors();
    }

    public function test_report_screen_shows_letterhead_and_signatures(): void
    {
        $this->actingAsRole('admin');
        app(Settings::class)->set('shop_name', 'Robin Electronics');
        app(Settings::class)->set('vat_bin', '000123456-0101');
        $this->sellOneCharger();

        Livewire::test(ReportIndex::class)
            ->set('tab', 'sales-register')
            ->assertSee('Robin Electronics')
            ->assertSee('000123456-0101')
            ->assertSee('Sales register')
            ->assertSee('Approved by')
            ->assertSee('Walk-in')
            ->assertSee('Total (completed only)');
    }

    public function test_sales_register_totals_completed_invoices(): void
    {
        $user = $this->actingAsRole('admin');
        $this->sellOneCharger();

        $document = app(ReportDocument::class)->build('sales-register', $user->branch_id, today(), today());

        $this->assertCount(1, $document['rows']);
        $this->assertSame(500.0, $document['totals']['total']);
        $this->assertStringStartsWith('RPT-', $document['reference']);
    }

    public function test_stock_valuation_values_stock_at_cost_and_price(): void
    {
        $user = $this->actingAsRole('admin');
        $product = $this->makeProduct(['name' => 'Cable', 'cost_price' => 80], piecePrice: 150, pieceBarcode: 'CBL');
        app(StockService::class)->move($user->branch, $product, 5, StockMovementType::Opening);

        $document = app(ReportDocument::class)->build('stock-valuation', $user->branch_id, today(), today());

        $this->assertSame(400.0, $document['totals']['cost_value']);
        $this->assertSame(750.0, $document['totals']['retail_value']);
    }

    public function test_every_report_downloads_as_pdf_and_excel(): void
    {
        $this->actingAsRole('admin');
        $this->sellOneCharger();
        $params = ['from' => today()->toDateString(), 'to' => today()->toDateString()];

        foreach (array_keys(ReportDocument::REPORTS) as $tab) {
            $pdf = $this->get(route('reports.export', $params + ['tab' => $tab, 'format' => 'pdf']));
            $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $pdf->getContent());

            $this->get(route('reports.export', $params + ['tab' => $tab, 'format' => 'xlsx']))
                ->assertOk()
                ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }
    }

    public function test_excel_file_has_letterhead_title_and_totals(): void
    {
        $user = $this->actingAsRole('admin');
        app(Settings::class)->set('shop_name', 'Robin Electronics');
        $this->sellOneCharger();

        $document = app(ReportDocument::class)->build('sales-register', $user->branch_id, today(), today());
        $path = tempnam(sys_get_temp_dir(), 'rpt').'.xlsx';
        (new Xlsx(app(ReportExporter::class)->spreadsheet($document)))->save($path);
        $sheet = IOFactory::load($path)->getActiveSheet();
        $values = collect($sheet->toArray())->flatten()->filter()->values();
        unlink($path);

        $this->assertSame('Robin Electronics', $sheet->getCell('A1')->getValue());
        $this->assertContains('SALES REGISTER', $values);
        $this->assertContains('Total (completed only)', $values);
        $this->assertStringContainsString('&P', $sheet->getHeaderFooter()->getOddFooter());
    }

    public function test_pdf_renders_bangla(): void
    {
        $user = $this->actingAsRole('admin');
        app()->setLocale('bn');
        app(Settings::class)->set('shop_name_bn', 'রবিন ইলেকট্রনিক্স');

        $document = app(ReportDocument::class)->build('summary', $user->branch_id, today(), today());
        $pdf = app(ReportExporter::class)->pdf($document);

        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertSame('রবিন ইলেকট্রনিক্স', $document['letterhead']['name']);
    }

    public function test_cashier_cannot_download_reports(): void
    {
        $this->actingAsRole('cashier');

        $this->get(route('reports.export', ['tab' => 'summary', 'format' => 'pdf', 'from' => today()->toDateString(), 'to' => today()->toDateString()]))
            ->assertForbidden();
    }
}
