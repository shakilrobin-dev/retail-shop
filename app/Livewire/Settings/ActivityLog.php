<?php

namespace App\Livewire\Settings;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Audit log: every change to products, prices, customers, users, settings...
 */
class ActivityLog extends Component
{
    use WithDataTable;

    #[Url]
    public ?int $userId = null;

    #[Url]
    public string $type = '';

    public function mount(): void
    {
        $this->authorize('users.manage');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<string, string>
     */
    protected function sortableColumns(): array
    {
        return ['date' => 'created_at'];
    }

    /**
     * @return array<string, string>
     */
    public static function types(): array
    {
        return [
            'product' => __('Products'),
            'product_unit' => __('Prices & units'),
            'customer' => __('Customers'),
            'supplier' => __('Suppliers'),
            'category' => __('Categories'),
            'user' => __('Users'),
            'repair_job' => __('Repairs'),
            'expense' => __('Expenses'),
        ];
    }

    public function render(): View
    {
        $classes = [
            'product' => Product::class,
            'product_unit' => ProductUnit::class,
            'customer' => Customer::class,
            'supplier' => Supplier::class,
            'category' => Category::class,
            'user' => User::class,
            'repair_job' => 'repair_job',
            'expense' => 'expense',
        ];

        $activities = Activity::query()
            ->with(['causer', 'subject'])
            ->when($this->userId, fn (Builder $query) => $query->where('causer_id', $this->userId))
            ->when($this->type !== '' && isset($classes[$this->type]), fn (Builder $query) => $query->where('subject_type', $classes[$this->type]))
            ->when($this->sortField === '', fn (Builder $query) => $query->latest('id'));

        return view('livewire.settings.activity-log', [
            'activities' => $this->sortField === '' ? $activities->paginate($this->perPage) : $this->applySorting($activities, 'created_at', 'desc')->paginate($this->perPage),
            'users' => User::query()->orderBy('name')->get(),
            'types' => self::types(),
        ])->title(__('Audit log'));
    }
}
