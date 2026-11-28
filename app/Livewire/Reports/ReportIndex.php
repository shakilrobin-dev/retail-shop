<?php

namespace App\Livewire\Reports;

use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Referrer;
use App\Models\Sale;
use App\Models\User;
use App\Services\CategoryTree;
use App\Services\CurrentBranch;
use App\Services\ReportDocument;
use App\Services\ReportFilters;
use App\Services\ReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

class ReportIndex extends Component
{
    #[Url]
    public string $tab = 'summary';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    /** 'current' or 'all' (admin only) */
    #[Url]
    public string $scope = 'current';

    #[Url]
    public int $deadDays = 60;

    /** Category report: add sub-categories into their main category. */
    #[Url]
    public bool $mainCategories = false;

    /** Customer due report: 'summary', 'invoices' or 'items'. */
    #[Url]
    public string $dueDetail = 'summary';

    /** Customer due report for one customer (old links); moved into the customer filter on load. */
    #[Url]
    public ?int $customer = null;

    /**
     * Chosen filters: key (see ReportFilters::KEYS) => list of ids. Empty = all.
     *
     * @var array<string, list<int|string>>
     */
    #[Url]
    public array $filters = [];

    public bool $showInvoiceList = false;

    /**
     * The invoices shown in the pop-up: a title and the criteria of ReportService::invoicesFor().
     *
     * @var array{title?: string, due?: bool, day?: string, user?: int|null, referrer?: int|null, customer?: int|null, with_referrer?: bool}
     */
    public array $invoiceList = [];

    /** How many invoices the pop-up shows (more load with "Show more"). */
    public int $invoiceListLimit = 50;

    public bool $showInvoice = false;

    public ?int $viewingSaleId = null;

    public function mount(): void
    {
        $this->authorize('reports.view');

        if ($this->customer) {
            $this->filters['customers'] = [$this->customer];
            $this->customer = null;
        }

        $this->from = $this->from ?: today()->startOfMonth()->toDateString();
        $this->to = $this->to ?: today()->toDateString();
    }

    public function setPeriod(string $period): void
    {
        [$from, $to] = match ($period) {
            'today' => [today(), today()],
            'yesterday' => [today()->subDay(), today()->subDay()],
            'week' => [today()->startOfWeek(Carbon::SATURDAY), today()],
            'month' => [today()->startOfMonth(), today()],
            'last-month' => [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()],
            'year' => [today()->startOfYear(), today()],
            default => [Carbon::parse($this->from), Carbon::parse($this->to)],
        };

        $this->from = $from->toDateString();
        $this->to = $to->toDateString();
    }

    public function clearFilters(): void
    {
        $this->filters = [];
    }

    /**
     * Open the pop-up with the invoices behind an invoice count on the report.
     *
     * @param  array<string, mixed>  $link
     */
    public function openInvoices(array $link): void
    {
        $this->authorize('reports.view');

        $list = ['title' => mb_substr((string) ($link['title'] ?? __('Invoices')), 0, 200)];

        foreach (['due', 'with_referrer'] as $key) {
            if (! empty($link[$key])) {
                $list[$key] = true;
            }
        }

        if (isset($link['day']) && is_string($link['day']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $link['day'])) {
            $list['day'] = $link['day'];
        }

        foreach (['user', 'referrer', 'customer'] as $key) {
            if (array_key_exists($key, $link)) {
                $list[$key] = $link[$key] === null ? null : (int) $link[$key];
            }
        }

        $this->invoiceList = $list;
        $this->invoiceListLimit = 50;
        $this->showInvoiceList = true;
    }

    public function showMoreInvoices(): void
    {
        $this->invoiceListLimit += 50;
    }

    /**
     * Open one invoice in a pop-up. Only invoices of the branches this report covers.
     */
    public function viewInvoice(int $saleId): void
    {
        $this->authorize('reports.view');

        $sale = Sale::query()->whereKey($saleId)->where('status', '!=', SaleStatus::Held)->firstOrFail();
        $branchId = $this->branchId();
        /** @var User $user */
        $user = Auth::user();

        abort_unless($branchId === null ? $user->canAccessAllBranches() : $sale->branch_id === $branchId, 404);

        $this->viewingSaleId = $sale->id;
        $this->showInvoice = true;
    }

    public function branchId(): ?int
    {
        /** @var User $user */
        $user = Auth::user();

        return $this->scope === 'all' && $user->canAccessAllBranches() ? null : app(CurrentBranch::class)->get()?->id;
    }

