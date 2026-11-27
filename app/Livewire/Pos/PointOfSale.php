<?php

namespace App\Livewire\Pos;

use App\Enums\CommissionType;
use App\Enums\DiscountType;
use App\Enums\PaymentMethodType;
use App\Enums\SaleStatus;
use App\Livewire\Concerns\SearchesProducts;
use App\Livewire\Concerns\ShowsStockErrors;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\ProductUnit;
use App\Models\Referrer;
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
     * A line of a product sold by measurement (tiles by sqft) also has "measure" and "pieces":
     * then "quantity" is the measurement (e.g. 500 sqft), "pieces" the whole pieces given and "unit_price" the rate per measurement.
     *
     * @var list<array{product_unit_id: int, product_id: int, name: string, units: array<int, array{name: string, price: float, quantity: float}>, quantity: string, unit_price: string, discount_amount: string, stock: float, measure?: array{unit: string, per: float, rate: string}|null, pieces?: string}>
     */
    public array $cart = [];

    public ?int $customerId = null;

    /** Reference person who brought this customer (optional). */
    public ?int $referrerId = null;

    public string $referrerSearch = '';

    public string $newReferrerName = '';

    public string $newReferrerPhone = '';

    public string $commissionType = 'profit_percent';

    public string $commissionValue = '';

    public string $customerSearch = '';

    public string $newCustomerName = '';

    public string $discountType = 'amount';

    public string $discountValue = '0';

    /** @var list<array{payment_method_id: int, amount: string, transaction_ref: string}> */
    public array $payments = [];

    public string $note = '';

    /** When the due should be paid (only for sales on due). */
    public string $dueDate = '';

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

        if ($unit->product->isMeasured()) {
            $this->pickMeasured($unit);

            return;
        }

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
     * A product sold by measurement goes on one line per product, counted in pieces of its base unit.
     * Picking a carton adds the pieces of a carton.
     */
    private function pickMeasured(ProductUnit $unit): void
    {
        $product = $unit->product;
        $addPieces = (float) $unit->quantity;

        foreach ($this->cart as $index => $line) {
            if ($line['product_id'] === $product->id && ! empty($line['measure'])) {
                $this->setPieces($index, (float) $line['pieces'] + $addPieces);
                $this->moveLineToTop($index);

                return;
            }
        }

        $baseUnit = $product->units->firstWhere('is_base', true) ?? $unit;
        $rate = $product->measure_price !== null
            ? (float) $product->measure_price
            : round((float) $baseUnit->selling_price / (float) $product->measure_per_unit, 2);

        array_unshift($this->cart, [
            'product_unit_id' => $baseUnit->id,
            'product_id' => $product->id,
            'name' => $product->localName(),
            'units' => $product->units->mapWithKeys(fn (ProductUnit $u): array => [$u->id => [
                'name' => $u->localName(),
                'price' => (string) $u->selling_price,
                'quantity' => (float) $u->quantity,
            ]])->all(),
            'measure' => ['unit' => (string) $product->measure_unit, 'per' => (float) $product->measure_per_unit, 'rate' => (string) $rate],
            'pieces' => (string) $addPieces,
            'quantity' => (string) round($addPieces * (float) $product->measure_per_unit, 3),
            'unit_price' => (string) $rate,
            'discount_amount' => '0',
            'stock' => app(StockService::class)->quantity(app(CurrentBranch::class)->get(), $product),
        ]);
    }

    /**
     * Set the whole pieces of a measured line; the measurement follows (283 pcs = 500.91 sqft).
     */
    private function setPieces(int $index, float $pieces): void
    {
        $pieces = max(0, (int) ceil(round($pieces, 6)));
        $this->cart[$index]['pieces'] = (string) $pieces;
        $this->cart[$index]['quantity'] = (string) round($pieces * $this->cart[$index]['measure']['per'], 3);
    }

    /**
     * Whole pieces needed to cover a measurement, e.g. 500 sqft ÷ 1.77 = 283 pieces.
     */
    private function piecesFor(array $line, float $measure): int
    {
        return $measure > 0 ? (int) ceil(round($measure / $line['measure']['per'], 6)) : 0;
    }

    /**
     * Pieces shown as cartons + pieces, e.g. "28 Carton + 3 Piece".
     *
     * @param  array<string, mixed>  $line
     */
    public function packBreakdown(array $line, float $pieces): string
    {
        $units = collect($line['units']);
        $base = $units->firstWhere('quantity', 1.0);
        $baseName = $base['name'] ?? '';
        $pack = $units->filter(fn (array $unit): bool => $unit['quantity'] > 1)->sortByDesc('quantity')->first();

        if (! $pack || $pieces < $pack['quantity']) {
            return shop_digits($pieces + 0).' '.$baseName;
        }

        $packs = (int) floor($pieces / $pack['quantity']);
        $rest = round($pieces - $packs * $pack['quantity'], 3);
        $text = shop_digits($packs).' '.$pack['name'];

        return $rest > 0 ? $text.' + '.shop_digits($rest + 0).' '.$baseName : $text;
    }

    /**
     * Unit (piece/box) changed on a cart line: use that unit's price.
     */
    public function updatedCart(mixed $value, string $key): void
    {
        [$index, $field] = explode('.', $key) + [1 => null];

        if (isset($this->cart[$index]) && ! empty($this->cart[$index]['measure'])) {
            $this->updatedMeasuredLine((int) $index, (string) $field, $value);

            return;
        }

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

    /**
     * Measurement typed → work out whole pieces. Pieces typed → work out the measurement.
     */
    private function updatedMeasuredLine(int $index, string $field, mixed $value): void
    {
        $line = $this->cart[$index];

        if ($field === 'quantity') {
            $this->cart[$index]['pieces'] = (string) $this->piecesFor($line, (float) $value);
        }

        if ($field === 'pieces') {
            $this->setPieces($index, (float) $value);
        }

        if ($field === 'unit_price' && ! $this->canEditPrice()) {
            $this->cart[$index]['unit_price'] = $line['measure']['rate'];
        }
    }

    public function increment(int $index): void
    {
        if (isset($this->cart[$index]) && ! empty($this->cart[$index]['measure'])) {
            $this->setPieces($index, (float) $this->cart[$index]['pieces'] + 1);

            return;
        }

        if (isset($this->cart[$index])) {
            $this->cart[$index]['quantity'] = (string) ((float) $this->cart[$index]['quantity'] + 1);
        }
    }

    public function decrement(int $index): void
    {
        if (isset($this->cart[$index]) && ! empty($this->cart[$index]['measure'])) {
            if ((float) $this->cart[$index]['pieces'] > 1) {
                $this->setPieces($index, (float) $this->cart[$index]['pieces'] - 1);
            }

            return;
        }

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
        $this->reset('cart', 'customerId', 'customerSearch', 'discountType', 'discountValue', 'payments', 'note', 'holdNote', 'showPayment', 'dueDate');
        $this->clearReferrer();
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

    // ----- Reference person -----

    public function selectReferrer(int $referrerId): void
    {
        $referrer = Referrer::query()->findOrFail($referrerId);
        $this->referrerId = $referrer->id;
        $this->referrerSearch = '';
        $this->commissionType = CommissionType::ProfitPercent->value;
        $this->commissionValue = $referrer->commission_percent !== null ? (string) ($referrer->commission_percent + 0) : '';
        $this->resetValidation(['newReferrerName', 'newReferrerPhone', 'commissionValue']);
    }

    public function createReferrer(): void
    {
        $this->validate([
            'newReferrerName' => ['required', 'string', 'max:255'],
            'newReferrerPhone' => ['nullable', 'string', 'max:30'],
        ], [], ['newReferrerName' => __('name'), 'newReferrerPhone' => __('phone')]);

        $referrer = Referrer::query()->create([
            'name' => trim($this->newReferrerName),
            'phone' => trim($this->newReferrerPhone) ?: null,
        ]);

        $this->selectReferrer($referrer->id);
        $this->reset('newReferrerName', 'newReferrerPhone');
    }

    public function clearReferrer(): void
    {
        $this->reset('referrerId', 'referrerSearch', 'newReferrerName', 'newReferrerPhone', 'commissionType', 'commissionValue');
    }

    /**
     * Typing in the reference search fills the "new reference" form (phone if it is a number).
     */
    public function updatedReferrerSearch(string $value): void
    {
        $value = trim($value);
        $isPhone = preg_match('/^[0-9+\-\s]+$/', $value) === 1;
        $this->newReferrerPhone = $isPhone ? $value : '';
        $this->newReferrerName = $isPhone ? '' : $value;
    }

    public function canManageCommission(): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->can('commissions.manage');
    }

    /**
     * Reference and commission for the sale. Staff who may not set commission get the person's default %.
     *
     * @return array{referrer_id: int, commission_type: CommissionType|null, commission_value: float}|null
     */
    private function reference(): ?array
    {
        if (! $this->referrerId) {
            return null;
        }

        if ($this->canManageCommission()) {
            return [
                'referrer_id' => $this->referrerId,
                'commission_type' => CommissionType::tryFrom($this->commissionType),
                'commission_value' => (float) $this->commissionValue,
            ];
        }

        $percent = Referrer::query()->whereKey($this->referrerId)->value('commission_percent');

        return ['referrer_id' => $this->referrerId, 'commission_type' => CommissionType::ProfitPercent, 'commission_value' => (float) $percent];
    }

    /**
     * Commission the reference person will get on the current cart.
     */
    public function commissionPreview(): float
    {
        $reference = $this->reference();

        if (! $reference || $this->cart === []) {
            return 0.0;
        }

        try {
            $totals = app(SaleService::class)->calculate(app(CurrentBranch::class)->get(), $this->cartForService(), DiscountType::tryFrom($this->discountType), (float) $this->discountValue);

            return app(SaleService::class)->commission($totals, $reference)['amount'];
        } catch (ValidationException) {
            return 0.0;
        }
    }

    // ----- Hold -----

    public function hold(SaleService $sales): void
    {
        if ($this->cart === []) {
            return;
        }

        $sales->hold(app(CurrentBranch::class)->get(), $this->cartForService(), $this->holdNote, $this->customerId, $this->referrerId);
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
            $sales->hold(app(CurrentBranch::class)->get(), $this->cartForService(), $this->holdNote, $this->customerId, $this->referrerId);
        }

        $this->clearCart();
        $data = $sales->resume($sale);
        $this->customerId = $data['customer_id'];

        if ($data['referrer_id']) {
            $this->selectReferrer($data['referrer_id']);
        }

        foreach (array_reverse($data['cart']) as $row) {
            $this->pickUnit($row['product_unit_id']);

            if (! empty($this->cart[0]['measure'])) {
                $this->setPieces(0, (float) $row['quantity']);
                $this->cart[0]['unit_price'] = (string) ($row['measure_rate'] ?? $this->cart[0]['unit_price']);
            } else {
                $this->cart[0]['quantity'] = (string) $row['quantity'];
                $this->cart[0]['unit_price'] = (string) $row['unit_price'];
            }

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

    /**
     * Customer pays nothing now: the whole bill goes on their due.
     */
    public function allOnDue(): void
    {
        foreach ($this->payments as $index => $row) {
            $this->payments[$index]['amount'] = '';
        }
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
            'dueDate' => ['nullable', 'date', 'after_or_equal:today'],
            'referrerId' => ['nullable', 'exists:referrers,id'],
            'commissionType' => ['required', 'in:'.implode(',', array_column(CommissionType::cases(), 'value'))],
            'commissionValue' => ['nullable', 'numeric', 'min:0'],
        ], [], ['cart.*.quantity' => __('quantity'), 'cart.*.unit_price' => __('price'), 'dueDate' => __('due date'), 'commissionValue' => __('commission')]);

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
            $this->dueDate ?: null,
            $this->reference(),
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
     * Measured lines go to the service as whole pieces with their rate per measurement.
     *
     * @return list<array{product_unit_id: int, quantity: string, unit_price: string, discount_amount: string, measure_rate?: string}>
     */
    private function cartForService(): array
    {
        return array_map(function (array $line): array {
            $row = [
                'product_unit_id' => (int) $line['product_unit_id'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'discount_amount' => $line['discount_amount'] === '' ? '0' : $line['discount_amount'],
            ];

            if (! empty($line['measure'])) {
                $row['quantity'] = (string) $line['pieces'];
                $row['unit_price'] = (string) round((float) $line['unit_price'] * $line['measure']['per'], 2);
                $row['measure_rate'] = $line['unit_price'] === '' ? '0' : $line['unit_price'];
            }

            return $row;
        }, $this->cart);
    }

    /**
     * @return array{subtotal: float, discount_amount: float, vat_amount: float, rounding_amount: float, total: float}
     */
    public function totals(): array
    {
        if ($this->cart === []) {
            return ['subtotal' => 0, 'discount_amount' => 0, 'vat_amount' => 0, 'rounding_amount' => 0, 'total' => 0];
        }

        try {
            $totals = app(SaleService::class)->calculate(
                app(CurrentBranch::class)->get(),
                $this->cartForService(),
                DiscountType::tryFrom($this->discountType),
                (float) $this->discountValue,
            );
        } catch (ValidationException) {
            return ['subtotal' => 0, 'discount_amount' => 0, 'vat_amount' => 0, 'rounding_amount' => 0, 'total' => 0];
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
        $customer = $this->customerId ? Customer::query()->find($this->customerId) : null;
        $canViewCost = (bool) Auth::user()?->can('products.view-cost');

        return view('livewire.pos.point-of-sale', [
            'shift' => $branch ? app(PaymentService::class)->openShift($branch) : null,
            'results' => $this->productResults(),
            'totals' => $totals,
            'paid' => $paid,
            'change' => max(0, round($paid - $totals['total'], 2)),
            'due' => max(0, round($totals['total'] - $paid, 2)),
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'customer' => $customer,
            'customerDue' => $customer?->totalDue() ?? 0,
            'creditAllowed' => $branch && app(SaleService::class)->creditAllowed($branch),
            'defaultDueDate' => today()->addDays((int) $settings->get('credit_due_days', $branch?->id))->toDateString(),
            'referrer' => $this->referrerId ? Referrer::query()->find($this->referrerId) : null,
            'referrerResults' => mb_strlen(trim($this->referrerSearch)) >= 2
                ? Referrer::query()->where('is_active', true)->where(fn ($query) => $query->where('name', 'like', '%'.trim($this->referrerSearch).'%')->orWhere('phone', 'like', '%'.trim($this->referrerSearch).'%'))->orderBy('name')->limit(5)->get()
                : collect(),
            'canManageCommission' => $this->canManageCommission(),
            'commissionPreview' => $this->commissionPreview(),
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
            'canViewCost' => $canViewCost,
            'unitCosts' => $canViewCost ? $this->unitCosts() : [],
        ])->title(__('POS / Sale'));
    }

    /**
     * Buying price of each unit in the cart (product cost × pieces in the unit), keyed by product unit id.
     * Only sent to the page for users who may see costs.
     *
     * @return array<int, float>
     */
    private function unitCosts(): array
    {
        $unitIds = collect($this->cart)->flatMap(fn (array $line): array => array_keys($line['units']))->unique()->all();

        return ProductUnit::query()->with('product')->whereIn('id', $unitIds)->get()
            ->mapWithKeys(fn (ProductUnit $unit): array => [$unit->id => round((float) $unit->product?->cost_price * (float) $unit->quantity, 2)])
            ->all();
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
