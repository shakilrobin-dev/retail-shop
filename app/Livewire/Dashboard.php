<?php

namespace App\Livewire;

use App\Enums\PaymentDirection;
use App\Enums\RepairStatus;
use App\Enums\SaleStatus;
use App\Models\CashShift;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\RepairJob;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use App\Services\CurrentBranch;
use App\Services\Settings;
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

        $days = collect(range(13, 0))->push(0)->map(fn (int $daysAgo) => today()->subDays($daysAgo));
        $salesByDay = Sale::query()
            ->where('branch_id', $branchId)
            ->where('status', SaleStatus::Completed)
            ->where('sold_at', '>=', $days->first()->copy()->startOfDay())
            ->selectRaw('DATE(sold_at) as day, SUM(total) as total, COUNT(*) as invoices')
            ->groupBy('day')
            ->get()
            ->keyBy(fn ($row) => (string) $row->day);
        $chart = $days->map(fn ($day) => [
            'date' => $day,
            'total' => (float) ($salesByDay[$day->toDateString()]->total ?? 0),
            'invoices' => (int) ($salesByDay[$day->toDateString()]->invoices ?? 0),
        ]);

        $settings = app(Settings::class);
        $setupSteps = [
            [__('Set your shop name and logo'), route('settings.general', ['tab' => 'shop']), $settings->get('shop_name') !== config('shop.defaults.shop_name'), 'settings.manage'],
            [__('Add branch address and phone (shown on receipts)'), route('settings.branches'), (bool) app(CurrentBranch::class)->get()?->address, 'branches.manage'],
            [__('Open the cash register for the first time'), route('cash-register'), CashShift::query()->exists(), 'cash-shifts.manage'],
            [__('Add products with barcodes'), route('products.create'), Product::query()->exists(), 'products.manage'],
            [__('Add staff users'), route('settings.users'), User::query()->count() > 1, 'users.manage'],
            [__('Choose receipt printing (ON / OFF / Ask)'), route('settings.general', ['tab' => 'receipt']), Setting::query()->where('key', 'receipt_print_mode')->exists(), 'settings.manage'],
        ];

        return view('livewire.dashboard', [
            'chart' => $chart,
            'chartMax' => max(1, (float) $chart->max('total')),
            'setupSteps' => collect($setupSteps)->filter(fn (array $step): bool => auth()->user()->can($step[3]))->values(),
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
