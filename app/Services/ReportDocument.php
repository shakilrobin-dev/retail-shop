<?php

namespace App\Services;

use App\Enums\PaymentDirection;
use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Turns any report into one "official document" structure:
 * letterhead, title block, columns, rows, totals, notes and signatures.
 * The same structure is rendered on screen, as PDF and as Excel.
 */
class ReportDocument
{
    /** Report key => [title, needs a date range, landscape]. */
    public const REPORTS = [
        'summary' => ['Profit & loss statement', true, false],
        'daily' => ['Daily sales summary', true, false],
        'sales-register' => ['Sales register', true, true],
        'products' => ['Product sales report', true, false],
        'categories' => ['Sales by category', true, false],
        'payments' => ['Collections by payment method', true, false],
        'staff' => ['Sales by staff', true, false],
        'expenses' => ['Expense report', true, false],
        'stock-valuation' => ['Stock valuation report', false, true],
        'dead-stock' => ['Dead stock report', false, false],
    ];

    public function __construct(
        private ReportService $reports,
        private Settings $settings,
        private CategoryTree $tree,
    ) {}

    /**
     * @param  array{main_categories?: bool, dead_days?: int}  $options
     * @return array<string, mixed>
     */
    public function build(string $key, ?int $branchId, CarbonInterface $from, CarbonInterface $to, array $options = []): array
    {
        $key = array_key_exists($key, self::REPORTS) ? $key : 'summary';
        [$title, $dated, $landscape] = self::REPORTS[$key];
        $branch = $branchId ? Branch::query()->find($branchId) : null;
        /** @var User|null $user */
        $user = Auth::user();

        $document = [
            'key' => $key,
            'title' => __($title),
            'reference' => sprintf('RPT-%s-%s-%s', $branch?->code ?? 'ALL', now()->format('Ymd-His'), strtoupper(substr(md5($key), 0, 4))),
            'period' => $dated
                ? __('From :from to :to', ['from' => $from->format('d M Y'), 'to' => $to->format('d M Y')])
                : __('As of :date', ['date' => now()->format('d M Y, h:i A')]),
            'branch' => $branch?->localName() ?? __('All branches'),
            'generated_by' => $user?->name ?? __('System'),
            'generated_at' => now()->format('d M Y, h:i A'),
            'landscape' => $landscape,
            'columns' => [],
            'rows' => [],
            'totals' => null,
            'sections' => [],
            'notes' => [],
            'letterhead' => $this->letterhead($branch),
        ];

        return match ($key) {
            'summary' => $this->summary($document, $branchId, $from, $to),
            'daily' => $this->daily($document, $branchId, $from, $to),
            'sales-register' => $this->salesRegister($document, $branchId, $from, $to),
            'products' => $this->products($document, $branchId, $from, $to),
            'categories' => $this->categories($document, $branchId, $from, $to, (bool) ($options['main_categories'] ?? false)),
            'payments' => $this->payments($document, $branchId, $from, $to),
            'staff' => $this->staff($document, $branchId, $from, $to),
            'expenses' => $this->expenses($document, $branchId, $from, $to),
            'stock-valuation' => $this->stockValuation($document, $branchId),
            'dead-stock' => $this->deadStock($document, $branchId, max(1, (int) ($options['dead_days'] ?? 60))),
        };
    }

    /**
     * Shop details printed at the top of every document.
     *
     * @return array<string, string|null>
     */
    public function letterhead(?Branch $branch): array
    {
        $bn = app()->getLocale() === 'bn';
        $branch ??= Branch::query()->orderBy('id')->first();
        $logo = $this->settings->get('shop_logo');

        return [
            'name' => ($bn ? $this->settings->get('shop_name_bn') : null) ?: $this->settings->get('shop_name'),
            'tagline' => $this->settings->get('report_tagline'),
            'address' => ($bn ? $branch?->address_bn : null) ?: $branch?->address,
            'phone' => $branch?->phone ?: $this->settings->get('shop_phone'),
            'bin' => $this->settings->get('vat_bin'),
            'logo_path' => $logo ? storage_path('app/public/'.$logo) : null,
            'logo_url' => $logo ? asset('storage/'.$logo) : null,
            'footer_note' => $this->settings->get('report_footer_note'),
            'signatures' => $this->settings->bool('report_show_signatures'),
        ];
    }

