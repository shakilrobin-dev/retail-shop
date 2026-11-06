<?php

namespace App\Livewire;

use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\RepairJob;
use App\Models\Sale;
use App\Models\User;
use App\Services\CurrentBranch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Search box in the top bar (Ctrl+K): products, invoices, repair jobs, customers and pages.
 */
class GlobalSearch extends Component
{
    public string $query = '';

    /**
     * @return list<array{group: string, title: string, subtitle: string, url: string}>
     */
    public function results(): array
    {
        $term = trim($this->query);

        if (mb_strlen($term) < 2) {
            return [];
        }

        /** @var User $user */
        $user = Auth::user();
        $branchId = app(CurrentBranch::class)->get()?->id;
        $results = [];

        foreach ($this->pages() as [$label, $route, $permission]) {
            if ((! $permission || $user->can($permission)) && str_contains(mb_strtolower($label), mb_strtolower($term))) {
                $results[] = ['group' => __('Pages'), 'title' => $label, 'subtitle' => '', 'url' => route($route)];
            }
        }

        if ($user->can('products.manage') || $user->can('pos.sell')) {
            $products = Product::query()
                ->where(fn (Builder $query) => $query->where('name', 'like', "%{$term}%")
                    ->orWhere('name_bn', 'like', "%{$term}%")
                    ->orWhere('sku', $term)
                    ->orWhereHas('barcodes', fn (Builder $query) => $query->where('code', $term)))
                ->limit(6)->get();

            foreach ($products as $product) {
                $results[] = [
                    'group' => __('Products'),
                    'title' => $product->localName(),
                    'subtitle' => $product->sku,
                    'url' => $user->can('products.manage') ? route('products.edit', $product) : route('stock.movements', ['productId' => $product->id]),
                ];
            }
        }

        if ($user->can('pos.sell')) {
            $sales = Sale::query()->where('branch_id', $branchId)->where('status', '!=', SaleStatus::Held)
                ->where('invoice_no', 'like', "%{$term}%")->latest('id')->limit(5)->get();

            foreach ($sales as $sale) {
                $results[] = ['group' => __('Invoices'), 'title' => $sale->invoice_no, 'subtitle' => money($sale->total), 'url' => route('sales.show', $sale)];
            }

            $customers = Customer::query()->where(fn (Builder $query) => $query->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"))
                ->limit(5)->get();

            foreach ($customers as $customer) {
                $results[] = ['group' => __('Customers'), 'title' => $customer->name, 'subtitle' => (string) $customer->phone, 'url' => route('customers.show', $customer)];
            }
        }

        if ($user->can('repairs.manage')) {
            $jobs = RepairJob::query()->with('customer')->where('branch_id', $branchId)
                ->where(fn (Builder $query) => $query->where('job_no', 'like', "%{$term}%")->orWhere('imei_serial', 'like', "%{$term}%"))
                ->latest('id')->limit(5)->get();

            foreach ($jobs as $job) {
                $results[] = ['group' => __('Repairs'), 'title' => $job->job_no.' · '.trim($job->device_brand.' '.$job->device_model), 'subtitle' => $job->status->label().' · '.$job->customer->name, 'url' => route('repairs.show', $job)];
            }
        }

        return $results;
    }

    /**
     * @return list<array{0: string, 1: string, 2: string|null}>
     */
    private function pages(): array
    {
        return [
            [__('Dashboard'), 'dashboard', null],
            [__('POS / Sale'), 'pos', 'pos.sell'],
            [__('Sales'), 'sales.index', 'pos.sell'],
            [__('Customers'), 'customers.index', 'pos.sell'],
            [__('Cash register'), 'cash-register', 'cash-shifts.manage'],
            [__('Repairs'), 'repairs.index', 'repairs.manage'],
            [__('New repair job'), 'repairs.create', 'repairs.manage'],
            [__('Products'), 'products.index', 'products.manage'],
            [__('New product'), 'products.create', 'products.manage'],
            [__('Categories & brands'), 'catalog.setup', 'products.manage'],
            [__('Barcode labels'), 'labels.index', 'products.manage'],
            [__('Stock'), 'stock.index', 'stock.adjust'],
            [__('Adjust / count stock'), 'stock.adjust', 'stock.adjust'],
            [__('Transfers'), 'stock.transfers.index', 'stock.transfer'],
            [__('Purchases'), 'purchases.index', 'purchases.manage'],
            [__('New purchase'), 'purchases.create', 'purchases.manage'],
            [__('Suppliers'), 'suppliers.index', 'purchases.manage'],
            [__('Expenses'), 'expenses.index', 'expenses.manage'],
            [__('Reports'), 'reports.index', 'reports.view'],
            [__('Settings'), 'settings.general', 'settings.manage'],
            [__('Users'), 'settings.users', 'users.manage'],
        ];
    }

    public function render(): View
    {
        return view('livewire.global-search', ['results' => $this->results()]);
    }
}
