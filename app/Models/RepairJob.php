<?php

namespace App\Models;

use App\Enums\RepairStatus;
use Database\Factories\RepairJobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['branch_id', 'job_no', 'status', 'customer_id', 'technician_id', 'warranty_of_job_id', 'device_type', 'device_brand', 'device_model', 'device_color', 'imei_serial', 'device_password', 'accessories', 'condition_notes', 'problem', 'estimated_cost', 'estimated_delivery_at', 'service_charge', 'parts_total', 'discount', 'total', 'paid_amount', 'warranty_days', 'warranty_until', 'delivered_at', 'note', 'received_by', 'delivered_by'])]
class RepairJob extends Model
{
    /** @use HasFactory<RepairJobFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RepairStatus::class,
            'device_password' => 'encrypted',
            'accessories' => 'array',
            'estimated_cost' => 'decimal:2',
            'estimated_delivery_at' => 'datetime',
            'service_charge' => 'decimal:2',
            'parts_total' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'warranty_days' => 'integer',
            'warranty_until' => 'date',
            'delivered_at' => 'datetime',
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
     * @return BelongsTo<Technician, $this>
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    /**
     * @return BelongsTo<RepairJob, $this>
     */
    public function warrantyOf(): BelongsTo
    {
        return $this->belongsTo(RepairJob::class, 'warranty_of_job_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deliverer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    /**
     * @return HasMany<RepairJobPart, $this>
     */
    public function parts(): HasMany
    {
        return $this->hasMany(RepairJobPart::class);
    }

    /**
     * @return HasMany<RepairJobStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(RepairJobStatusHistory::class);
    }

    /**
     * @return MorphMany<Payment, $this>
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }
}
