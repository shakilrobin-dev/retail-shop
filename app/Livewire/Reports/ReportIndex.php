<?php

namespace App\Livewire\Reports;

use App\Models\Branch;
use App\Models\User;
use App\Services\CurrentBranch;
use App\Services\ReportDocument;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

class ReportIndex extends Component
{
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
        $from = Carbon::parse($this->from ?: today());
        $to = Carbon::parse($this->to ?: today());

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $branchId = $this->branchId();
        $tab = array_key_exists($this->tab, ReportDocument::REPORTS) ? $this->tab : 'summary';
        $document = app(ReportDocument::class)->build($tab, $branchId, $from, $to, [
            'main_categories' => $this->mainCategories,
            'dead_days' => $this->deadDays,
        ]);

        /** @var User $user */
        $user = Auth::user();

        $exportParams = ['tab' => $tab, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'scope' => $this->scope, 'deadDays' => $this->deadDays, 'main' => $this->mainCategories ? 1 : 0];

        return view('livewire.reports.report-index', [
            'document' => $document,
            'activeTab' => $tab,
            'canSeeAll' => $user->canAccessAllBranches() && Branch::query()->count() > 1,
            'exportUrls' => collect(['pdf', 'xlsx', 'csv'])->mapWithKeys(fn (string $format) => [$format => route('reports.export', $exportParams + ['format' => $format])])->all(),
        ])->title(__('Reports'));
    }
}
