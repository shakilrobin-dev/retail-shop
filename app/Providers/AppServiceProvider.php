<?php

namespace App\Providers;

use App\Models\Expense;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\RepairJob;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\StockAdjustment;
use App\Models\StockTransfer;
use App\Services\CurrentBranch;
use App\Services\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(Settings::class);
        $this->app->scoped(CurrentBranch::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        /** Short names stored in payments.payable_type and stock_movements.reference_type. */
        Relation::morphMap([
            'sale' => Sale::class,
            'sale_return' => SaleReturn::class,
            'purchase' => Purchase::class,
            'purchase_return' => PurchaseReturn::class,
            'repair_job' => RepairJob::class,
            'stock_adjustment' => StockAdjustment::class,
            'stock_transfer' => StockTransfer::class,
            'expense' => Expense::class,
        ]);
    }
}
