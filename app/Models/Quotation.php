<?php

namespace App\Models;

use App\Enums\DiscountType;
use Database\Factories\QuotationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A price quotation for a customer. Nothing moves in stock or money until it is sold at the POS.
 */
#[Fillable(['branch_id', 'quotation_no', 'customer_id', 'customer_name', 'customer_phone', 'customer_address', 'quotation_date', 'valid_until', 'subtotal', 'discount_type', 'discount_value', 'discount_amount', 'vat_amount', 'rounding_amount', 'total', 'note', 'sale_id', 'user_id'])]
class Quotation extends Model
{
    /** @use HasFactory<QuotationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quotation_date' => 'date',
            'valid_until' => 'date',
            'subtotal' => 'decimal:2',
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'rounding_amount' => 'decimal:2',
            'total' => 'decimal:2',
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
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<QuotationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    /**
     * Name to show: the linked customer, or the name typed on the quotation.
     */
    public function customerLabel(): ?string
    {
        return $this->customer?->name ?? $this->customer_name;
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->lt(today()) && $this->sale_id === null;
    }
}
