<?php

namespace App\Livewire\Purchases;

use App\Livewire\Concerns\ShowsStockErrors;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Services\CurrentBranch;
use App\Services\PurchaseService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class PurchaseShow extends Component
{
    use ShowsStockErrors;

    public Purchase $purchase;

    public bool $showReturn = false;

    /** @var array<int, string> purchase_item_id => base quantity */
    public array $returns = [];

    public ?int $refund_method_id = null;

    public string $return_note = '';

    public bool $into_drawer = false;

    public function mount(Purchase $purchase): void
    {
        abort_unless($purchase->branch_id === app(CurrentBranch::class)->get()?->id, 404);

        $this->purchase = $purchase;
        $this->refund_method_id = PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->value('id');
    }

    public function saveReturn(PurchaseService $purchases): void
    {
        $this->authorize('purchases.manage');

        $this->validate([
            'returns.*' => ['nullable', 'numeric', 'min:0'],
            'refund_method_id' => ['required', 'exists:payment_methods,id'],
            'return_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $purchaseReturn = $purchases->return(
            $this->purchase,
            array_filter($this->returns, fn ($quantity) => (float) $quantity > 0),
            PaymentMethod::query()->findOrFail($this->refund_method_id),
            $this->return_note ?: null,
            $this->into_drawer,
        );

        session()->flash('success', __('Return :no saved. Stock reduced.', ['no' => $purchaseReturn->reference_no]));
        $this->redirectRoute('purchases.show', $this->purchase, navigate: true);
    }

    public function render(): View
    {
        $this->purchase->load(['supplier', 'user', 'items.product.units', 'items.productUnit', 'payments.paymentMethod', 'returns.items', 'returns.user']);

        return view('livewire.purchases.purchase-show', [
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ])->title(__('Purchase').' '.$this->purchase->reference_no);
    }
}
