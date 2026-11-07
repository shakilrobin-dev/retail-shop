<?php

namespace App\Services;

use App\Enums\RepairStatus;
use App\Enums\SaleStatus;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Product;
use App\Models\RepairJob;
use App\Models\RepairJobPart;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Numbers for the reports page. $branchId = null means all branches.
 */
class ReportService
{
    /**
     * Profit and loss for a period.
     *
     * @return array<string, float|int>
     */
    public function summary(?int $branchId, CarbonInterface $from, CarbonInterface $to): array
    {
        $sales = $this->completedSales($branchId, $from, $to);

        $grossSales = (float) (clone $sales)->sum('total');
        $vat = (float) (clone $sales)->sum('vat_amount');
        $discounts = (float) (clone $sales)->sum('discount_amount')
            + (float) SaleItem::query()->whereIn('sale_id', (clone $sales)->select('id'))->sum('discount_amount');
        $invoiceCount = (clone $sales)->count();

        $cost = (float) SaleItem::query()->whereIn('sale_id', (clone $sales)->select('id'))->sum(DB::raw('unit_cost * quantity'));

        $returns = SaleReturn::query()
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
        $returnsTotal = (float) (clone $returns)->sum('total');
        $returnedCost = (float) SaleReturnItem::query()
            ->join('sale_items', 'sale_items.id', '=', 'sale_return_items.sale_item_id')
            ->whereIn('sale_return_items.sale_return_id', (clone $returns)->select('id'))
            ->where('sale_return_items.restock', true)
            ->sum(DB::raw('sale_items.unit_cost * sale_return_items.quantity'));

        $netSales = $grossSales - $vat - $returnsTotal;
        $salesProfit = $netSales - ($cost - $returnedCost);

        $repairs = RepairJob::query()
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->where('status', RepairStatus::Delivered)
            ->whereBetween('delivered_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
        $repairIncome = (float) (clone $repairs)->sum('total');
        $repairPartsCost = (float) RepairJobPart::query()->whereIn('repair_job_id', (clone $repairs)->select('id'))->sum(DB::raw('unit_cost * quantity'));
        $repairCount = (clone $repairs)->count();

        $expenses = (float) Expense::query()
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->whereDate('expense_date', '>=', $from->toDateString())
            ->whereDate('expense_date', '<=', $to->toDateString())
            ->sum('amount');

        $repairProfit = $repairIncome - $repairPartsCost;

        return [
            'invoice_count' => $invoiceCount,
            'gross_sales' => round($grossSales, 2),
            'discounts' => round($discounts, 2),
            'vat' => round($vat, 2),
            'returns' => round($returnsTotal, 2),
            'net_sales' => round($netSales, 2),
            'cost_of_goods' => round($cost - $returnedCost, 2),
            'sales_profit' => round($salesProfit, 2),
            'repair_count' => $repairCount,
            'repair_income' => round($repairIncome, 2),
            'repair_parts_cost' => round($repairPartsCost, 2),
            'repair_profit' => round($repairProfit, 2),
            'expenses' => round($expenses, 2),
            'net_profit' => round($salesProfit + $repairProfit - $expenses, 2),
            'average_sale' => $invoiceCount ? round($grossSales / $invoiceCount, 2) : 0,
        ];
    }

    /**
     * @return Collection<int, object{day: string, invoices: int, sales: float, profit: float}>
     */
    public function daily(?int $branchId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereIn('sales.id', $this->completedSales($branchId, $from, $to)->select('id'))
            ->selectRaw('DATE(sales.sold_at) as day, COUNT(DISTINCT sales.id) as invoices, SUM(sale_items.line_total) as sales, SUM(sale_items.line_total - sale_items.unit_cost * sale_items.quantity) as profit')
            ->groupBy('day')
            ->orderBy('day')
            ->get();
    }

    /**
     * Best sellers by revenue.
     *
     * @return Collection<int, SaleItem>
     */
    public function topProducts(?int $branchId, CarbonInterface $from, CarbonInterface $to, int $limit = 50): Collection
    {
        return SaleItem::query()
            ->whereIn('sale_id', $this->completedSales($branchId, $from, $to)->select('id'))
            ->selectRaw('product_id, MAX(product_name) as name, SUM(base_quantity) as quantity, SUM(line_total) as revenue, SUM(line_total - unit_cost * quantity) as profit')
            ->groupBy('product_id')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();
    }

    /**
     * Sales per category. $mainOnly = true adds sub-categories up into their main category.
     *
     * @return Collection<int, array{name: string, quantity: float, revenue: float, profit: float}>
     */
    public function byCategory(?int $branchId, CarbonInterface $from, CarbonInterface $to, bool $mainOnly = false): Collection
    {
        $rows = SaleItem::query()
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->whereIn('sale_items.sale_id', $this->completedSales($branchId, $from, $to)->select('id'))
            ->selectRaw('products.category_id as category_id, SUM(sale_items.base_quantity) as quantity, SUM(sale_items.line_total) as revenue, SUM(sale_items.line_total - sale_items.unit_cost * sale_items.quantity) as profit')
            ->groupBy('products.category_id')
            ->get();

        $tree = app(CategoryTree::class);

        return $rows
            ->groupBy(fn ($row): string => $mainOnly ? explode(' › ', $tree->path((int) $row->category_id))[0] : $tree->path((int) $row->category_id))
            ->map(fn (Collection $group, string $name): array => [
                'name' => $name,
                'quantity' => (float) $group->sum('quantity'),
                'revenue' => round((float) $group->sum('revenue'), 2),
                'profit' => round((float) $group->sum('profit'), 2),
            ])
            ->sortByDesc('revenue')
            ->values();
    }

    /**
     * Money in/out by payment method (sales, repairs, refunds, purchases, expenses).
     *
     * @return Collection<int, object>
     */
    public function byPaymentMethod(?int $branchId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Payment::query()
            ->join('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
            ->when($branchId, fn (Builder $query) => $query->where('payments.branch_id', $branchId))
            ->whereBetween('payments.paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw("payment_methods.name as name, payment_methods.name_bn as name_bn,
                SUM(CASE WHEN payments.direction = 'in' THEN payments.amount ELSE 0 END) as money_in,
                SUM(CASE WHEN payments.direction = 'out' THEN payments.amount ELSE 0 END) as money_out")
            ->groupBy('payment_methods.id', 'payment_methods.name', 'payment_methods.name_bn')
            ->orderBy('payment_methods.sort_order')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function byStaff(?int $branchId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Sale::query()
            ->leftJoin('users', 'users.id', '=', 'sales.user_id')
            ->whereIn('sales.id', $this->completedSales($branchId, $from, $to)->select('id'))
            ->selectRaw('users.name as name, COUNT(*) as invoices, SUM(sales.total) as sales, SUM(sales.discount_amount) as discounts')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('sales')
            ->get();
    }

    /**
     * Products in stock that did not sell for $days days.
     *
     * @return Collection<int, Product>
     */
    public function deadStock(?int $branchId, int $days): Collection
    {
        $since = now()->subDays($days);

        $soldRecently = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', SaleStatus::Completed)
            ->where('sales.sold_at', '>=', $since)
            ->when($branchId, fn ($query) => $query->where('sales.branch_id', $branchId))
            ->select('sale_items.product_id');

        return Product::query()
            ->join('product_stocks', 'product_stocks.product_id', '=', 'products.id')
            ->when($branchId, fn ($query) => $query->where('product_stocks.branch_id', $branchId))
            ->where('product_stocks.quantity', '>', 0)
            ->whereNotIn('products.id', $soldRecently)
            ->where('products.created_at', '<', $since)
            ->selectRaw('products.id, products.name, products.sku, products.cost_price, SUM(product_stocks.quantity) as quantity, SUM(product_stocks.quantity) * products.cost_price as value')
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.cost_price')
            ->orderByDesc('value')
            ->get();
    }

    /**
     * @return Builder<Sale>
     */
    private function completedSales(?int $branchId, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return Sale::query()
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->where('status', SaleStatus::Completed)
            ->whereBetween('sold_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
    }

    /**
     * Every invoice in the period (sales register).
     *
     * @return Collection<int, Sale>
     */
    public function salesRegister(?int $branchId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Sale::query()
            ->with(['customer', 'user', 'payments.paymentMethod'])
            ->withCount('items')
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->whereIn('status', [SaleStatus::Completed, SaleStatus::Voided])
            ->whereBetween('sold_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderBy('sold_at')
            ->get();
    }

    /**
     * Stock on hand with cost and selling value.
     *
     * @return Collection<int, object>
     */
    public function stockValuation(?int $branchId): Collection
    {
        return Product::query()
            ->join('product_stocks', 'product_stocks.product_id', '=', 'products.id')
            ->leftJoin('product_units', fn ($join) => $join->on('product_units.product_id', '=', 'products.id')->where('product_units.is_base', true))
            ->when($branchId, fn ($query) => $query->where('product_stocks.branch_id', $branchId))
            ->where('products.is_active', true)
            ->selectRaw('products.id, products.sku, products.name, products.category_id, products.cost_price, MAX(product_units.selling_price) as selling_price,
                SUM(product_stocks.quantity) as quantity,
                SUM(product_stocks.quantity) * products.cost_price as cost_value,
                SUM(product_stocks.quantity) * MAX(product_units.selling_price) as retail_value')
            ->groupBy('products.id', 'products.sku', 'products.name', 'products.category_id', 'products.cost_price')
            ->havingRaw('SUM(product_stocks.quantity) > 0')
            ->orderBy('products.name')
            ->get();
    }

    /**
     * Expenses in the period, oldest first.
     *
     * @return Collection<int, Expense>
     */
    public function expenseList(?int $branchId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Expense::query()
            ->with(['category', 'user', 'payments.paymentMethod'])
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->whereDate('expense_date', '>=', $from->toDateString())
            ->whereDate('expense_date', '<=', $to->toDateString())
            ->orderBy('expense_date')
            ->get();
    }
}
