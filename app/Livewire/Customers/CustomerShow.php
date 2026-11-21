<?php

namespace App\Livewire\Customers;

use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Services\CurrentBranch;
use App\Services\CustomerDueService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class CustomerShow extends Component
{
    public Customer $customer;

    public bool $showCollect = false;

    public string $collectAmount = '';

    public ?int $collectMethodId = null;

    /** Empty = oldest invoices first. */
    public string $collectSaleId = '';

    public string $collectRef = '';

    public string $collectNote = '';

    public function mount(): void
    {
        $this->collectMethodId = PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->value('id');

        /* Links such as "Collect" in the customer list open the form straight away. */
        if (request()->boolean('collect') && auth()->user()->can('customers.collect-due') && $this->customer->totalDue() > 0) {
            $this->openCollect();
        }
    }

    public function openCollect(?int $saleId = null): void
    {
        $this->authorize('customers.collect-due');
        $this->collectSaleId = $saleId ? (string) $saleId : '';
        $this->collectAmount = (string) ($saleId
            ? (float) $this->customer->dueSales()->whereKey($saleId)->value('due_amount')
            : $this->customer->totalDue());
        $this->showCollect = true;
    }

    public function collect(CustomerDueService $dues): void
    {
        $this->authorize('customers.collect-due');
        $this->validate([
            'collectAmount' => ['required', 'numeric', 'gt:0'],
            'collectMethodId' => ['required', 'exists:payment_methods,id'],
            'collectSaleId' => ['nullable', 'integer'],
            'collectRef' => ['nullable', 'string', 'max:100'],
            'collectNote' => ['nullable', 'string', 'max:255'],
        ], [], ['collectAmount' => __('amount')]);

        $receipt = $dues->collect(
            app(CurrentBranch::class)->get(),
            $this->customer,
            (float) $this->collectAmount,
            PaymentMethod::query()->findOrFail($this->collectMethodId),
            $this->collectSaleId !== '' ? (int) $this->collectSaleId : null,
            $this->collectRef ?: null,
            $this->collectNote ?: null,
        );

        $this->reset('showCollect', 'collectAmount', 'collectSaleId', 'collectRef', 'collectNote');
        $this->dispatch('toast', message: __('Received :amount. Money receipt :no.', ['amount' => money($receipt->amount), 'no' => $receipt->receipt_no]));
        $this->dispatch('print-due-receipt', url: route('due-receipts.show', $receipt));
    }

    public function render(): View
    {
        $dueSales = $this->customer->dueSales()->with('branch')->get();
        $totalDue = round((float) $dueSales->sum('due_amount'), 2);

        return view('livewire.customers.customer-show', [
            'sales' => $this->customer->sales()->with('items')->where('status', '!=', SaleStatus::Held)->latest('sold_at')->limit(50)->get(),
            'repairs' => $this->customer->repairJobs()->latest()->limit(50)->get(),
            'totalSpent' => $this->customer->sales()->where('status', SaleStatus::Completed)->sum('total'),
            'dueSales' => $dueSales,
            'totalDue' => $totalDue,
            'overdue' => round((float) $dueSales->filter->isOverdue()->sum('due_amount'), 2),
            'collections' => $this->customer->customerPayments()->with(['paymentMethod', 'user'])->latest('paid_at')->limit(20)->get(),
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'statementUrl' => route('reports.export', ['tab' => 'customer-dues', 'format' => 'pdf', 'detail' => 'items', 'customer' => $this->customer->id, 'from' => today()->toDateString(), 'to' => today()->toDateString(), 'scope' => 'all']),
        ])->title($this->customer->name);
    }
}
