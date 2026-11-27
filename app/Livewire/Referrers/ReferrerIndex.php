<?php

namespace App\Livewire\Referrers;

use App\Enums\CommissionType;
use App\Enums\SaleStatus;
use App\Livewire\Concerns\WithDataTable;
use App\Models\CommissionSettlement;
use App\Models\Expense;
use App\Models\PaymentMethod;
use App\Models\Referrer;
use App\Services\CommissionService;
use App\Services\CurrentBranch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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

    /** Work out commission for a month or date range. */
    public bool $showSettle = false;

    public ?int $settlingId = null;

    public string $settleFrom = '';

    public string $settleTo = '';

    public string $settleType = 'profit_percent';

    public string $settleValue = '';

    public string $settleNote = '';

    /** Amount paid now (optional). */
    public string $settlePay = '';

    public ?int $settleMethodId = null;

    public bool $settleFromDrawer = true;

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

    public function openSettle(int $id): void
    {
        $referrer = Referrer::query()->findOrFail($id);
        $this->resetValidation();
        $this->settlingId = $referrer->id;
        $last = $referrer->settlements()->latest('period_to')->first();
        $from = $last ? $last->period_to->copy()->addDay() : today()->startOfMonth();
        $this->settleFrom = $from->toDateString();
        $this->settleTo = today()->max($from)->toDateString();
        $this->settleType = CommissionType::ProfitPercent->value;
        $this->settleValue = $referrer->commission_percent !== null ? (string) ($referrer->commission_percent + 0) : '';
        $this->settleNote = '';
        $this->settlePay = '';
        $this->settleMethodId ??= PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->value('id');
        $this->showSettle = true;
    }

    public function settleMonth(string $which): void
    {
        $month = $which === 'last' ? today()->subMonthNoOverflow() : today();
        $this->settleFrom = $month->copy()->startOfMonth()->toDateString();
        $this->settleTo = $which === 'last' ? $month->copy()->endOfMonth()->toDateString() : today()->toDateString();
    }

    public function settle(CommissionService $commissions, CurrentBranch $currentBranch): void
    {
        $this->authorize('commissions.manage');

        $this->validate([
            'settleFrom' => ['required', 'date'],
            'settleTo' => ['required', 'date', 'after_or_equal:settleFrom'],
            'settleType' => ['required', 'in:'.implode(',', array_column(CommissionType::cases(), 'value'))],
            'settleValue' => ['required', 'numeric', 'gt:0'],
            'settleNote' => ['nullable', 'string', 'max:255'],
            'settlePay' => ['nullable', 'numeric', 'min:0'],
            'settleMethodId' => ['required_with:settlePay', 'nullable', 'exists:payment_methods,id'],
        ], [], ['settleFrom' => __('from'), 'settleTo' => __('to'), 'settleValue' => __('commission'), 'settlePay' => __('amount paid')]);

        $referrer = Referrer::query()->findOrFail($this->settlingId);

        $commissions->settle(
            $currentBranch->get(),
            $referrer,
            Carbon::parse($this->settleFrom),
            Carbon::parse($this->settleTo),
            CommissionType::from($this->settleType),
            (float) $this->settleValue,
            $this->settleNote,
            (float) $this->settlePay,
            $this->settleMethodId ? PaymentMethod::query()->find($this->settleMethodId) : null,
            $this->settleFromDrawer,
        );

        $this->reset('showSettle', 'settlingId', 'settleValue', 'settleNote', 'settlePay');
        $this->dispatch('toast', message: __('Commission saved for :name.', ['name' => $referrer->name]));
    }

    /**
     * Live figures for the commission pop-up.
     *
     * @return array<string, mixed>|null
     */
    private function settlePreview(): ?array
    {
        $referrer = $this->showSettle && $this->settlingId ? Referrer::query()->find($this->settlingId) : null;

        if (! $referrer) {
            return null;
        }

        try {
            $from = Carbon::parse($this->settleFrom);
            $to = Carbon::parse($this->settleTo);
        } catch (\Throwable) {
            return null;
        }

        $commissions = app(CommissionService::class);
        $summary = $commissions->summary($referrer, $from, $to);
        $type = CommissionType::tryFrom($this->settleType) ?? CommissionType::ProfitPercent;

        return [
            'referrer' => $referrer,
            'summary' => $summary,
            'amount' => $commissions->calculate($summary['profit'], $type, (float) $this->settleValue),
            'due' => $referrer->commissionDue(),
            'overlap' => $to->gte($from) ? $commissions->overlapping($referrer, $from, $to) : null,
            'history' => $referrer->settlements()->latest('period_to')->limit(6)->get(),
        ];
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
        return ['name' => 'name', 'sales' => 'sales_count', 'earned' => 'settlements_sum_amount', 'paid' => 'commission_payments_sum_amount'];
    }

    public function export(): StreamedResponse
    {
        $rows = $this->query()->orderBy('name')->get()->map(fn (Referrer $referrer): array => [
            $referrer->name, $referrer->phone, $referrer->commission_percent, $referrer->sales_count, (float) $referrer->sales_sum_total,
            (float) $referrer->settlements_sum_amount, (float) $referrer->commission_payments_sum_amount,
            round((float) $referrer->settlements_sum_amount - (float) $referrer->commission_payments_sum_amount, 2),
        ]);

        return $this->csvDownload('references', ['Name', 'Phone', 'Default commission %', 'Invoices', 'Sales', 'Commission given', 'Commission paid', 'Commission due'], $rows);
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
            ->withSum('settlements', 'amount')
            ->withSum('commissionPayments', 'amount')
            ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")));
    }

    public function render(): View
    {
        $referrers = $this->applySorting($this->query(), 'name')->paginate($this->perPage);
        $earned = (float) CommissionSettlement::query()->sum('amount');
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
            'settle' => $this->settlePreview(),
            'paymentMethods' => $this->showPay || $this->showSettle ? PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get() : collect(),
            'history' => $this->editingId
                ? Expense::query()->where('referrer_id', $this->editingId)->latest('expense_date')->latest('id')->limit(10)->get()
                : collect(),
        ])->title(__('References'));
    }
}
