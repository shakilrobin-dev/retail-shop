<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\BranchSwitchController;
use App\Http\Controllers\LabelPrintController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\RepairPrintController;
use App\Http\Controllers\ReportExportController;
use App\Livewire\Auth\Login;
use App\Livewire\Customers\CustomerIndex;
use App\Livewire\Customers\CustomerShow;
use App\Livewire\Dashboard;
use App\Livewire\Expenses\ExpenseIndex;
use App\Livewire\Pos\CashRegister;
use App\Livewire\Pos\PointOfSale;
use App\Livewire\Products\CatalogSetup;
use App\Livewire\Products\LabelPrint;
use App\Livewire\Products\ProductForm;
use App\Livewire\Products\ProductIndex;
use App\Livewire\Purchases\PurchaseForm;
use App\Livewire\Purchases\PurchaseIndex;
use App\Livewire\Purchases\PurchaseShow;
use App\Livewire\Repairs\RepairForm;
use App\Livewire\Repairs\RepairIndex;
use App\Livewire\Repairs\RepairShow;
use App\Livewire\Reports\ReportIndex;
use App\Livewire\Sales\SaleIndex;
use App\Livewire\Sales\SaleShow;
use App\Livewire\Settings\ActivityLog;
use App\Livewire\Settings\BackupManager;
use App\Livewire\Settings\BranchManager;
use App\Livewire\Settings\GeneralSettings;
use App\Livewire\Settings\PaymentMethodManager;
use App\Livewire\Settings\RolePermissions;
use App\Livewire\Settings\UserManager;
use App\Livewire\Stock\StockAdjustmentForm;
use App\Livewire\Stock\StockMovements;
use App\Livewire\Stock\StockOverview;
use App\Livewire\Stock\StockTransferForm;
use App\Livewire\Stock\StockTransferIndex;
use App\Livewire\Stock\StockTransferShow;
use App\Livewire\Suppliers\SupplierIndex;
use Illuminate\Support\Facades\Route;

Route::post('/locale', LocaleController::class)->name('locale.switch');

Route::middleware('guest')->group(function () {
    Route::livewire('/login', Login::class)->name('login');
});

Route::middleware('auth')->group(function () {
    Route::livewire('/', Dashboard::class)->name('dashboard');
    Route::post('/logout', LogoutController::class)->name('logout');
    Route::post('/branch', BranchSwitchController::class)->name('branch.switch');

    Route::middleware('can:pos.sell')->group(function () {
        Route::livewire('/pos', PointOfSale::class)->name('pos');
        Route::get('/pos/drawer', [ReceiptController::class, 'drawer'])->middleware('can:pos.open-drawer')->name('pos.drawer');
        Route::livewire('/sales', SaleIndex::class)->name('sales.index');
        Route::livewire('/sales/{sale}', SaleShow::class)->name('sales.show');
        Route::get('/sales/{sale}/receipt', [ReceiptController::class, 'show'])->name('sales.receipt');
        Route::livewire('/customers', CustomerIndex::class)->name('customers.index');
        Route::livewire('/customers/{customer}', CustomerShow::class)->name('customers.show');
    });

    Route::middleware('can:repairs.manage')->group(function () {
        Route::livewire('/repairs', RepairIndex::class)->name('repairs.index');
        Route::livewire('/repairs/create', RepairForm::class)->name('repairs.create');
        Route::livewire('/repairs/{job}', RepairShow::class)->name('repairs.show');
        Route::get('/repairs/{job}/slip', [RepairPrintController::class, 'slip'])->name('repairs.slip');
        Route::get('/repairs/{job}/bill', [RepairPrintController::class, 'bill'])->name('repairs.bill');
    });

    Route::livewire('/expenses', ExpenseIndex::class)->middleware('can:expenses.manage')->name('expenses.index');

    Route::middleware('can:reports.view')->group(function () {
        Route::livewire('/reports', ReportIndex::class)->name('reports.index');
        Route::get('/reports/export', ReportExportController::class)->name('reports.export');
    });

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::livewire('/', GeneralSettings::class)->middleware('can:settings.manage')->name('general');
        Route::livewire('/branches', BranchManager::class)->middleware('can:branches.manage')->name('branches');
        Route::livewire('/users', UserManager::class)->middleware('can:users.manage')->name('users');
        Route::livewire('/permissions', RolePermissions::class)->middleware('can:users.manage')->name('roles');
        Route::livewire('/payment-methods', PaymentMethodManager::class)->middleware('can:settings.manage')->name('payment-methods');
        Route::livewire('/backup', BackupManager::class)->middleware('can:settings.manage')->name('backup');
        Route::livewire('/audit-log', ActivityLog::class)->middleware('can:users.manage')->name('activity');
    });

    Route::livewire('/cash-register', CashRegister::class)->middleware('can:cash-shifts.manage')->name('cash-register');

    Route::middleware('can:products.manage')->group(function () {
        Route::livewire('/products', ProductIndex::class)->name('products.index');
        Route::livewire('/products/create', ProductForm::class)->name('products.create');
        Route::livewire('/products/{product}/edit', ProductForm::class)->name('products.edit');
        Route::livewire('/catalog-setup', CatalogSetup::class)->name('catalog.setup');
        Route::livewire('/labels', LabelPrint::class)->name('labels.index');
        Route::get('/labels/print', LabelPrintController::class)->name('labels.print');
    });

    Route::middleware('can:stock.adjust')->group(function () {
        Route::livewire('/stock', StockOverview::class)->name('stock.index');
        Route::livewire('/stock/history', StockMovements::class)->name('stock.movements');
        Route::livewire('/stock/adjust', StockAdjustmentForm::class)->name('stock.adjust');
    });

    Route::middleware('can:stock.transfer')->group(function () {
        Route::livewire('/stock/transfers', StockTransferIndex::class)->name('stock.transfers.index');
        Route::livewire('/stock/transfers/create', StockTransferForm::class)->name('stock.transfers.create');
        Route::livewire('/stock/transfers/{transfer}', StockTransferShow::class)->name('stock.transfers.show');
    });

    Route::middleware('can:purchases.manage')->group(function () {
        Route::livewire('/suppliers', SupplierIndex::class)->name('suppliers.index');
        Route::livewire('/purchases', PurchaseIndex::class)->name('purchases.index');
        Route::livewire('/purchases/create', PurchaseForm::class)->name('purchases.create');
        Route::livewire('/purchases/{purchase}', PurchaseShow::class)->name('purchases.show');
    });
});
