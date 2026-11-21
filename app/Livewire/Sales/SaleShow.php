<?php

namespace App\Livewire\Sales;

use App\Enums\SaleStatus;
use App\Livewire\Concerns\ShowsStockErrors;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Services\CurrentBranch;
use App\Services\CustomerDueService;
use App\Services\SaleService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class SaleShow extends Component
{
    use ShowsStockErrors;

    public Sale $sale;

    public bool $showReturn = false;

    /** @var array<int, array{quantity: string, restock: bool}> */
    public array $returns = [];

    public ?int $refund_method_id = null;

    public string $return_reason = '';

    public bool $showVoid = false;

    public string $void_reason = '';

    public bool $showCollect = false;

    public string $collect_amount = '';

    public ?int $collect_method_id = null;

    public string $collect_ref = '';

    public function mount(Sale $sale): void
    {
        abort_unless($sale->branch_id === app(CurrentBranch::class)->get()?->id && $sale->status !== SaleStatus::Held, 404);

        $this->sale = $sale;
        $this->refund_method_id = PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->value('id');
        $this->collect_method_id = $this->refund_method_id;
        $this->collect_amount = $sale->due_amount > 0 ? (string) (float) $sale->due_amount : '';

        foreach ($sale->items as $item) {
            $this->returns[$item->id] = ['quantity' => '', 'restock' => true];
        }
    }

    public function saveReturn(SaleService $sales): void
    {
        $this->authorize('sales.return');

        $this->validate([
            'returns.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'refund_method_id' => ['required', 'exists:payment_methods,id'],
            'return_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $saleReturn = $sales->return(
            $this->sale,
            array_filter($this->returns, fn (array $row): bool => (float) $row['quantity'] > 0),
            PaymentMethod::query()->findOrFail($this->refund_method_id),
            $this->return_reason ?: null,
        );

        $offset = round((float) $saleReturn->total - (float) $saleReturn->refund_amount, 2);
        session()->flash('success', $offset > 0
            ? __('Return :no saved. :offset taken off the due, refund :amount.', ['no' => $saleReturn->reference_no, 'offset' => money($offset), 'amount' => money($saleReturn->refund_amount)])
            : __('Return :no saved. Refund :amount.', ['no' => $saleReturn->reference_no, 'amount' => money($saleReturn->refund_amount)]));
        $this->redirectRoute('sales.show', $this->sale, navigate: true);
    }

    /**
     * Take payment against this invoice's due.
     */
    public function collect(CustomerDueService $dues): void
    {
        $this->authorize('customers.collect-due');
        $this->validate([
            'collect_amount' => ['required', 'numeric', 'gt:0'],
            'collect_method_id' => ['required', 'exists:payment_methods,id'],
            'collect_ref' => ['nullable', 'string', 'max:100'],
        ], [], ['collect_amount' => __('amount')]);

        abort_unless($this->sale->customer, 404);

        $receipt = $dues->collect(
            app(CurrentBranch::class)->get(),
            $this->sale->customer,
            (float) $this->collect_amount,
            PaymentMethod::query()->findOrFail($this->collect_method_id),
            $this->sale->id,
            $this->collect_ref ?: null,
        );

        session()->flash('success', __('Received :amount. Money receipt :no.', ['amount' => money($receipt->amount), 'no' => $receipt->receipt_no]));
        $this->redirectRoute('sales.show', $this->sale, navigate: true);
    }

    public function void(SaleService $sales): void
    {
        $this->authorize('sales.void');
        $this->validate(['void_reason' => ['required', 'string', 'max:255']], [], ['void_reason' => __('reason')]);

        $sales->void($this->sale, $this->void_reason);

        session()->flash('success', __('Sale voided. Stock returned and money refunded.'));
        $this->redirectRoute('sales.show', $this->sale, navigate: true);
    }

    public function render(): View
    {
        $this->sale->load(['items', 'payments.paymentMethod', 'payments.customerPayment', 'customer', 'user', 'voidedBy', 'returns.items.saleItem', 'returns.user']);

        return view('livewire.sales.sale-show', [
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ])->title(__('Invoice').' '.$this->sale->invoice_no);
    }
}
