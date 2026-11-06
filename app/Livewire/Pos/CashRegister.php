<?php

namespace App\Livewire\Pos;

use App\Models\CashShift;
use App\Services\CashShiftService;
use App\Services\CurrentBranch;
use App\Services\PaymentService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class CashRegister extends Component
{
    use WithPagination;

    public string $openingCash = '0';

    public string $countedCash = '';

    public string $closeNote = '';

    public ?int $viewShiftId = null;

    public function mount(): void
    {
        $this->authorize('cash-shifts.manage');
    }

    public function open(CashShiftService $shifts): void
    {
        $this->authorize('cash-shifts.manage');
        $this->validate(['openingCash' => ['required', 'numeric', 'min:0']], [], ['openingCash' => __('opening cash')]);
        $shifts->open(app(CurrentBranch::class)->get(), (float) $this->openingCash);
        session()->flash('success', __('Cash register opened.'));
    }

    public function close(CashShiftService $shifts, PaymentService $payments): void
    {
        $this->authorize('cash-shifts.manage');
        $this->validate([
            'countedCash' => ['required', 'numeric', 'min:0'],
            'closeNote' => ['nullable', 'string', 'max:1000'],
        ], [], ['countedCash' => __('counted cash')]);

        $shift = $payments->openShift(app(CurrentBranch::class)->get());
        abort_unless($shift, 404);

        $shift = $shifts->close($shift, (float) $this->countedCash, $this->closeNote ?: null);
        $this->reset('countedCash', 'closeNote');
        $this->viewShiftId = $shift->id;
        session()->flash('success', __('Cash register closed.'));
    }

    public function render(): View
    {
        $shifts = app(CashShiftService::class);
        $payments = app(PaymentService::class);
        $branch = app(CurrentBranch::class)->get();
        $openShift = $branch ? $payments->openShift($branch) : null;
        $viewShift = $this->viewShiftId ? CashShift::query()->where('branch_id', $branch?->id)->find($this->viewShiftId) : null;

        return view('livewire.pos.cash-register', [
            'openShift' => $openShift,
            'openSummary' => $openShift ? $shifts->summary($openShift) : null,
            'viewShift' => $viewShift,
            'viewSummary' => $viewShift ? $shifts->summary($viewShift) : null,
            'history' => CashShift::query()->with('user')->where('branch_id', $branch?->id)->whereNotNull('closed_at')->latest('closed_at')->paginate(15),
        ])->title(__('Cash register'));
    }
}
