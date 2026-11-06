<?php

namespace App\Livewire\Purchases;

use App\Livewire\Concerns\SearchesProducts;
use App\Models\PaymentMethod;
use App\Models\ProductUnit;
use App\Models\Supplier;
use App\Services\CurrentBranch;
use App\Services\PurchaseService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class PurchaseForm extends Component
{
    use SearchesProducts;

    public ?int $supplier_id = null;

    public string $supplier_invoice_no = '';

    public string $purchase_date = '';

    public string $discount = '0';

    public string $note = '';

    public ?int $payment_method_id = null;

    public string $transaction_ref = '';

    public bool $paid_from_drawer = false;

    /**
     * @var list<array{product_id: int, product_unit_id: int, name: string, units: array<int, string>, unit_quantities: array<int, float>, quantity: string, unit_cost: string, selling_price: string, new_selling_price: string}>
     */
    public array $items = [];

    public function mount(): void
    {
        $this->purchase_date = now()->toDateString();
        $this->payment_method_id = PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->value('id');
    }

    public function pickUnit(int $productUnitId): void
    {
        $unit = ProductUnit::query()->with('product.units')->findOrFail($productUnitId);

        foreach ($this->items as $index => $item) {
            if ($item['product_unit_id'] === $unit->id) {
                $this->items[$index]['quantity'] = (string) ((float) $item['quantity'] + 1);

                return;
            }
        }

        $product = $unit->product;

        $this->items[] = [
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'name' => $product->name,
            'units' => $product->units->mapWithKeys(fn ($u) => [$u->id => $u->name])->all(),
            'unit_quantities' => $product->units->mapWithKeys(fn ($u) => [$u->id => (float) $u->quantity])->all(),
            'quantity' => '1',
            'unit_cost' => (string) round((float) $product->cost_price * (float) $unit->quantity, 2),
            'selling_price' => (string) $unit->selling_price,
            'new_selling_price' => '',
        ];
    }

    /**
     * When the unit (piece/box) of a line changes, adjust the cost suggestion.
     */
    public function updatedItems(mixed $value, string $key): void
    {
        [$index, $field] = explode('.', $key) + [1 => null];

        if ($field !== 'product_unit_id' || ! isset($this->items[$index])) {
            return;
        }

        $unit = ProductUnit::query()->with('product')->find((int) $value);

        if ($unit) {
            $this->items[$index]['product_unit_id'] = $unit->id;
            $this->items[$index]['unit_cost'] = (string) round((float) $unit->product->cost_price * (float) $unit->quantity, 2);
            $this->items[$index]['selling_price'] = (string) $unit->selling_price;
        }
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function subtotal(): float
    {
        return round(collect($this->items)->sum(fn (array $item): float => (float) $item['quantity'] * (float) $item['unit_cost']), 2);
    }

    public function total(): float
    {
        return max(0, round($this->subtotal() - (float) $this->discount, 2));
    }

    public function save(PurchaseService $purchases, CurrentBranch $currentBranch): void
    {
        $this->authorize('purchases.manage');

        $this->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'supplier_invoice_no' => ['nullable', 'string', 'max:50'],
            'purchase_date' => ['required', 'date', 'before_or_equal:today'],
            'discount' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:2000'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'transaction_ref' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.new_selling_price' => ['nullable', 'numeric', 'min:0'],
        ], [], [
            'items.*.quantity' => __('quantity'),
            'items.*.unit_cost' => __('cost'),
            'items' => __('products'),
        ]);

        $purchase = $purchases->create(
            $currentBranch->get(),
            [
                'supplier_id' => $this->supplier_id,
                'supplier_invoice_no' => $this->supplier_invoice_no,
                'purchase_date' => $this->purchase_date,
                'discount' => $this->discount,
                'note' => $this->note,
            ],
            array_map(fn (array $item): array => [
                'product_unit_id' => (int) $item['product_unit_id'],
                'quantity' => $item['quantity'],
                'unit_cost' => $item['unit_cost'],
                'new_selling_price' => $item['new_selling_price'],
            ], $this->items),
            PaymentMethod::query()->findOrFail($this->payment_method_id),
            $this->transaction_ref,
            $this->paid_from_drawer,
        );

        session()->flash('success', __('Purchase :no saved. Stock updated.', ['no' => $purchase->reference_no]));
        $this->redirectRoute('purchases.show', $purchase, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.purchases.purchase-form', [
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'results' => $this->productResults(),
        ])->title(__('New purchase'));
    }
}