    /**
     * Format one cell for HTML / PDF.
     */
    public static function display(mixed $value, string $format): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($format) {
            'money' => money($value),
            'qty' => shop_digits(rtrim(rtrim(number_format((float) $value, 3), '0'), '.')),
            'int' => shop_digits(number_format((float) $value)),
            'percent' => shop_digits(number_format((float) $value, 1)).'%',
            default => (string) $value,
        };
    }

    /**
     * @param  list<array{0: string, 1: string, 2?: string, 3?: string}>  $columns  [key, label, format, align]
     * @return list<array{key: string, label: string, format: string, align: string}>
     */
    private function columns(array $columns): array
    {
        return array_map(fn (array $column): array => [
            'key' => $column[0],
            'label' => $column[1],
            'format' => $column[2] ?? 'text',
            'align' => $column[3] ?? (in_array($column[2] ?? 'text', ['money', 'qty', 'int', 'percent'], true) ? 'right' : 'left'),
        ], $columns);
    }

    private function summary(array $document, ?int $branchId, CarbonInterface $from, CarbonInterface $to): array
    {
        $s = $this->reports->summary($branchId, $from, $to);
        $document['figures'] = $s;

        $document['sections'] = [
            ['title' => __('Sales'), 'lines' => [
                [__('Number of invoices'), $s['invoice_count'], 'int', ''],
                [__('Gross sales (with VAT)'), $s['gross_sales'], 'money', ''],
                [__('Less: VAT collected'), -$s['vat'], 'money', 'muted'],
                [__('Less: returns and refunds'), -$s['returns'], 'money', 'muted'],
                [__('Net sales'), $s['net_sales'], 'money', 'subtotal'],
                [__('Less: cost of goods sold'), -$s['cost_of_goods'], 'money', 'muted'],
                [__('Gross profit from sales'), $s['sales_profit'], 'money', 'total'],
            ]],
            ['title' => __('Repair service'), 'lines' => [
                [__('Delivered jobs'), $s['repair_count'], 'int', ''],
                [__('Repair income'), $s['repair_income'], 'money', ''],
                [__('Less: parts used'), -$s['repair_parts_cost'], 'money', 'muted'],
                [__('Profit from repairs'), $s['repair_profit'], 'money', 'total'],
            ]],
            ['title' => __('Operating expenses'), 'lines' => [
                [__('Total expenses'), -$s['expenses'], 'money', 'total'],
            ]],
            ['title' => null, 'lines' => [
                [__('Net profit for the period'), $s['net_profit'], 'money', 'grand'],
            ]],
        ];
        $document['notes'][] = __('Discounts given in the period: :amount (already deducted from sales).', ['amount' => money($s['discounts'])]);
        $document['notes'][] = __('Cost of goods sold uses the average purchase cost at the time of sale.');

        return $document;
    }

    private function daily(array $document, ?int $branchId, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = $this->reports->daily($branchId, $from, $to);
        $document['columns'] = $this->columns([['day', __('Date')], ['invoices', __('Invoices'), 'int'], ['sales', __('Sales'), 'money'], ['profit', __('Gross profit'), 'money'], ['margin', __('Margin'), 'percent']]);
        $document['rows'] = $rows->map(fn ($row): array => [
            'day' => Carbon::parse($row->day)->format('D, d M Y'),
            'invoices' => (int) $row->invoices, 'sales' => (float) $row->sales, 'profit' => (float) $row->profit,
            'margin' => $row->sales > 0 ? $row->profit / $row->sales * 100 : 0,
        ])->all();
        $sales = (float) $rows->sum('sales');
        $profit = (float) $rows->sum('profit');
        $document['totals'] = ['day' => __('Total'), 'invoices' => (int) $rows->sum('invoices'), 'sales' => $sales, 'profit' => $profit, 'margin' => $sales > 0 ? $profit / $sales * 100 : 0];

        return $document;
    }

    private function salesRegister(array $document, ?int $branchId, CarbonInterface $from, CarbonInterface $to): array
    {
        $sales = $this->reports->salesRegister($branchId, $from, $to);
        $document['columns'] = $this->columns([
            ['invoice', __('Invoice')], ['date', __('Date & time')], ['customer', __('Customer')], ['items', __('Items'), 'int'],
            ['subtotal', __('Subtotal'), 'money'], ['discount', __('Discount'), 'money'], ['vat', __('VAT'), 'money'],
            ['total', __('Total'), 'money'], ['paid_by', __('Paid by')], ['cashier', __('Cashier')], ['status', __('Status')],
        ]);
        $document['rows'] = $sales->map(fn ($sale): array => [
            'invoice' => $sale->invoice_no, 'date' => $sale->sold_at->format('d/m/Y h:i A'),
            'customer' => $sale->customer?->name ?? __('Walk-in'), 'items' => $sale->items_count,
            'subtotal' => (float) $sale->subtotal, 'discount' => (float) $sale->discount_amount, 'vat' => (float) $sale->vat_amount,
            'total' => (float) $sale->total,
            'paid_by' => $sale->payments->where('direction', PaymentDirection::In)->map(fn ($p) => $p->paymentMethod->localName())->unique()->implode(', '),
            'cashier' => $sale->user?->name, 'status' => $sale->status->label(),
        ])->all();
        $completed = $sales->where('status', SaleStatus::Completed);
        $document['totals'] = [
            'invoice' => __('Total (completed only)'), 'items' => (int) $completed->sum('items_count'),
            'subtotal' => (float) $completed->sum('subtotal'), 'discount' => (float) $completed->sum('discount_amount'),
            'vat' => (float) $completed->sum('vat_amount'), 'total' => (float) $completed->sum('total'),
        ];
        $voided = $sales->count() - $completed->count();

        if ($voided > 0) {
            $document['notes'][] = __(':count voided invoices are listed but not included in totals.', ['count' => $voided]);
        }

        return $document;
    }

    private function products(array $document, ?int $branchId, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = $this->reports->topProducts($branchId, $from, $to, 1000);
        $document['columns'] = $this->columns([['rank', '#', 'int'], ['name', __('Product')], ['quantity', __('Qty sold (pcs)'), 'qty'], ['revenue', __('Sales'), 'money'], ['profit', __('Gross profit'), 'money'], ['margin', __('Margin'), 'percent']]);
        $document['rows'] = $rows->values()->map(fn ($row, $index): array => [
            'rank' => $index + 1, 'name' => $row->name, 'quantity' => (float) $row->quantity, 'revenue' => (float) $row->revenue,
            'profit' => (float) $row->profit, 'margin' => $row->revenue > 0 ? $row->profit / $row->revenue * 100 : 0,
        ])->all();
        $document['totals'] = ['name' => __('Total'), 'quantity' => (float) $rows->sum('quantity'), 'revenue' => (float) $rows->sum('revenue'), 'profit' => (float) $rows->sum('profit')];

        return $document;
    }

    private function categories(array $document, ?int $branchId, CarbonInterface $from, CarbonInterface $to, bool $mainOnly): array
    {
        $rows = $this->reports->byCategory($branchId, $from, $to, $mainOnly);
        $total = max(0.01, (float) $rows->sum('revenue'));
        $document['columns'] = $this->columns([['name', __('Category')], ['quantity', __('Qty sold (pcs)'), 'qty'], ['revenue', __('Sales'), 'money'], ['share', __('Share of sales'), 'percent'], ['profit', __('Gross profit'), 'money']]);
        $document['rows'] = $rows->map(fn ($row): array => $row + ['share' => $row['revenue'] / $total * 100])->all();
        $document['totals'] = ['name' => __('Total'), 'quantity' => (float) $rows->sum('quantity'), 'revenue' => (float) $rows->sum('revenue'), 'share' => 100, 'profit' => (float) $rows->sum('profit')];
        $document['notes'][] = $mainOnly ? __('Sub-categories are added into their main category.') : __('Categories are shown with their full path.');

        return $document;
    }

    private function payments(array $document, ?int $branchId, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = $this->reports->byPaymentMethod($branchId, $from, $to);
        $bn = app()->getLocale() === 'bn';
        $document['columns'] = $this->columns([['name', __('Payment method')], ['in', __('Money in'), 'money'], ['out', __('Money out'), 'money'], ['net', __('Net'), 'money']]);
        $document['rows'] = $rows->map(fn ($row): array => [
            'name' => $bn && $row->name_bn ? $row->name_bn : $row->name, 'in' => (float) $row->money_in, 'out' => (float) $row->money_out,
            'net' => (float) $row->money_in - (float) $row->money_out,
        ])->all();
        $document['totals'] = ['name' => __('Total'), 'in' => (float) $rows->sum('money_in'), 'out' => (float) $rows->sum('money_out'), 'net' => (float) $rows->sum('money_in') - (float) $rows->sum('money_out')];
        $document['notes'][] = __('Money in: sales, repair payments, supplier refunds. Money out: refunds, purchases, expenses.');

        return $document;
    }

    private function staff(array $document, ?int $branchId, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = $this->reports->byStaff($branchId, $from, $to);
        $document['columns'] = $this->columns([['name', __('Staff')], ['invoices', __('Invoices'), 'int'], ['sales', __('Sales'), 'money'], ['average', __('Average sale'), 'money'], ['discounts', __('Discounts given'), 'money']]);
        $document['rows'] = $rows->map(fn ($row): array => [
            'name' => $row->name ?? '—', 'invoices' => (int) $row->invoices, 'sales' => (float) $row->sales,
            'average' => $row->invoices ? $row->sales / $row->invoices : 0, 'discounts' => (float) $row->discounts,
        ])->all();
        $document['totals'] = ['name' => __('Total'), 'invoices' => (int) $rows->sum('invoices'), 'sales' => (float) $rows->sum('sales'), 'discounts' => (float) $rows->sum('discounts')];

        return $document;
    }

    private function expenses(array $document, ?int $branchId, CarbonInterface $from, CarbonInterface $to): array
    {
        $expenses = $this->reports->expenseList($branchId, $from, $to);
        $document['columns'] = $this->columns([['date', __('Date')], ['category', __('Category')], ['note', __('Description')], ['paid_by', __('Paid by')], ['user', __('Entered by')], ['amount', __('Amount'), 'money']]);
        $document['rows'] = $expenses->map(fn ($expense): array => [
            'date' => $expense->expense_date->format('d/m/Y'), 'category' => $expense->category->localName(), 'note' => $expense->note,
            'paid_by' => $expense->payments->first()?->paymentMethod->localName(), 'user' => $expense->user?->name, 'amount' => (float) $expense->amount,
        ])->all();
        $document['totals'] = ['date' => __('Total'), 'amount' => (float) $expenses->sum('amount')];

        $byCategory = $expenses->groupBy(fn ($expense) => $expense->category->localName())->map->sum('amount')->sortDesc();
        $document['sections'] = [['title' => __('Summary by category'), 'lines' => $byCategory->map(fn ($amount, $name) => [$name, (float) $amount, 'money', ''])->values()->all()]];

        return $document;
    }

    private function stockValuation(array $document, ?int $branchId): array
    {
        $rows = $this->reports->stockValuation($branchId);
        $document['columns'] = $this->columns([['sku', __('SKU')], ['name', __('Product')], ['category', __('Category')], ['quantity', __('On hand'), 'qty'], ['cost', __('Unit cost'), 'money'], ['cost_value', __('Value at cost'), 'money'], ['price', __('Selling price'), 'money'], ['retail_value', __('Value at selling price'), 'money']]);
        $document['rows'] = $rows->map(fn ($row): array => [
            'sku' => $row->sku, 'name' => $row->name, 'category' => $this->tree->path($row->category_id), 'quantity' => (float) $row->quantity,
            'cost' => (float) $row->cost_price, 'cost_value' => (float) $row->cost_value, 'price' => (float) $row->selling_price, 'retail_value' => (float) $row->retail_value,
        ])->all();
        $cost = (float) $rows->sum('cost_value');
        $retail = (float) $rows->sum('retail_value');
        $document['totals'] = ['sku' => __('Total'), 'quantity' => (float) $rows->sum('quantity'), 'cost_value' => $cost, 'retail_value' => $retail];
        $document['notes'][] = __('Possible gross profit if all stock is sold at current prices: :amount.', ['amount' => money($retail - $cost)]);

        return $document;
    }

    private function deadStock(array $document, ?int $branchId, int $days): array
    {
        $rows = $this->reports->deadStock($branchId, $days);
        $document['title'] = __('Dead stock report (not sold in :days days)', ['days' => $days]);
        $document['columns'] = $this->columns([['sku', __('SKU')], ['name', __('Product')], ['quantity', __('In stock'), 'qty'], ['cost', __('Unit cost'), 'money'], ['value', __('Money stuck (cost)'), 'money']]);
        $document['rows'] = $rows->map(fn ($row): array => [
            'sku' => $row->sku, 'name' => $row->name, 'quantity' => (float) $row->quantity, 'cost' => (float) $row->cost_price, 'value' => (float) $row->value,
        ])->all();
        $document['totals'] = ['sku' => __('Total'), 'quantity' => (float) $rows->sum('quantity'), 'value' => (float) $rows->sum('value')];
        $document['notes'][] = __('Consider discounts or returns to supplier for these items.');

        return $document;
    }
}
