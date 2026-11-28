<?php

namespace Tests\Feature;

use App\Enums\PaymentMethodType;
use App\Enums\StockMovementType;
use App\Livewire\Reports\ReportIndex;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Referrer;
use App\Services\CashShiftService;
use App\Services\ReportDocument;
use App\Services\SaleService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportFiltersTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private PaymentMethod $cash;

    /** @var array<string, Category> */
    private array $categories = [];

    /** @var array<string, Product> */
    private array $products = [];

    /** @var array<string, Referrer> */
    private array $referrers = [];

    /**
     * Tiles › Floor Tiles / Wall Tiles and Cement. Karim sells floor tiles and cement, Rahim wall tiles,
     * and a walk-in customer buys cement.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create(['code' => 'B1']);
        $this->actingAsRole('admin', $this->branch);
        $this->cash = PaymentMethod::factory()->create(['name' => 'Cash', 'type' => PaymentMethodType::Cash]);
        app(CashShiftService::class)->open($this->branch, 0);

        $tiles = Category::factory()->create(['name' => 'Tiles', 'parent_id' => null]);
        $this->categories = [
            'tiles' => $tiles,
            'floor' => Category::factory()->create(['name' => 'Floor Tiles', 'parent_id' => $tiles->id]),
            'wall' => Category::factory()->create(['name' => 'Wall Tiles', 'parent_id' => $tiles->id]),
            'cement' => Category::factory()->create(['name' => 'Cement', 'parent_id' => null]),
        ];
        $shah = Brand::query()->create(['name' => 'Shah']);

        $this->products = [
            'floor' => $this->makeProduct(['name' => 'Floor Tile', 'category_id' => $this->categories['floor']->id, 'cost_price' => 80], piecePrice: 100),
            'wall' => $this->makeProduct(['name' => 'Wall Tile', 'category_id' => $this->categories['wall']->id, 'cost_price' => 40], piecePrice: 50),
            'cement' => $this->makeProduct(['name' => 'Cement Bag', 'category_id' => $this->categories['cement']->id, 'brand_id' => $shah->id, 'cost_price' => 450], piecePrice: 500),
        ];

        foreach ($this->products as $product) {
            app(StockService::class)->move($this->branch, $product, 100, StockMovementType::Opening);
        }

        $this->referrers = [
            'karim' => Referrer::factory()->create(['name' => 'Karim Mistri']),
            'rahim' => Referrer::factory()->create(['name' => 'Rahim Contractor']),
        ];

        $this->sell(['floor' => 10, 'cement' => 2], $this->referrers['karim']->id);
        $this->sell(['wall' => 4], $this->referrers['rahim']->id);
        $this->sell(['cement' => 1]);
    }

    /**
     * @param  array<string, int>  $lines  product key => pieces
     */
    private function sell(array $lines, ?int $referrerId = null): void
    {
        $cart = [];
        $total = 0;

        foreach ($lines as $key => $quantity) {
            $unit = $this->products[$key]->units->firstWhere('is_base', true);
            $cart[] = ['product_unit_id' => $unit->id, 'quantity' => $quantity, 'unit_price' => (float) $unit->selling_price];
            $total += $quantity * (float) $unit->selling_price;
        }

        app(SaleService::class)->complete($this->branch, $cart, [['payment_method_id' => $this->cash->id, 'amount' => $total]], referrerId: $referrerId);
    }

    /**
     * @param  array<string, list<int>>  $filters
     * @return array<string, mixed>
     */
    private function report(string $key, array $filters): array
    {
        return app(ReportDocument::class)->build($key, $this->branch->id, today(), today(), ['filters' => $filters]);
    }

    public function test_main_category_includes_its_sub_categories(): void
    {
        $document = $this->report('products', ['categories' => [$this->categories['tiles']->id]]);

        $this->assertEqualsCanonicalizing(['Floor Tile', 'Wall Tile'], array_column($document['rows'], 'name'));
        $this->assertEquals(1200, $document['totals']['revenue']);
        $this->assertSame(['Category: Tiles'], $document['filters']);
    }

    public function test_a_sub_category_narrows_its_main_category_only(): void
    {
        $document = $this->report('products', [
            'categories' => [$this->categories['tiles']->id, $this->categories['cement']->id],
            'subcategories' => [$this->categories['floor']->id],
        ]);

        $this->assertEqualsCanonicalizing(['Floor Tile', 'Cement Bag'], array_column($document['rows'], 'name'));
    }

    public function test_reference_and_product_filters_combine(): void
    {
        $document = $this->report('referrers', ['referrers' => [$this->referrers['karim']->id], 'categories' => [$this->categories['tiles']->id]]);

        $this->assertCount(1, $document['rows']);
        $this->assertStringContainsString('Karim Mistri', $document['rows'][0]['name']);
        $this->assertEquals(1000, $document['rows'][0]['sales']);
        $this->assertEquals(200, $document['rows'][0]['profit']);
        $this->assertContains('Only the chosen products are counted: item totals before invoice discount and VAT.', $document['notes']);

        $register = $this->report('sales-register', ['brands' => [Brand::query()->sole()->id]]);
        $this->assertCount(2, $register['rows']);
    }

    public function test_stock_reports_use_product_filters_and_unknown_filters_are_ignored(): void
    {
        $document = $this->report('stock-valuation', ['subcategories' => [$this->categories['wall']->id], 'referrers' => [$this->referrers['karim']->id]]);

        $this->assertSame(['Wall Tile'], array_column($document['rows'], 'name'));
        $this->assertSame(['Sub-category: Tiles › Wall Tiles'], $document['filters']);
    }

    public function test_filters_on_the_reports_page_and_in_the_export(): void
    {
        Livewire::test(ReportIndex::class)
            ->set('tab', 'products')
            ->assertSee('Sub-category')
            ->assertSee('Reference')
            ->set('filters.referrers', [(string) $this->referrers['rahim']->id])
            ->assertSee('Filtered by: Reference: Rahim Contractor')
            ->assertViewHas('document', fn (array $document): bool => array_column($document['rows'], 'name') === ['Wall Tile'])
            ->call('clearFilters')
            ->assertViewHas('document', fn (array $document): bool => count($document['rows']) === 3 && $document['filters'] === []);

        $csv = $this->get(route('reports.export', [
            'tab' => 'products', 'format' => 'csv', 'from' => today()->toDateString(), 'to' => today()->toDateString(),
            'f' => ['products' => [$this->products['cement']->id]],
        ]))->assertOk()->streamedContent();

        $this->assertStringContainsString('Filtered by: Product: Cement Bag', $csv);
        $this->assertStringContainsString('Cement Bag', $csv);
        $this->assertStringNotContainsString('Floor Tile', $csv);
    }

    public function test_sub_category_choices_follow_the_chosen_category(): void
    {
        Livewire::test(ReportIndex::class)
            ->set('tab', 'products')
            ->set('filters.categories', [$this->categories['cement']->id])
            ->assertViewHas('filterOptions', fn (array $options): bool => $options['subcategories']['options'] === []
                && array_column($options['products']['options'], 'label') === ['Cement Bag']);
    }
}
