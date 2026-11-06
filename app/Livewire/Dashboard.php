<?php

namespace App\Livewire;

use App\Enums\PaymentDirection;
use App\Enums\RepairStatus;
use App\Enums\SaleStatus;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\RepairJob;
use App\Models\Sale;
use App\Services\CurrentBranch;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Dashboard extends Component
{
    public function render(): View
    {
        $branchId = app(CurrentBranch::class)->get()?->id;
        $todaySales = Sale::query()
            ->where('branch_id', $branchId)
            ->where('status', SaleStatus::Completed)
            ->whereDate('sold_at', today());

        $openRepairStatuses = array_map(
            fn (RepairStatus $status): string => $status->value,
            [RepairStatus::Received, RepairStatus::Diagnosing, RepairStatus::WaitingForParts, RepairStatus::Repairing],
        );

        return view('livewire.dashboard', [
            'todaySalesTotal' => (float) (clone $todaySales)->sum('total'),
            'todayInvoiceCount' => (clone $todaySales)->count(),
            'todayRepairIncome' => (float) Payment::query()
                ->where('branch_id', $branchId)
                ->where('payable_type', 'repair_job')
                ->where('direction', PaymentDirection::In)
                ->whereDate('paid_at', today())
                ->sum('amount'),
            'pendingRepairCount' => RepairJob::query()
                ->where('branch_id', $branchId)
                ->whereIn('status', $openRepairStatuses)
                ->count(),
            'readyRepairCount' => RepairJob::query()
                ->where('branch_id', $branchId)
                ->where('status', RepairStatus::Ready)
                ->count(),
            'productCount' => Product::query()->where('is_active', true)->count(),
            'lowStockCount' => ProductStock::query()
                ->join('products', 'products.id', '=', 'product_stocks.product_id')
                ->where('product_stocks.branch_id', $branchId)
                ->where('products.is_active', true)
                ->where('products.reorder_level', '>', 0)
                ->where('product_stocks.quantity', '<=', DB::raw('products.reorder_level'))
                ->count(),
            'recentSales' => Sale::query()->with('customer')
                ->where('branch_id', $branchId)
                ->where('status', SaleStatus::Completed)
                ->latest('sold_at')->limit(8)->get(),
            'lowStock' => ProductStock::query()->with('product')
                ->join('products', 'products.id', '=', 'product_stocks.product_id')
                ->where('product_stocks.branch_id', $branchId)
                ->where('products.is_active', true)
                ->where('products.reorder_level', '>', 0)
                ->where('product_stocks.quantity', '<=', DB::raw('products.reorder_level'))
                ->orderBy('product_stocks.quantity')
                ->select('product_stocks.*')
                ->limit(8)->get(),
        ])->title(__('Dashboard'));
    }
}