    public function render(): View
    {
        [$from, $to] = $this->period();
        $branchId = $this->branchId();
        $tab = $this->activeTab();
        $chosen = ReportFilters::from($this->filters, ReportDocument::FILTERS[$tab]);
        $document = app(ReportDocument::class)->build($tab, $branchId, $from, $to, [
            'main_categories' => $this->mainCategories,
            'dead_days' => $this->deadDays,
            'due_detail' => $this->dueDetail,
            'filters' => $chosen->ids,
        ]);

        /** @var User $user */
        $user = Auth::user();

        $exportParams = ['tab' => $tab, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'scope' => $this->scope, 'deadDays' => $this->deadDays, 'main' => $this->mainCategories ? 1 : 0, 'detail' => $this->dueDetail, 'f' => $chosen->ids];

        return view('livewire.reports.report-index', [
            'document' => $document,
            'activeTab' => $tab,
            'filterOptions' => $this->filterOptions($tab, $chosen),
            'activeFilterCount' => count($chosen->ids),
            'canSeeAll' => $user->canAccessAllBranches() && Branch::query()->count() > 1,
            'exportUrls' => collect(['pdf', 'xlsx', 'csv'])->mapWithKeys(fn (string $format) => [$format => route('reports.export', $exportParams + ['format' => $format])])->all(),
            'invoices' => $this->showInvoiceList ? $this->invoiceListData($branchId, $from, $to, $chosen) : null,
            'viewingSale' => $this->showInvoice && $this->viewingSaleId
                ? Sale::query()->with(['items.product.units', 'payments.paymentMethod', 'customer', 'user', 'branch', 'referrer'])->find($this->viewingSaleId)
                : null,
        ])->title(__('Reports'));
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function period(): array
    {
        $from = Carbon::parse($this->from ?: today());
        $to = Carbon::parse($this->to ?: today());

        return $to->lt($from) ? [$to, $from] : [$from, $to];
    }

    private function activeTab(): string
    {
        return array_key_exists($this->tab, ReportDocument::REPORTS) ? $this->tab : 'summary';
    }

    /**
     * The invoices for the pop-up, with their count and totals.
     *
     * @return array{rows: Collection<int, Sale>, count: int, total: float, due: float, period: string|null}
     */
    private function invoiceListData(?int $branchId, Carbon $from, Carbon $to, ReportFilters $filters): array
    {
        $criteria = $this->invoiceList;
        unset($criteria['title']);
        $query = app(ReportService::class)->invoicesFor($branchId, $from, $to, $filters, $criteria);
        $totals = (clone $query)->reorder()->toBase()->selectRaw('COUNT(*) as invoices, SUM(sales.total) as total, SUM(sales.due_amount) as due')->first();

        return [
            'rows' => $query->with(['customer', 'user'])->withCount('items')->limit($this->invoiceListLimit)->get(),
            'count' => (int) ($totals->invoices ?? 0),
            'total' => (float) ($totals->total ?? 0),
            'due' => (float) ($totals->due ?? 0),
            'period' => ($criteria['due'] ?? false) || isset($criteria['day']) ? null : __('From :from to :to', ['from' => $from->format('d M Y'), 'to' => $to->format('d M Y')]),
        ];
    }

    /**
     * Choices for each filter the report uses. Sub-categories and products follow the chosen categories and brands.
     *
     * @return array<string, array{label: string, options: list<array{id: int, label: string, hint: string|null}>}>
     */
    private function filterOptions(string $tab, ReportFilters $chosen): array
    {
        $tree = app(CategoryTree::class);
        $labels = [
            'categories' => __('Category'), 'subcategories' => __('Sub-category'), 'brands' => __('Brand'), 'products' => __('Product'),
            'referrers' => __('Reference'), 'customers' => __('Customer'), 'staff' => __('Staff'),
            'payment_methods' => __('Payment method'), 'expense_categories' => __('Expense category'),
        ];
        $option = fn (int $id, string $label, ?string $hint = null): array => ['id' => $id, 'label' => $label, 'hint' => $hint ?: null];
        $options = [];

        foreach (ReportDocument::FILTERS[$tab] as $key) {
            $options[$key] = ['label' => $labels[$key], 'options' => match ($key) {
                'categories' => Category::query()->whereNull('parent_id')->orderBy('name')->get()->map(fn (Category $category) => $option($category->id, $category->localName()))->all(),
                'subcategories' => $tree->flat()
                    ->filter(fn (array $row): bool => $row['depth'] > 0)
                    ->filter(fn (array $row): bool => $chosen->get('categories') === [] || collect($chosen->get('categories'))->contains(fn (int $mainId): bool => in_array($row['category']->id, $tree->withDescendants($mainId), true)))
                    ->map(fn (array $row) => $option($row['category']->id, $row['category']->localName(), $row['path']))->values()->all(),
                'brands' => Brand::query()->orderBy('name')->get()->map(fn (Brand $brand) => $option($brand->id, $brand->name))->all(),
                'products' => (new ReportFilters(array_intersect_key($chosen->ids, array_flip(['categories', 'subcategories', 'brands']))))
                    ->onProducts(Product::query(), 'products.id')->orderBy('name')->get(['id', 'name', 'name_bn', 'sku'])
                    ->map(fn (Product $product) => $option($product->id, $product->localName(), $product->sku))->all(),
                'referrers' => Referrer::query()->orderBy('name')->get(['id', 'name', 'phone'])->map(fn (Referrer $referrer) => $option($referrer->id, $referrer->name, $referrer->phone))->all(),
                'customers' => Customer::query()
                    ->when($tab === 'customer-dues', fn ($query) => $query->whereHas('sales', fn ($sales) => $sales->where('status', SaleStatus::Completed)->where('due_amount', '>', 0)))
                    ->orderBy('name')->get(['id', 'name', 'phone'])->map(fn (Customer $customer) => $option($customer->id, $customer->name, $customer->phone))->all(),
                'staff' => User::query()->orderBy('name')->get(['id', 'name', 'username'])->map(fn (User $user) => $option($user->id, $user->name, $user->username))->all(),
                'payment_methods' => PaymentMethod::query()->orderBy('sort_order')->get()->map(fn (PaymentMethod $method) => $option($method->id, $method->localName()))->all(),
                'expense_categories' => ExpenseCategory::query()->orderBy('name')->get()->map(fn (ExpenseCategory $category) => $option($category->id, $category->localName()))->all(),
            }];
        }

        return $options;
    }
}
