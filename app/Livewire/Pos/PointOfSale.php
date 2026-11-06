<?php

namespace App\Livewire\Pos;

use App\Enums\DiscountType;
use App\Enums\PaymentMethodType;
use App\Enums\SaleStatus;
use App\Livewire\Concerns\SearchesProducts;
use App\Livewire\Concerns\ShowsStockErrors;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\CashShiftService;
use App\Services\CurrentBranch;
use App\Services\PaymentService;
use App\Services\SaleService;
use App\Services\Settings;
use App\Services\StockService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class PointOfSale extends Component
{
    use SearchesProducts, ShowsStockErrors;

    /**
     * @var list<array{product_unit_id: int, product_id: int, name: string, units: array<int, array{name: string, price: float, quantity: float}>, quantity: string, unit_price: string, discount_amount: string, stock: float}>
     */
    public array $cart = [];

    public ?int $customerId = null;

    public string $customerSearch = '';

    public string $newCustomerName = '';

    public string $discountType = 'amount';

    public string $discountValue = '0';

    /** @var list<array{payment_method_id: int, amount: string, transaction_ref: string}> */
    public array $payments = [];

    public string $note = '';

    public string $holdNote = '';

    public bool $showPayment = false;

    public ?int $completedSaleId = null;

    public string $openingCash = '0';

    public function mount(): void
    {
        $this->authorize('pos.sell');
    }

    public function pickUnit(int $productUnitId): void
    {
        $unit = ProductUnit::query()->with('product.units')->findOrFail($productUnitId);
        $this->completedSaleId = null;

        foreach ($this->cart as $index => $line) {
            if ($line['product_unit_id'] === $unit->id) {
                $this->cart[$index]['quantity'] = (string) ((float) $line['quantity'] + 1);
                $this->moveLineToTop($index);

                return;
            }
        }

        $product = $unit->product;
        $branch = app(CurrentBranch::class)->get();

        array_unshift($this->cart, [
            'product_unit_id' => $unit->id,
            'product_id' => $product->id,
            'name' => $product->localName(),
            'units' => $product->units->mapWithKeys(fn (ProductUnit $u): array => [$u->id => [
                'name' => $u->localName(),
                'price' => (string) $u->selling_price,
                'quantity' => (float) $u->quantity,
            ]])->all(),
            'quantity' => '1',
            'unit_price' => (string) $unit->selling_price,
            'discount_amount' => '0',
            'stock' => app(StockService::class)->quantity($branch, $product),
        ]);
    }

    /**
     * Unit (piece/box) changed on a cart line: use that unit's price.
     */
    public function updatedCart(mixed $value, string $key): void
    {
        [$index, $field] = explode('.', $key) + [1 => null];

        if ($field === 'product_unit_id' && isset($this->cart[$index])) {
            $unitId = (int) $value;
            $this->cart[$index]['product_unit_id'] = $unitId;
            $this->cart[$index]['unit_price'] = (string) ($this->cart[$index]['units'][$unitId]['price'] ?? $this->cart[$index]['unit_price']);
        }

        if ($field === 'unit_price' && ! $this->canEditPrice()) {
            $unitId = $this->cart[$index]['product_unit_id'];
            $this->cart[$index]['unit_price'] = (string) $this->cart[$index]['units'][$unitId]['price'];
        }
    }

    public function increment(int $index): void
    {
        if (isset($this->cart[$index])) {
            $this->cart[$index]['quantity'] = (string) ((float) $this->cart[$index]['quantity'] + 1);
        }
    }

    public function decrement(int $index): void
    {
        if (isset($this->cart[$index]) && (float) $this->cart[$index]['quantity'] > 1) {
            $this->cart[$index]['quantity'] = (string) ((float) $this->cart[$index]['quantity'] - 1);
        }
    }

    public function removeLine(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    public function clearCart(): void
    {
        $this->reset('cart', 'customerId', 'customerSearch', 'discountType', 'discountValue', 'payments', 'note', 'holdNote', 'showPayment');
    }

    private function moveLineToTop(int $index): void
    {
        $line = $this->cart[$index];
        unset($this->cart[$index]);
        array_unshift($this->cart, $line);
        $this->cart = array_values($this->cart);
    }

    // ----- Customer -----

    public function selectCustomer(int $customerId): void
    {
        $this->customerId = $customerId;
        $this->customerSearch = '';
    }

    public function createCustomer(): void
    {
        $this->validate([
            'customerSearch' => ['required', 'string', 'max:30', 'unique:customers,phone'],
            'newCustomerName' => ['required', 'string', 'max:255'],
        ], [], ['customerSearch' => __('phone'), 'newCustomerName' => __('name')]);

        $customer = Customer::query()->create(['name' => $this->newCustomerName, 'phone' => trim($this->customerSearch)]);
        $this->customerId = $customer->id;
        $this->reset('customerSearch', 'newCustomerName');
    }

    // ----- Hold -----

    public function hold(SaleService $sales): void
    {
        if ($this->cart === []) {
            return;
        }

        $sales->hold(app(CurrentBranch::class)->get(), $this->cartForService(), $this->holdNote, $this->customerId);
        $this->clearCart();
        session()->flash('success', __('Sale put on hold.'));
    }

    public function resume(int $saleId, SaleService $sales): void
    {
        $sale = Sale::query()->with('items')
            ->where('branch_id', app(CurrentBranch::class)->get()?->id)
            ->where('status', SaleStatus::Held)
            ->findOrFail($saleId);

        if ($this->cart !== []) {
            $sales->hold(app(CurrentBranch::class)->get(), $this->cartForService(), $this->holdNote, $this->customerId);
        }

        $this->clearCart();
        $data = $sales->resume($sale);
        $this->customerId = $data['customer_id'];

        foreach (array_reverse($data['cart']) as $row) {
            $this->pickUnit($row['product_unit_id']);
            $this->cart[0]['quantity'] = (string) $row['quantity'];
            $this->cart[0]['unit_price'] = (string) $row['unit_price'];
            $this->cart[0]['discount_amount'] = (string) $row['discount_amount'];
        }
    }

    // ----- Payment -----

    public function openPayment(): void
    {
        if ($this->cart === []) {
            return;
        }

        $this->showPayment = true;

        if ($this->payments === []) {
            $cash = PaymentMethod::query()->where('is_active', true)->where('type', PaymentMethodType::Cash)->value('id');
            $this->payments = [['payment_method_id' => $cash ?? PaymentMethod::query()->value('id'), 'amount' => '', 'transaction_ref' => '']];
        }

        $this->dispatch('focus-payment');
    }

    public function addPayment(int $methodId): void
    {
        $remaining = max(0, $this->totals()['total'] - $this->paidSoFar());
        $this->payments[] = ['payment_method_id' => $methodId, 'amount' => $remaining > 0 ? (string) $remaining : '', 'transaction_ref' => ''];
    }

    public function removePayment(int $index): void
    {
        unset($this->payments[$index]);
        $this->payments = array_values($this->payments);
    }

    public function payExact(int $index): void
    {
        $others = collect($this->payments)->except($index)->sum(fn (array $row): float => (float) $row['amount']);
        $this->payments[$index]['amount'] = (string) max(0, round($this->totals()['total'] - $others, 2));
    }

    public function complete(SaleService $sales): void
    {
        $this->authorize('pos.sell');
        $this->checkDiscountAllowed();

        $this->validate([
            'payments.*.amount' => ['nullable', 'numeric', 'min:0'],
            'payments.*.payment_method_id' => ['required', 'exists:payment_methods,id'],
            'cart.*.quantity' => ['required', 'numeric', 'gt:0'],
            'cart.*.unit_price' => ['required', 'numeric', 'min:0'],
            'cart.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
        ], [], ['cart.*.quantity' => __('quantity'), 'cart.*.unit_price' => __('price')]);

        $sale = $sales->complete(
            app(CurrentBranch::class)->get(),
            $this->cartForService(),
            array_map(fn (array $row): array => [
                'payment_method_id' => (int) $row['payment_method_id'],
                'amount' => $row['amount'] === '' ? 0 : $row['amount'],
                'transaction_ref' => $row['transaction_ref'] ?: null,
            ], $this->payments),
            (float) $this->discountValue > 0 ? DiscountType::from($this->discountType) : null,
            (float) $this->discountValue,
            $this->customerId,
            $this->note,
        );

        $this->clearCart();
        $this->completedSaleId = $sale->id;

        if (app(Settings::class)->get('receipt_print_mode', $sale->branch_id) === 'on') {
            $this->dispatch('print-receipt', url: route('sales.receipt', $sale));
        }
    }

    public function newSale(): void
    {
        $this->completedSaleId = null;
    }

    // ----- Cash register -----

    public function openRegister(CashShiftService $shifts): void
    {
        $this->authorize('cash-shifts.manage');
        $this->validate(['openingCash' => ['required', 'numeric', 'min:0']], [], ['openingCash' => __('opening cash')]);
        $shifts->open(app(CurrentBranch::class)->get(), (float) $this->openingCash);
    }

    // ----- Helpers -----

    public function canEditPrice(): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->can('pos.edit-price') || app(Settings::class)->bool('pos_allow_price_edit', app(CurrentBranch::class)->get()?->id);
    }

    private function checkDiscountAllowed(): void
    {
        /** @var User $user */
        $user = Auth::user();
        $totals = $this->totals();
        $lineDiscounts = collect($this->cart)->sum(fn (array $line): float => (float) $line['discount_amount']);
        $discount = $totals['discount_amount'] + $lineDiscounts;

        if ($discount <= 0 || $user->hasRole('admin')) {
            return;
        }

        if (! $user->can('pos.discount')) {
            throw ValidationException::withMessages(['discountValue' => __('You are not allowed to give discounts.')]);
        }

        $maxPercent = (float) app(Settings::class)->get('pos_max_discount_percent', app(CurrentBranch::class)->get()?->id);
        $gross = $totals['subtotal'] + $lineDiscounts;

        if ($gross > 0 && ($discount / $gross) * 100 > $maxPercent + 0.001) {
            throw ValidationException::withMessages(['discountValue' => __('Maximum discount allowed is :percent%.', ['percent' => $maxPercent + 0])]);
        }
    }

    /**
     * @return list<array{product_unit_id: int, quantity: string, unit_price: string, discount_amount: string}>
     */
    private function cartForService(): array
    {
        return array_map(fn (array $line): array => [
            'product_unit_id' => (int) $line['product_unit_id'],
            'quantity' => $line['quantity'],
            'unit_price' => $line['unit_price'],
            'discount_amount' => $line['discount_amount'] === '' ? '0' : $line['discount_amount'],
        ], $this->cart);
    }

    /**
     * @return array{subtotal: float, discount_amount: float, vat_amount: float, total: float}
     */
    public function totals(): array
    {
        if ($this->cart === []) {
            return ['subtotal' => 0, 'discount_amount' => 0, 'vat_amount' => 0, 'total' => 0];
        }

        try {
            $totals = app(SaleService::class)->calculate(
                app(CurrentBranch::class)->get(),
                $this->cartForService(),
                DiscountType::tryFrom($this->discountType),
                (float) $this->discountValue,
            );
        } catch (ValidationException) {
            return ['subtotal' => 0, 'discount_amount' => 0, 'vat_amount' => 0, 'total' => 0];
        }

        unset($totals['lines']);

        return $totals;
    }

    private function paidSoFar(): float
    {
        return round(collect($this->payments)->sum(fn (array $row): float => (float) $row['amount']), 2);
    }

    public function render(): View
    {
        $branch = app(CurrentBranch::class)->get();
        $settings = app(Settings::class);
        $totals = $this->totals();
        $paid = $this->paidSoFar();

        return view('livewire.pos.point-of-sale', [
            'shift' => $branch ? app(PaymentService::class)->openShift($branch) : null,
            'results' => $this->productResults(),
            'totals' => $totals,
            'paid' => $paid,
            'change' => max(0, round($paid - $totals['total'], 2)),
            'due' => max(0, round($totals['total'] - $paid, 2)),
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'customer' => $this->customerId ? Customer::query()->find($this->customerId) : null,
            'customerResults' => mb_strlen($this->customerSearch) >= 3
                ? Customer::query()->where('phone', 'like', "%{$this->customerSearch}%")->orWhere('name', 'like', "%{$this->customerSearch}%")->limit(5)->get()
                : collect(),
            'heldSales' => Sale::query()->with('customer')->withCount('items')
                ->where('branch_id', $branch?->id)->where('status', SaleStatus::Held)->latest()->get(),
            'completedSale' => $this->completedSaleId ? Sale::query()->find($this->completedSaleId) : null,
            'printMode' => $settings->get('receipt_print_mode', $branch?->id),
            'vatEnabled' => $settings->bool('vat_enabled', $branch?->id),
            'canEditPrice' => $this->canEditPrice(),
            'drawerEnabled' => $settings->bool('cash_drawer_enabled', $branch?->id),
            'lastShift' => $branch ? CashShift::query()->where('branch_id', $branch->id)->latest('closed_at')->first() : null,
            'quickItems' => $this->quickItems(),
        ])->title(__('POS / Sale'));
    }

    /**
     * Best sellers of the last 30 days (or newest products) as tap-to-add tiles.
     *
     * @return Collection<int, ProductUnit>
     */
    private function quickItems(): Collection
    {
        $branchId = app(CurrentBranch::class)->get()?->id;

        $unitIds = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.branch_id', $branchId)
            ->where('sales.status', SaleStatus::Completed)
            ->where('sales.sold_at', '>=', now()->subDays(30))
            ->selectRaw('sale_items.product_unit_id, SUM(sale_items.quantity) as sold')
            ->groupBy('sale_items.product_unit_id')
            ->orderByDesc('sold')
            ->limit(12)
            ->pluck('product_unit_id');

        if ($unitIds->count() < 12) {
            $unitIds = $unitIds->merge(ProductUnit::query()
                ->where('is_base', true)
                ->whereHas('product', fn ($query) => $query->where('is_active', true))
                ->whereNotIn('id', $unitIds)
                ->latest('id')
                ->limit(12 - $unitIds->count())
                ->pluck('id'));
        }

        return ProductUnit::query()->with('product')->whereIn('id', $unitIds)->get()
            ->filter(fn (ProductUnit $unit): bool => (bool) $unit->product?->is_active)
            ->sortBy(fn (ProductUnit $unit): int => $unitIds->search($unit->id))
            ->values();
    }
}
