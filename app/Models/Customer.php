<?php

namespace App\Models;

use App\Enums\SaleStatus;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['name', 'phone', 'address', 'credit_limit', 'notes'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, LogsActivity;

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * Completed invoices that still have money to collect, oldest first.
     *
     * @return HasMany<Sale, $this>
     */
    public function dueSales(): HasMany
    {
        return $this->sales()->where('status', SaleStatus::Completed)->where('due_amount', '>', 0)->orderBy('sold_at');
    }

    /**
     * @return HasMany<CustomerPayment, $this>
     */
    public function customerPayments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    /**
     * Total the customer still owes.
     */
    public function totalDue(): float
    {
        return round((float) $this->dueSales()->sum('due_amount'), 2);
    }

    /**
     * How much more can be given on credit. Null means no limit.
     */
    public function availableCredit(): ?float
    {
        return $this->credit_limit === null ? null : max(0, round((float) $this->credit_limit - $this->totalDue(), 2));
    }

    /**
     * @return HasMany<RepairJob, $this>
     */
    public function repairJobs(): HasMany
    {
        return $this->hasMany(RepairJob::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['credit_limit' => 'decimal:2'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
