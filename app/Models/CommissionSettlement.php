<?php

namespace App\Models;

use App\Enums\CommissionType;
use Database\Factories\CommissionSettlementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Commission worked out for a reference person on their referred sales in a month or date range.
 */
#[Fillable(['referrer_id', 'branch_id', 'period_from', 'period_to', 'invoices', 'sales', 'profit', 'commission_type', 'commission_value', 'amount', 'note', 'user_id'])]
class CommissionSettlement extends Model
{
    /** @use HasFactory<CommissionSettlementFactory> */
    use HasFactory, LogsActivity;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'invoices' => 'integer',
            'sales' => 'decimal:2',
            'profit' => 'decimal:2',
            'commission_type' => CommissionType::class,
            'commission_value' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Referrer, $this>
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Referrer::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
