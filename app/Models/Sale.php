<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['branch_id', 'invoice_no', 'status', 'customer_id', 'referrer_id', 'user_id', 'cash_shift_id', 'sold_at', 'subtotal', 'discount_type', 'discount_value', 'discount_amount', 'vat_amount', 'rounding_amount', 'total', 'paid_amount', 'change_amount', 'due_amount', 'payment_status', 'due_date', 'hold_note', 'note', 'voided_at', 'voided_by', 'void_reason'])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SaleStatus::class,
            'discount_type' => DiscountType::class,
            'sold_at' => 'datetime',
            'voided_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'rounding_amount' => 'decimal:2',
            'due_amount' => 'decimal:2',
            'payment_status' => PaymentStatus::class,
            'due_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Reference person who brought this sale.
     *
     * @return BelongsTo<Referrer, $this>
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Referrer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<CashShift, $this>
     */
    public function cashShift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * @return HasMany<SaleReturn, $this>
     */
    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    /**
     * @return MorphMany<Payment, $this>
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /**
     * Money received for this invoice so far (sale payments plus due collections, less refunds).
     */
    public function receivedAmount(): float
    {
        return round((float) $this->total - (float) $this->returns->sum('total') - (float) $this->due_amount + $this->refundedForReturns(), 2);
    }

    /**
     * Value of returned items that was paid back in money (not taken off the due).
     */
    public function refundedForReturns(): float
    {
        return (float) $this->returns->sum('refund_amount');
    }

    /**
     * Change the due and keep the payment status in step.
     */
    public function setDue(float $due): void
    {
        $due = max(0, round($due, 2));
        $base = (float) $this->total - (float) $this->returns()->sum('total');

        $this->update([
            'due_amount' => $due,
            'payment_status' => PaymentStatus::for(max($base, 0.01), $due),
        ]);
    }

    /**
     * Days since the invoice date (used for due ageing).
     */
    public function ageInDays(): int
    {
        return (int) $this->sold_at->copy()->startOfDay()->diffInDays(today());
    }

    public function isOverdue(): bool
    {
        return (float) $this->due_amount > 0 && $this->due_date !== null && $this->due_date->isPast() && ! $this->due_date->isToday();
    }
}
