<?php

namespace App\Livewire\Expenses;

use App\Enums\PaymentDirection;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Services\CurrentBranch;
use App\Services\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ExpenseIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public ?int $categoryFilter = null;

    public string $expense_date = '';

    public ?int $expense_category_id = null;

    public string $amount = '';

    public ?int $payment_method_id = null;

    public bool $from_drawer = true;

    public string $note = '';

    public string $newCategory = '';

    public function mount(): void
    {
        $this->authorize('expenses.manage');
        $this->from = $this->from ?: today()->startOfMonth()->toDateString();
        $this->to = $this->to ?: today()->toDateString();
        $this->expense_date = today()->toDateString();
        $this->payment_method_id = PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->value('id');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['from', 'to', 'categoryFilter'], true)) {
            $this->resetPage();
        }
    }

    public function save(PaymentService $payments): void
    {
        $this->authorize('expenses.manage');

        $validated = $this->validate([
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['expense_category_id' => __('category')]);

        $branch = app(CurrentBranch::class)->get();

        DB::transaction(function () use ($validated, $branch, $payments): void {
            $expense = Expense::query()->create([
                'branch_id' => $branch->id,
                'expense_category_id' => $validated['expense_category_id'],
                'expense_date' => $validated['expense_date'],
                'amount' => $validated['amount'],
                'note' => $validated['note'] ?: null,
                'user_id' => Auth::id(),
            ]);

            $payments->record($branch, $expense, PaymentMethod::query()->findOrFail($validated['payment_method_id']), PaymentDirection::Out, (float) $validated['amount'], null, $this->from_drawer);
        });

        $this->reset('amount', 'note');
        session()->flash('success', __('Expense saved.'));
    }

    public function delete(int $id): void
    {
        $this->authorize('expenses.manage');

        $expense = Expense::query()->where('branch_id', app(CurrentBranch::class)->get()?->id)->findOrFail($id);

        DB::transaction(function () use ($expense): void {
            $expense->payments()->delete();
            $expense->delete();
        });
    }

    public function addCategory(): void
    {
        $this->authorize('expenses.manage');
        $this->validate(['newCategory' => ['required', 'string', 'max:255', 'unique:expense_categories,name']], [], ['newCategory' => __('category')]);
        $this->expense_category_id = ExpenseCategory::query()->create(['name' => trim($this->newCategory)])->id;
        $this->newCategory = '';
    }

    public function render(): View
    {
        $query = Expense::query()
            ->with(['category', 'user', 'payments.paymentMethod'])
            ->where('branch_id', app(CurrentBranch::class)->get()?->id)
            ->when($this->from !== '', fn (Builder $query) => $query->whereDate('expense_date', '>=', $this->from))
            ->when($this->to !== '', fn (Builder $query) => $query->whereDate('expense_date', '<=', $this->to))
            ->when($this->categoryFilter, fn (Builder $query) => $query->where('expense_category_id', $this->categoryFilter));

        return view('livewire.expenses.expense-index', [
            'expenses' => (clone $query)->latest('expense_date')->latest('id')->paginate(25),
            'total' => (clone $query)->sum('amount'),
            'byCategory' => (clone $query)->selectRaw('expense_category_id, SUM(amount) as total')->groupBy('expense_category_id')->with('category')->get(),
            'categories' => ExpenseCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ])->title(__('Expenses'));
    }
}
