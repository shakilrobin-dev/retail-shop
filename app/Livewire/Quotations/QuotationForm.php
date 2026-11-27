<?php

namespace App\Livewire\Quotations;

use App\Enums\DiscountType;
use App\Livewire\Concerns\ManagesCartLines;
use App\Livewire\Concerns\SearchesProducts;
use App\Models\Quotation;
use App\Services\CurrentBranch;
use App\Services\QuotationService;
use App\Services\SaleService;
use App\Services\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Make or change a price quotation. Prices can always be changed here.
 */
class QuotationForm extends Component
{
    use ManagesCartLines, SearchesProducts;

    public ?Quotation $quotation = null;

    public string $customerName = '';

    public string $customerPhone = '';

    public string $customerAddress = '';

    public string $quotationDate = '';

    public string $validUntil = '';

    public string $discountType = 'amount';

    public string $discountValue = '0';

    public string $note = '';

    public function mount(?Quotation $quotation = null): void
    {
        $this->authorize('pos.sell');

        if ($quotation?->exists) {
            abort_unless($quotation->branch_id === app(CurrentBranch::class)->get()?->id, 404);
            $this->quotation = $quotation->load('items', 'customer');
            $this->customerName = (string) $quotation->customerLabel();
            $this->customerPhone = (string) ($quotation->customer_phone ?? $quotation->customer?->phone);
            $this->customerAddress = (string) $quotation->customer_address;
            $this->quotationDate = $quotation->quotation_date->toDateString();
            $this->validUntil = (string) $quotation->valid_until?->toDateString();
            $this->discountType = $quotation->discount_type->value ?? 'amount';
            $this->discountValue = (string) ($quotation->discount_value + 0);
            $this->note = (string) $quotation->note;

            foreach ($quotation->items->reverse() as $item) {
                $this->addSavedLine($item->product_unit_id, (float) $item->quantity, (float) $item->unit_price, $item->measure_rate !== null ? (float) $item->measure_rate : null, (float) $item->discount_amount);
            }

            return;
        }

        $this->quotationDate = today()->toDateString();
        $days = (int) app(Settings::class)->get('quotation_valid_days', app(CurrentBranch::class)->get()?->id);
        $this->validUntil = today()->addDays($days > 0 ? $days : 7)->toDateString();
    }

    public function pickUnit(int $productUnitId): void
    {
        $this->addToCart($productUnitId);
    }

    /**
     * Prices on a quotation can always be changed.
     */
    public function canEditPrice(): bool
    {
        return true;
    }

    /**
     * @return array{subtotal: float, discount_amount: float, vat_amount: float, rounding_amount: float, total: float}
     */
    public function totals(): array
    {
        $empty = ['subtotal' => 0, 'discount_amount' => 0, 'vat_amount' => 0, 'rounding_amount' => 0, 'total' => 0];

        if ($this->cart === []) {
            return $empty;
        }

        try {
            $totals = app(SaleService::class)->calculate(app(CurrentBranch::class)->get(), $this->cartForService(), DiscountType::tryFrom($this->discountType), (float) $this->discountValue);
        } catch (ValidationException) {
            return $empty;
        }

        unset($totals['lines']);

        return $totals;
    }

    public function save(QuotationService $quotations, CurrentBranch $currentBranch): void
    {
        $this->authorize('pos.sell');

        $this->validate([
            'customerName' => ['nullable', 'string', 'max:255'],
            'customerPhone' => ['nullable', 'string', 'max:30'],
            'customerAddress' => ['nullable', 'string', 'max:255'],
            'quotationDate' => ['required', 'date'],
            'validUntil' => ['nullable', 'date', 'after_or_equal:quotationDate'],
            'discountValue' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:2000'],
            'cart' => ['required', 'array', 'min:1'],
            'cart.*.quantity' => ['required', 'numeric', 'gt:0'],
            'cart.*.unit_price' => ['required', 'numeric', 'min:0'],
            'cart.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
        ], [], ['cart' => __('products'), 'cart.*.quantity' => __('quantity'), 'cart.*.unit_price' => __('price'), 'validUntil' => __('valid until')]);

        $quotation = $quotations->save(
            $currentBranch->get(),
            $this->quotation,
            [
                'customer_name' => $this->customerName,
                'customer_phone' => $this->customerPhone,
                'customer_address' => $this->customerAddress,
                'quotation_date' => $this->quotationDate,
                'valid_until' => $this->validUntil,
                'note' => $this->note,
            ],
            $this->cartForService(),
            (float) $this->discountValue > 0 ? DiscountType::tryFrom($this->discountType) : null,
            (float) $this->discountValue,
        );

        session()->flash('success', __('Quotation :no saved.', ['no' => $quotation->quotation_no]));
        session()->flash('print_quotation', $quotation->id);
        $this->redirectRoute('quotations.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.quotations.quotation-form', [
            'results' => $this->productResults(),
            'totals' => $this->totals(),
            'vatEnabled' => app(Settings::class)->bool('vat_enabled', app(CurrentBranch::class)->get()?->id),
            'branch' => app(CurrentBranch::class)->get(),
        ])->title($this->quotation ? __('Edit quotation') : __('New quotation'));
    }
}
