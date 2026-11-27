<?php

namespace App\Livewire\Referrers;

use App\Enums\SaleStatus;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Expense;
use App\Models\PaymentMethod;
use App\Models\Referrer;
use App\Services\CommissionService;
use App\Services\CurrentBranch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reference people (mason, contractor, engineer...) and the commission they earn and are paid.
 */
class ReferrerIndex extends Component
{
    use WithDataTable;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $phone = '';

    public string $address = '';

    public string $commission_percent = '';

    public string $notes = '';

    public bool $showPay = false;

    public ?int $payingId = null;

    public string $payAmount = '';

    public ?int $payMethodId = null;

    public string $payDate = '';

    public string $payNote = '';

    public bool $payFromDrawer = true;

    public function mount(): void
    {
        $this->authorize('commissions.manage');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $referrer = Referrer::query()->findOrFail($id);
        $this->resetForm();
        $this->editingId = $referrer->id;
        $this->name = $referrer->name;
        $this->phone = (string) $referrer->phone;
        $this->address = (string) $referrer->address;
        $this->commission_percent = $referrer->commission_percent !== null ? (string) ($referrer->commission_percent + 0) : '';
        $this->notes = (string) $referrer->notes;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('commissions.manage');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], ['commission_percent' => __('commission %')]);

        Referrer::query()->updateOrCreate(['id' => $this->editingId], array_map(fn ($value) => $value === '' ? null : $value, $validated));

        $this->dispatch('toast', message: __('Reference saved.'));
        $this->resetForm();
    }

    public function toggleActive(int $id): void
    {
        $this->authorize('commissions.manage');
        $referrer = Referrer::query()->findOrFail($id);
        $referrer->update(['is_active' => ! $referrer->is_active]);
    }

    public function openPay(int $id): void
    {
        $referrer = Referrer::query()->findOrFail($id);
        $this->resetValidation();
        $this->payingId = $referrer->id;
        $this->payAmount = (string) max(0, $referrer->commissionDue());
        $this->payMethodId ??= PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->value('id');
        $this->payDate = today()->toDateString();
        $this->payNote = '';
        $this->showPay = true;
    }

    public function pay(CommissionService $commissions, CurrentBranch $currentBranch): void
    {
        $this->authorize('commissions.manage');

        $this->validate([
            'payAmount' => ['required', 'numeric', 'gt:0'],
            'payMethodId' => ['required', 'exists:payment_methods,id'],
            'payDate' => ['required', 'date', 'before_or_equal:today'],
            'payNote' => ['nullable', 'string', 'max:255'],
        ], [], ['payAmount' => __('amount'), 'payMethodId' => __('payment method'), 'payDate' => __('date')]);

        $referrer = Referrer::query()->findOrFail($this->payingId);

        $commissions->pay(
            $currentBranch->get(),
            $referrer,
            (float) $this->payAmount,
            PaymentMethod::query()->findOrFail($this->payMethodId),
            $this->payDate,
            $this->payNote,
            $this->payFromDrawer,
        );

        $this->reset('showPay', 'payingId', 'payAmount', 'payNote');
        $this->dispatch('toast', message: __('Commission paid to :name.', ['name' => $referrer->name]));
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'name', 'phone', 'address', 'commission_percent', 'notes', 'showForm');
        $this->resetValidation();
    }

    /**
     * @return array<string, string>
     */
    protected function sortableColumns(): array
    {
        return ['name' => 'name', 'sales' => 'sales_count', 'earned' => 'sales_sum_commission_amount', 'paid' => 'commission_payments_sum_amount'];
    }

    public function export(): StreamedResponse
    {
        $rows = $this->query()->orderBy('name')->get()->map(fn (Referrer $referrer): array => [
            $referrer->name, $referrer->phone, $referrer->commission_percent, $referrer->sales_count, (float) $referrer->sales_sum_total,
            (float) $referrer->sales_sum_commission_amount, (float) $referrer->commission_payments_sum_amount,
            round((float) $referrer->sales_sum_commission_amount - (float) $referrer->commission_payments_sum_amount, 2),
        ]);

        return $this->csvDownload('references', ['Name', 'Phone', 'Default commission %', 'Invoices', 'Sales', 'Commission earned', 'Commission paid', 'Commission due'], $rows);
    }

    /**
     * @return Builder<Referrer>
     */
    private function query(): Builder
    {
        $completed = fn (Builder $query) => $query->where('status', SaleStatus::Completed);

        return Referrer::query()
            ->withCount(['sales' => $completed])
            ->withSum(['sales' => $completed], 'total')
            ->withSum(['sales' => $completed], 'commission_amount')
            ->withSum('commissionPayments', 'amount')
            ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")));
    }

    public function render(): View
    {
        $referrers = $this->applySorting($this->query(), 'name')->paginate($this->perPage);
        $earned = (float) Referrer::query()->join('sales', 'sales.referrer_id', '=', 'referrers.id')->where('sales.status', SaleStatus::Completed)->sum('sales.commission_amount');
        $paid = (float) Expense::query()->whereNotNull('referrer_id')->sum('amount');
        $paying = $this->payingId ? Referrer::query()->find($this->payingId) : null;

        return view('livewire.referrers.referrer-index', [
            'referrers' => $referrers,
            'summary' => [
                'active' => Referrer::query()->where('is_active', true)->count(),
                'earned' => $earned,
                'paid' => $paid,
                'due' => round($earned - $paid, 2),
                'paid_month' => (float) Expense::query()->whereNotNull('referrer_id')->whereDate('expense_date', '>=', today()->startOfMonth())->sum('amount'),
            ],
            'paying' => $paying,
            'payingDue' => $paying?->commissionDue() ?? 0,
            'paymentMethods' => $this->showPay ? PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get() : collect(),
            'history' => $this->editingId
                ? Expense::query()->where('referrer_id', $this->editingId)->latest('expense_date')->latest('id')->limit(10)->get()
                : collect(),
        ])->title(__('References'));
    }
}
