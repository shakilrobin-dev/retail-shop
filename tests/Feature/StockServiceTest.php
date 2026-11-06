<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Branch;
use App\Models\Product;
use App\Services\Settings;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_adding_and_removing_stock_updates_balance_and_writes_movements(): void
    {
        $branch = Branch::factory()->create();
        $product = Product::factory()->create();
        $stock = app(StockService::class);

        $stock->move($branch, $product, 50, StockMovementType::Purchase);
        $movement = $stock->move($branch, $product, -12, StockMovementType::Sale);

        $this->assertSame(38.0, $stock->quantity($branch, $product));
        $this->assertSame('-12.000', $movement->quantity);
        $this->assertSame('38.000', $movement->balance_after);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_stock_is_kept_separately_per_branch(): void
    {
        $mainBranch = Branch::factory()->create();
        $secondBranch = Branch::factory()->create();
        $product = Product::factory()->create();
        $stock = app(StockService::class);

        $stock->move($mainBranch, $product, 30, StockMovementType::Purchase);
        $stock->move($secondBranch, $product, 5, StockMovementType::Purchase);

        $this->assertSame(30.0, $stock->quantity($mainBranch, $product));
        $this->assertSame(5.0, $stock->quantity($secondBranch, $product));
        $this->assertSame(35.0, $stock->totalQuantity($product));
    }

    public function test_selling_more_than_available_is_blocked_by_default(): void
    {
        $branch = Branch::factory()->create();
        $product = Product::factory()->create();
        $stock = app(StockService::class);
        $stock->move($branch, $product, 3, StockMovementType::Purchase);

        try {
            $stock->move($branch, $product, -5, StockMovementType::Sale);
            $this->fail('Expected InsufficientStockException.');
        } catch (InsufficientStockException $exception) {
            $this->assertSame(3.0, $exception->available);
        }

        $this->assertSame(3.0, $stock->quantity($branch, $product));
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_negative_stock_is_allowed_when_setting_is_on(): void
    {
        $branch = Branch::factory()->create();
        $product = Product::factory()->create();
        app(Settings::class)->set('pos_allow_negative_stock', true);
        $stock = app(StockService::class);

        $stock->move($branch, $product, -2, StockMovementType::Sale);

        $this->assertSame(-2.0, $stock->quantity($branch, $product));
    }

    public function test_purchase_updates_average_cost(): void
    {
        $branch = Branch::factory()->create();
        $product = Product::factory()->create(['cost_price' => 0]);
        $stock = app(StockService::class);

        $stock->updateAverageCost($product, 10, 100);
        $stock->move($branch, $product, 10, StockMovementType::Purchase);

        $stock->updateAverageCost($product, 10, 120);
        $stock->move($branch, $product, 10, StockMovementType::Purchase);

        $this->assertSame('110.00', $product->fresh()->cost_price);
    }
}
