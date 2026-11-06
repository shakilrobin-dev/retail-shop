<?php

namespace App\Livewire\Reports;

use App\Models\Branch;
use App\Models\User;
use App\Services\CurrentBranch;
use App\Services\ReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

class ReportIndex extends Component
{
    public const TABS = ['summary', 'daily', 'products', 'categories', 'payments', 'staff', 'dead-stock'];

    #[Url]
    public string $tab = 'summary';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    /** 'current' or 'all' (admin only) */
    #[Url]
    public string $scope = 'current';

    #[Url]
    public int $deadDays = 60;

    /** Category report: add sub-categories into their main category. */
    #[Url]
    public bool $mainCategories = false;

    public function mount(): void
    {
        $this->authorize('reports.view');
        $this->from = $this->from ?: today()->startOfMonth()->toDateString();
        $this->to = $this->to ?: today()->toDateString();
    }

    public function setPeriod(string $period): void
    {
        [$from, $to] = match ($period) {
            'today' => [today(), today()],
            'yesterday' => [today()->subDay(), today()->subDay()],
            'week' => [today()->startOfWeek(Carbon::SATURDAY), today()],
            'month' => [today()->startOfMonth(), today()],
            'last-month' => [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()],
            'year' => [today()->startOfYear(), today()],
            default => [Carbon::parse($this->from), Carbon::parse($this->to)],
        };

        $this->from = $from->toDateString();
        $this->to = $to->toDateString();
    }

    public function branchId(): ?int
    {
        /** @var User $user */
        $user = Auth::user();

        return $this->scope === 'all' && $user->canAccessAllBranches() ? null : app(CurrentBranch::class)->get()?->id;
    }

    public function render(): View
    {
        $reports = app(ReportService::class);
        $from = Carbon::parse($this->from ?: today());
        $to = Carbon::parse($this->to ?: today());

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $branchId = $this->branchId();
        $tab = in_array($this->tab, self::TABS, true) ? $this->tab : 'summary';

        $data = match ($tab) {
            'summary' => ['summary' => $reports->summary($branchId, $from, $to)],
            'daily' => ['rows' => $reports->daily($branchId, $from, $to)],
            'products' => ['rows' => $reports->topProducts($branchId, $from, $to)],
            'categories' => ['rows' => $reports->byCategory($branchId, $from, $to, $this->mainCategories)],
            'payments' => ['rows' => $reports->byPaymentMethod($branchId, $from, $to)],
            'staff' => ['rows' => $reports->byStaff($branchId, $from, $to)],
            'dead-stock' => ['rows' => $reports->deadStock($branchId, max(1, $this->deadDays))],
        };

        /** @var User $user */
        $user = Auth::user();

        return view('livewire.reports.report-index', $data + [
            'activeTab' => $tab,
            'canSeeAll' => $user->canAccessAllBranches() && Branch::query()->count() > 1,
            'exportUrl' => route('reports.export', ['tab' => $tab, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'scope' => $this->scope, 'deadDays' => $this->deadDays, 'main' => $this->mainCategories ? 1 : 0]),
        ])->title(__('Reports'));
    }
}
