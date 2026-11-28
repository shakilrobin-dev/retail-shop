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
        'referrers' => ['Sales by reference', true, false],
        'commissions' => ['Commission report', true, false],
        'expenses' => ['Expense report', true, false],
        'customer-dues' => ['Customer due report', false, true],
        'due-collections' => ['Due collection report', true, false],
        'stock-valuation' => ['Stock valuation report', false, true],
        'dead-stock' => ['Dead stock report', false, false],
    ];

    private const PRODUCT_FILTERS = ['categories', 'subcategories', 'brands', 'products'];

    private const SALE_FILTERS = ['categories', 'subcategories', 'brands', 'products', 'referrers', 'customers', 'staff'];

    /** Report key => the filters it can use (see ReportFilters::KEYS). */
    public const FILTERS = [
        'summary' => [],
        'daily' => self::SALE_FILTERS,
        'sales-register' => self::SALE_FILTERS,
        'products' => self::SALE_FILTERS,
        'categories' => self::SALE_FILTERS,
        'payments' => ['payment_methods', 'staff'],
        'staff' => self::SALE_FILTERS,
        'referrers' => self::SALE_FILTERS,
        'commissions' => ['referrers'],
        'expenses' => ['expense_categories', 'staff'],
        'customer-dues' => ['customers', 'referrers'],
        'due-collections' => ['customers', 'payment_methods', 'staff'],
        'stock-valuation' => self::PRODUCT_FILTERS,
        'dead-stock' => self::PRODUCT_FILTERS,
    ];

    public function __construct(
        private ReportService $reports,
        private Settings $settings,
        private CategoryTree $tree,
    ) {}

    /**
     * @param  array{main_categories?: bool, dead_days?: int, due_detail?: string, customer_id?: int|null, filters?: array<string, mixed>}  $options
     * @return array<string, mixed>
     */
    public function build(string $key, ?int $branchId, CarbonInterface $from, CarbonInterface $to, array $options = []): array
    {
        $key = array_key_exists($key, self::REPORTS) ? $key : 'summary';
        [$title, $dated, $landscape] = self::REPORTS[$key];
        $branch = $branchId ? Branch::query()->find($branchId) : null;
        /** @var User|null $user */
        $user = Auth::user();
        $filters = ReportFilters::from($options['filters'] ?? [], self::FILTERS[$key]);

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
            'subject' => null,
            'filters' => $filters->describe(),
            'columns' => [],
            'rows' => [],
            'totals' => null,
            'sections' => [],
            'notes' => [],
            'letterhead' => $this->letterhead($branch),
        ];

        return match ($key) {
            'summary' => $this->summary($document, $branchId, $from, $to),
            'daily' => $this->daily($document, $branchId, $from, $to, $filters),
            'sales-register' => $this->salesRegister($document, $branchId, $from, $to, $filters),
            'products' => $this->products($document, $branchId, $from, $to, $filters),
            'categories' => $this->categories($document, $branchId, $from, $to, (bool) ($options['main_categories'] ?? false), $filters),
            'payments' => $this->payments($document, $branchId, $from, $to, $filters),
            'staff' => $this->staff($document, $branchId, $from, $to, $filters),
            'referrers' => $this->referrers($document, $branchId, $from, $to, $filters),
            'commissions' => $this->commissions($document, $branchId, $from, $to, $filters),
            'expenses' => $this->expenses($document, $branchId, $from, $to, $filters),
            'customer-dues' => $this->customerDues($document, $branchId, $options['customer_id'] ?? null, $options['due_detail'] ?? 'summary', $filters),
            'due-collections' => $this->dueCollections($document, $branchId, $from, $to, $filters),
            'stock-valuation' => $this->stockValuation($document, $branchId, $filters),
            'dead-stock' => $this->deadStock($document, $branchId, max(1, (int) ($options['dead_days'] ?? 60)), $filters),
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

    /**
     * A link on an invoice count: on screen it opens the list of those invoices (see ReportService::invoicesFor()).
     *
     * @param  array<string, mixed>  $criteria
     * @return array<string, mixed>|null
     */
    private function invoiceLink(int $count, string $title, array $criteria = []): ?array
    {
        return $count > 0 ? ['title' => $title] + $criteria : null;
    }

    private function summary(array $document, ?int $branchId, CarbonInterface $from, CarbonInterface $to): array
    {
        $s = $this->reports->summary($branchId, $from, $to);
        $document['figures'] = $s;

        $document['sections'] = [
            ['title' => __('Sales'), 'lines' => [
                [__('Number of invoices'), $s['invoice_count'], 'int', '', $this->invoiceLink($s['invoice_count'], __('Invoices'))],
                [__('Gross sales (with VAT)'), $s['gross_sales'], 'money', ''],
                [__('Less: VAT collected'), -$s['vat'], 'money', 'muted'],
                [__('Less: returns and refunds'), -$s['returns'], 'money', 'muted'],
                [__('Net sales'), $s['net_sales'], 'money', 'subtotal'],
                [__('Less: cost of goods sold'), -$s['cost_of_goods'], 'money', 'muted'],
                [__('Gross profit from sales'), $s['sales_profit'], 'money', 'total'],
            ]],
            ...($s['repair_income'] > 0 || $s['repair_count'] > 0 || $this->settings->bool('repairs_enabled') ? [['title' => __('Repair service'), 'lines' => [
                [__('Delivered jobs'), $s['repair_count'], 'int', ''],
                [__('Repair income'), $s['repair_income'], 'money', ''],
                [__('Less: parts used'), -$s['repair_parts_cost'], 'money', 'muted'],
                [__('Profit from repairs'), $s['repair_profit'], 'money', 'total'],
            ]]] : []),
            ['title' => __('Operating expenses'), 'lines' => [
                [__('Commission given'), -$s['commission_given'], 'money', 'muted'],
                [__('Other expenses'), -$s['other_expenses'], 'money', 'muted'],
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

    private function daily(array $document, ?int $branchId, CarbonInterface $from, CarbonInterface $to, ReportFilters $filters): array
    {
        $rows = $this->reports->daily($branchId, $from, $to, $filters);
        $document['columns'] = $this->columns([['day', __('Date')], ['invoices', __('Invoices'), 'int'], ['sales', __('Sales'), 'money'], ['profit', __('Gross profit'), 'money'], ['margin', __('Margin'), 'percent']]);
        $document['rows'] = $rows->map(fn ($row): array => [
            'day' => Carbon::parse($row->day)->format('D, d M Y'),
            'invoices' => (int) $row->invoices, 'sales' => (float) $row->sales, 'profit' => (float) $row->profit,
            'margin' => $row->sales > 0 ? $row->profit / $row->sales * 100 : 0,
            '_links' => ['invoices' => $this->invoiceLink((int) $row->invoices, __('Invoices on :date', ['date' => Carbon::parse($row->day)->format('d M Y')]), ['day' => Carbon::parse($row->day)->toDateString()])],
        ])->all();
        $sales = (float) $rows->sum('sales');
        $profit = (float) $rows->sum('profit');
        $document['totals'] = ['day' => __('Total'), 'invoices' => (int) $rows->sum('invoices'), 'sales' => $sales, 'profit' => $profit, 'margin' => $sales > 0 ? $profit / $sales * 100 : 0,
            '_links' => ['invoices' => $this->invoiceLink((int) $rows->sum('invoices'), __('Invoices'))]];

        return $document;
    }

    private function salesRegister(array $document, ?int $branchId, CarbonInterface $from, CarbonInterface $to, ReportFilters $filters): array
    {
        $sales = $this->reports->salesRegister($branchId, $from, $to, $filters);
        $document['columns'] = $this->columns([
            ['invoice', __('Invoice')], ['date', __('Date & time')], ['customer', __('Customer')], ['items', __('Items'), 'int'],
            ['subtotal', __('Subtotal'), 'money'], ['discount', __('Discount'), 'money'], ['vat', __('VAT'), 'money'],
            ['total', __('Total'), 'money'], ['due', __('Due'), 'money'], ['paid_by', __('Paid by')], ['cashier', __('Cashier')], ['status', __('Status')],
        ]);
        $document['rows'] = $sales->map(fn ($sale): array => [
            'invoice' => $sale->invoice_no, 'date' => $sale->sold_at->format('d/m/Y h:i A'),
            'customer' => $sale->customer?->name ?? __('Walk-in'), 'items' => $sale->items_count,
            'subtotal' => (float) $sale->subtotal, 'discount' => (float) $sale->discount_amount, 'vat' => (float) $sale->vat_amount,
            'total' => (float) $sale->total, 'due' => (float) $sale->due_amount,
            'paid_by' => $sale->payments->where('direction', PaymentDirection::In)->map(fn ($p) => $p->paymentMethod->localName())->unique()->implode(', '),
            'cashier' => $sale->user?->name, 'status' => $sale->status === SaleStatus::Voided ? $sale->status->label() : $sale->payment_status->label(),
            '_links' => ['invoice' => ['sale' => $sale->id]],
        ])->all();
        $completed = $sales->where('status', SaleStatus::Completed);
        $document['totals'] = [
            'invoice' => __('Total (completed only)'), 'items' => (int) $completed->sum('items_count'),
            'subtotal' => (float) $completed->sum('subtotal'), 'discount' => (float) $completed->sum('discount_amount'),
            'vat' => (float) $completed->sum('vat_amount'), 'total' => (float) $completed->sum('total'), 'due' => (float) $completed->sum('due_amount'),
        ];
        $voided = $sales->count() - $completed->count();

        if ($filters->hasItemFilters()) {
            $document['notes'][] = __('Invoices that include the chosen products. Amounts are for the whole invoice.');
        }

        if ($voided > 0) {
            $document['notes'][] = __(':count voided invoices are listed but not included in totals.', ['count' => $voided]);
        }

        return $document;
    }

    private function products(array $document, ?int $branchId, CarbonInterface $from, CarbonInterface $to, ReportFilters $filters): array
    {
        $rows = $this->reports->topProducts($branchId, $from, $to, 1000, $filters);
        $document['columns'] = $this->columns([['rank', '#', 'int'], ['name', __('Product')], ['quantity', __('Qty sold (pcs)'), 'qty'], ['revenue', __('Sales'), 'money'], ['profit', __('Gross profit'), 'money'], ['margin', __('Margin'), 'percent']]);
        $document['rows'] = $rows->values()->map(fn ($row, $index): array => [
            'rank' => $index + 1, 'name' => $row->name, 'quantity' => (float) $row->quantity, 'revenue' => (float) $row->revenue,
            'profit' => (float) $row->profit, 'margin' => $row->revenue > 0 ? $row->profit / $row->revenue * 100 : 0,
        ])->all();
        $document['totals'] = ['name' => __('Total'), 'quantity' => (float) $rows->sum('quantity'), 'revenue' => (float) $rows->sum('revenue'), 'profit' => (float) $rows->sum('profit')];

        return $document;
    }

    private function categories(array $document, ?int $branchId, CarbonInterface $from, CarbonInterface $to, bool $mainOnly, ReportFilters $filters): array
    {
        $rows = $this->reports->byCategory($branchId, $from, $to, $mainOnly, $filters);
        $total = max(0.01, (float) $rows->sum('revenue'));
        $document['columns'] = $this->columns([['name', __('Category')], ['quantity', __('Qty sold (pcs)'), 'qty'], ['revenue', __('Sales'), 'money'], ['share', __('Share of sales'), 'percent'], ['profit', __('Gross profit'), 'money']]);
        $document['rows'] = $rows->map(fn ($row): array => $row + ['share' => $row['revenue'] / $total * 100])->all();
        $document['totals'] = ['name' => __('Total'), 'quantity' => (float) $rows->sum('quantity'), 'revenue' => (float) $rows->sum('revenue'), 'share' => 100, 'profit' => (float) $rows->sum('profit')];
        $document['notes'][] = $mainOnly ? __('Sub-categories are added into their main category.') : __('Categories are shown with their full path.');

        return $document;
    }

    private function payments(array $document, ?int $branchId, CarbonInterface $from, CarbonInterface $to, ReportFilters $filters): array
    {
        $rows = $this->reports->byPaymentMethod($branchId, $from, $to, $filters);
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

    private function staff(array $document, ?int $branchId, CarbonInterface $from, CarbonInterface $to, ReportFilters $filters): array
    {
        $rows = $this->reports->byStaff($branchId, $from, $to, $filters);
        $document['columns'] = $this->columns([['name', __('Staff')], ['invoices', __('Invoices'), 'int'], ['sales', __('Sales'), 'money'], ['average', __('Average sale'), 'money'], ['discounts', __('Discounts given'), 'money']]);
        $document['rows'] = $rows->map(fn ($row): array => [
            'name' => $row->name ?? '—', 'invoices' => (int) $row->invoices, 'sales' => (float) $row->sales,
            'average' => $row->invoices ? $row->sales / $row->invoices : 0, 'discounts' => (float) $row->discounts,
            '_links' => ['invoices' => $this->invoiceLink((int) $row->invoices, __('Invoices by :name', ['name' => $row->name ?? '—']), ['user' => $row->user_id === null ? null : (int) $row->user_id])],
        ])->all();
        $document['totals'] = ['name' => __('Total'), 'invoices' => (int) $rows->sum('invoices'), 'sales' => (float) $rows->sum('sales'), 'discounts' => (float) $rows->sum('discounts'),
            '_links' => ['invoices' => $this->invoiceLink((int) $rows->sum('invoices'), __('Invoices'))]];

        if ($filters->hasItemFilters()) {
            $document['notes'][] = __('Only the chosen products are counted: item totals before invoice discount and VAT.');
        }

        return $document;
    }

    private function referrers(array $document, ?int $branchId, CarbonInterface $from, CarbonInterface $to, ReportFilters $filters): array
    {
        $rows = $this->reports->byReferrer($branchId, $from, $to, $filters)->map(function ($row): array {
            $net = (float) $row->sales - (float) $row->vat;
            $profit = $net - (float) $row->cost;

            $name = $row->referrer_id ? $row->name.($row->phone ? ' ('.$row->phone.')' : '') : __('Without reference');

            return [
                'name' => $name,
                'invoices' => (int) $row->invoices, 'sales' => $net, 'cost' => (float) $row->cost, 'profit' => $profit,
                'margin' => $net > 0 ? $profit / $net * 100 : 0,
                '_links' => ['invoices' => $this->invoiceLink((int) $row->invoices, $row->referrer_id ? __('Invoices with reference :name', ['name' => $name]) : __('Invoices without reference'),
                    ['referrer' => $row->referrer_id === null ? null : (int) $row->referrer_id])],
            ];
        });

        $document['columns'] = $this->columns([
            ['name', __('Reference')], ['invoices', __('Invoices'), 'int'], ['sales', __('Sales'), 'money'], ['cost', __('Cost of goods'), 'money'],
            ['profit', __('Gross profit'), 'money'], ['margin', __('Margin'), 'percent'],
        ]);
        $document['rows'] = $rows->all();
        $sales = (float) $rows->sum('sales');
        $profit = (float) $rows->sum('profit');
        $document['totals'] = [
            'name' => __('Total'), 'invoices' => (int) $rows->sum('invoices'), 'sales' => $sales, 'cost' => (float) $rows->sum('cost'),
            'profit' => $profit, 'margin' => $sales > 0 ? $profit / $sales * 100 : 0,
            '_links' => ['invoices' => $this->invoiceLink((int) $rows->sum('invoices'), __('Invoices'))],
        ];
        $document['notes'][] = $filters->hasItemFilters()
            ? __('Only the chosen products are counted: item totals before invoice discount and VAT.')
            : __('Sales are without VAT. Returns are not deducted here; see the profit & loss for returns.');

        return $document;
    }

    private function commissions(array $document, ?int $branchId, CarbonInterface $from, CarbonInterface $to, ReportFilters $filters): array
    {
        $rows = $this->reports->commissions($branchId, $from, $to, $filters);
        $document['columns'] = $this->columns([
            ['name', __('Reference')], ['phone', __('Phone')], ['invoices', __('Invoices'), 'int'], ['profit', __('Profit from sales'), 'money'],
            ['commission', __('Commission worked out'), 'money'], ['paid', __('Paid in period'), 'money'], ['due', __('Due now'), 'money'],
        ]);
        $document['rows'] = $rows->map(fn (array $row): array => $row + [
            '_links' => ['invoices' => $this->invoiceLink($row['invoices'], __('Invoices with reference :name', ['name' => $row['name']]), ['referrer' => $row['referrer_id']])],
        ])->all();
        $document['totals'] = [
            'name' => __('Total'), 'invoices' => (int) $rows->sum('invoices'), 'profit' => (float) $rows->sum('profit'),
            'commission' => (float) $rows->sum('commission'), 'paid' => (float) $rows->sum('paid'), 'due' => (float) $rows->sum('due'),
            '_links' => ['invoices' => $this->invoiceLink((int) $rows->sum('invoices'), __('Invoices with a reference'), ['with_referrer' => true])],
        ];
        $document['notes'][] = __('Commission is worked out on each person\'s total profit for a month or date range, and counted as an expense (commission given) when it is paid.');
        $document['notes'][] = __('"Commission worked out" counts periods that end inside this report\'s dates.');
        $document['notes'][] = __('"Due now" is all unpaid commission up to today, not only this period.');

        return $document;
    }

    private function expenses(array $document, ?int $branchId, CarbonInterface $from, CarbonInterface $to, ReportFilters $filters): array
    {
        $expenses = $this->reports->expenseList($branchId, $from, $to, $filters);
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

    /**
     * Who owes how much. 'summary' = one line per customer with ageing,
     * 'invoices' = every unpaid invoice, 'items' = invoices with their items.
     */
    private function customerDues(array $document, ?int $branchId, ?int $customerId, string $detail, ReportFilters $filters): array
    {
        $detail = in_array($detail, ['summary', 'invoices', 'items'], true) ? $detail : 'summary';
        $customerId ??= count($filters->get('customers')) === 1 ? $filters->get('customers')[0] : null;
        $sales = $this->reports->customerDues($branchId, $customerId, $detail === 'items', $filters);
        $byCustomer = $sales->groupBy('customer_id');
        $bucket = fn (int $days): string => match (true) {
            $days <= 30 => 'age_30',
            $days <= 60 => 'age_60',
            $days <= 90 => 'age_90',
            default => 'age_older',
        };
        $ageing = ['age_30' => 0.0, 'age_60' => 0.0, 'age_90' => 0.0, 'age_older' => 0.0];

        foreach ($sales as $sale) {
            $ageing[$bucket($sale->ageInDays())] += (float) $sale->due_amount;
        }

        if ($customerId && $sales->isNotEmpty()) {
            $customer = $sales->first()->customer;
            $document['title'] = __('Customer due statement');
            $document['subject'] = trim($customer->name.($customer->phone ? ' · '.$customer->phone : ''));
        }

        if ($detail === 'summary') {
            $document['columns'] = $this->columns([
                ['name', __('Customer')], ['phone', __('Phone')], ['invoices', __('Invoices'), 'int'], ['oldest', __('Oldest (days)'), 'int'],
                ['age_30', __('0–30 days'), 'money'], ['age_60', __('31–60 days'), 'money'], ['age_90', __('61–90 days'), 'money'], ['age_older', __('Over 90 days'), 'money'],
                ['due', __('Total due'), 'money'], ['limit', __('Credit limit'), 'money'],
            ]);
            $document['rows'] = $byCustomer->map(function ($group) use ($bucket): array {
                $customer = $group->first()->customer;
                $row = ['name' => $customer->name, 'phone' => $customer->phone, 'invoices' => $group->count(), 'oldest' => $group->max(fn ($sale) => $sale->ageInDays()),
                    'age_30' => 0.0, 'age_60' => 0.0, 'age_90' => 0.0, 'age_older' => 0.0, 'due' => (float) $group->sum('due_amount'),
                    'limit' => $customer->credit_limit === null ? null : (float) $customer->credit_limit,
                    '_links' => ['invoices' => $this->invoiceLink($group->count(), __('Unpaid invoices of :name', ['name' => $customer->name]), ['due' => true, 'customer' => $customer->id])]];

                foreach ($group as $sale) {
                    $row[$bucket($sale->ageInDays())] += (float) $sale->due_amount;
                }

                return $row;
            })->sortByDesc('due')->values()->all();
            $document['totals'] = ['name' => __('Total'), 'invoices' => $sales->count(), 'due' => (float) $sales->sum('due_amount'),
                '_links' => ['invoices' => $this->invoiceLink($sales->count(), __('Unpaid invoices'), ['due' => true])]] + $ageing;
        } else {
            $document['columns'] = $this->columns([
                ['invoice', __('Invoice')], ['date', __('Date')], ['due_date', __('Pay by')], ['days', __('Days'), 'int'],
                ['total', __('Invoice total'), 'money'], ['paid', __('Paid'), 'money'], ['due', __('Due'), 'money'],
            ]);
            $rows = [];

            foreach ($byCustomer as $group) {
                $customer = $group->first()->customer;
                $rows[] = ['_style' => 'group', '_label' => trim($customer->name.($customer->phone ? '  ·  '.$customer->phone : '').($customer->credit_limit !== null ? '  ·  '.__('Credit limit').' '.money($customer->credit_limit) : ''))];

                foreach ($group as $sale) {
                    $rows[] = [
                        'invoice' => $sale->invoice_no, 'date' => $sale->sold_at->format('d/m/Y'),
                        'due_date' => $sale->due_date?->format('d/m/Y').($sale->isOverdue() ? ' ('.__('overdue').')' : ''),
                        'days' => $sale->ageInDays(), 'total' => (float) $sale->total,
                        'paid' => round((float) $sale->total - (float) $sale->due_amount, 2), 'due' => (float) $sale->due_amount,
                        '_links' => ['invoice' => ['sale' => $sale->id]],
                    ];

                    if ($detail === 'items') {
                        foreach ($sale->items as $item) {
                            $rows[] = ['_style' => 'item', '_label' => sprintf('%s%s  ×  %s  @  %s  =  %s',
                                $item->product_name, $item->unit_quantity > 1 ? ' ('.$item->unit_name.')' : '',
                                self::display($item->quantity, 'qty'), money($item->unit_price), money($item->line_total))];
                        }
                    }
                }

                $rows[] = ['_style' => 'subtotal', 'invoice' => __('Total for :name', ['name' => $customer->name]),
                    'total' => (float) $group->sum('total'), 'paid' => round((float) $group->sum('total') - (float) $group->sum('due_amount'), 2), 'due' => (float) $group->sum('due_amount')];
            }

            $document['rows'] = $rows;
            $document['totals'] = ['invoice' => __('Grand total'), 'total' => (float) $sales->sum('total'),
                'paid' => round((float) $sales->sum('total') - (float) $sales->sum('due_amount'), 2), 'due' => (float) $sales->sum('due_amount')];
            $document['sections'] = [['title' => __('Ageing of due'), 'lines' => [
                [__('0–30 days'), $ageing['age_30'], 'money', ''],
                [__('31–60 days'), $ageing['age_60'], 'money', ''],
                [__('61–90 days'), $ageing['age_90'], 'money', ''],
                [__('Over 90 days'), $ageing['age_older'], 'money', ''],
                [__('Total due'), (float) $sales->sum('due_amount'), 'money', 'total'],
            ]]];
        }

        $overdue = (float) $sales->filter->isOverdue()->sum('due_amount');
        $document['notes'][] = __(':customers customers owe :amount on :invoices invoices.', [
            'customers' => $byCustomer->count(), 'amount' => money((float) $sales->sum('due_amount')), 'invoices' => $sales->count(),
        ]);

        if ($overdue > 0) {
            $document['notes'][] = __('Overdue (past the pay-by date): :amount.', ['amount' => money($overdue)]);
        }

        $document['notes'][] = __('Days are counted from the invoice date.');

        return $document;
    }

    private function dueCollections(array $document, ?int $branchId, CarbonInterface $from, CarbonInterface $to, ReportFilters $filters): array
    {
        $collections = $this->reports->dueCollections($branchId, $from, $to, $filters);
        $document['columns'] = $this->columns([
            ['receipt', __('Receipt no')], ['date', __('Date')], ['customer', __('Customer')], ['invoices', __('Against invoices')],
            ['method', __('Received by')], ['user', __('Staff')], ['amount', __('Amount'), 'money'],
        ]);
        $document['rows'] = $collections->map(fn ($collection): array => [
            'receipt' => $collection->receipt_no, 'date' => $collection->paid_at->format('d/m/Y h:i A'),
            'customer' => $collection->customer->name, 'invoices' => $collection->allocations->map(fn ($payment) => $payment->payable?->invoice_no)->filter()->implode(', '),
            'method' => $collection->paymentMethod->localName().($collection->transaction_ref ? ' ('.$collection->transaction_ref.')' : ''),
            'user' => $collection->user?->name, 'amount' => (float) $collection->amount,
        ])->all();
        $document['totals'] = ['receipt' => __('Total'), 'amount' => (float) $collections->sum('amount')];

        $byMethod = $collections->groupBy(fn ($collection) => $collection->paymentMethod->localName())->map->sum('amount')->sortDesc();
        $document['sections'] = [['title' => __('Summary by payment method'), 'lines' => $byMethod->map(fn ($amount, $name) => [$name, (float) $amount, 'money', ''])->values()->all()]];

        return $document;
    }

    private function stockValuation(array $document, ?int $branchId, ReportFilters $filters): array
    {
        $rows = $this->reports->stockValuation($branchId, $filters);
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

    private function deadStock(array $document, ?int $branchId, int $days, ReportFilters $filters): array
    {
        $rows = $this->reports->deadStock($branchId, $days, $filters);
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
