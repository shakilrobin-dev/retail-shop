<?php

namespace App\Livewire;

use App\Enums\PaymentDirection;
use App\Enums\RepairStatus;
use App\Enums\SaleStatus;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductUnit;
use App\Models\RepairJob;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use App\Services\CashShiftService;
use App\Services\CurrentBranch;
use App\Services\PaymentService;
use App\Services\ReportService;
use App\Services\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

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

        $days = collect(range(29, 1))->push(0)->map(fn (int $daysAgo) => today()->subDays($daysAgo));
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

        $branch = app(CurrentBranch::class)->get();
        $reports = app(ReportService::class);
        $yesterday = Sale::query()->where('branch_id', $branchId)->where('status', SaleStatus::Completed)->whereDate('sold_at', today()->subDay());
        $month = $reports->summary($branchId, today()->startOfMonth(), today());
        $lastMonthSameDays = $reports->summary($branchId, today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow());
        $shift = $branch ? app(PaymentService::class)->openShift($branch) : null;
        $repairCounts = RepairJob::query()->where('branch_id', $branchId)
            ->whereNotIn('status', [RepairStatus::Delivered->value, RepairStatus::Cancelled->value])
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('livewire.dashboard', [
            'chart' => $chart,
            'yesterdayTotal' => (float) (clone $yesterday)->sum('total'),
            'month' => $month,
            'lastMonth' => $lastMonthSameDays,
            'paymentMix' => $reports->byPaymentMethod($branchId, today()->subDays(6), today())->filter(fn ($row) => $row->money_in > 0)->values(),
            'topProducts' => $reports->topProducts($branchId, today()->subDays(6), today(), 6),
            'repairCounts' => $repairCounts,
            'stockValue' => (float) ProductStock::query()->join('products', 'products.id', '=', 'product_stocks.product_id')
                ->where('product_stocks.branch_id', $branchId)->where('product_stocks.quantity', '>', 0)
                ->selectRaw('SUM(product_stocks.quantity * products.cost_price) as value')->value('value'),
            'outOfStockCount' => Product::query()->where('is_active', true)
                ->whereRaw('COALESCE((select quantity from product_stocks where product_stocks.product_id = products.id and product_stocks.branch_id = ?), 0) <= 0', [$branchId])->count(),
            'shift' => $shift,
            'shiftSummary' => $shift ? app(CashShiftService::class)->summary($shift) : null,
            'activities' => Activity::query()->with(['causer', 'subject'])->latest('id')->limit(7)->get()
                ->loadMorph('subject', [ProductUnit::class => ['product']]),
            'newCustomers' => Customer::query()->where('created_at', '>=', today()->startOfMonth())->count(),
            'chartMax' => $this->niceMax((float) $chart->max('total')),
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
            'recentSales' => Sale::query()->with(['customer', 'user'])
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

    /**
     * Round the chart top up to a clean number (e.g. 3,450 -> 4,000).
     */
    private function niceMax(float $value): float
    {
        if ($value <= 0) {
            return 0;
        }

        $magnitude = 10 ** floor(log10($value));

        foreach ([1, 2, 2.5, 5, 10] as $step) {
            if ($value <= $step * $magnitude) {
                return $step * $magnitude;
            }
        }

        return 10 * $magnitude;
    }
}
