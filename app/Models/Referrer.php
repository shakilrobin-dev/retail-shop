<?php

namespace App\Models;

use Database\Factories\ReferrerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A reference person (mason, contractor, engineer...) who brings customers and earns a commission.
 */
#[Fillable(['name', 'phone', 'address', 'commission_percent', 'notes', 'is_active'])]
class Referrer extends Model
{
    /** @use HasFactory<ReferrerFactory> */
    use HasFactory, LogsActivity;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'commission_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * Commission worked out for a month or date range.
     *
     * @return HasMany<CommissionSettlement, $this>
     */
    public function settlements(): HasMany
    {
        return $this->hasMany(CommissionSettlement::class);
    }

    /**
     * Commission payments (saved as expenses).
     *
     * @return HasMany<Expense, $this>
     */
    public function commissionPayments(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Commission given for all worked-out periods.
     */
    public function commissionEarned(): float
    {
        return round((float) $this->settlements()->sum('amount'), 2);
    }

    /**
     * Commission paid, all time.
     */
    public function commissionPaid(): float
    {
        return round((float) $this->commissionPayments()->sum('amount'), 2);
    }

    /**
     * Commission still to be paid.
     */
    public function commissionDue(): float
    {
        return round($this->commissionEarned() - $this->commissionPaid(), 2);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
