<?php

namespace App\Models\Interstate;

use Illuminate\Database\Eloquent\Model;
use App\Models\Request\Request;

/**
 * Model for tracking payments per leg in multi-leg interstate delivery
 */
class LegPayment extends Model
{
    protected $fillable = [
        'request_id',
        'request_leg_id',
        'leg_number',
        'leg_type',
        'original_amount',
        'adjusted_amount',
        'paid_amount',
        'refund_amount',
        'balance_due',
        'payment_status',
        'payment_method',
        'payment_reference',
        'transaction_id',
        'payment_details',
        'adjustment_reason',
        'adjustment_details',
        'refund_status',
        'refund_reference',
        'refund_failure_reason',
        'currency',
        'paid_at',
        'refund_processed_at',
        'finalized_at',
    ];

    protected $casts = [
        'original_amount' => 'decimal:2',
        'adjusted_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'payment_details' => 'array',
        'adjustment_details' => 'array',
        'paid_at' => 'datetime',
        'refund_processed_at' => 'datetime',
        'finalized_at' => 'datetime',
    ];

    /**
     * Relationship to parent request
     */
    public function request()
    {
        return $this->belongsTo(Request::class);
    }

    /**
     * Relationship to request leg
     */
    public function requestLeg()
    {
        return $this->belongsTo(RequestLeg::class);
    }

    /**
     * Scope: Pending payments
     */
    public function scopePending($query)
    {
        return $query->whereIn('payment_status', ['pending', 'additional_payment_required']);
    }

    /**
     * Scope: Paid payments
     */
    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    /**
     * Scope: By leg type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('leg_type', $type);
    }

    /**
     * Check if payment is complete
     */
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    /**
     * Check if additional payment is required
     */
    public function requiresAdditionalPayment(): bool
    {
        return $this->payment_status === 'additional_payment_required';
    }

    /**
     * Check if refund is pending
     */
    public function isRefundPending(): bool
    {
        return $this->payment_status === 'refund_pending';
    }

    /**
     * Get amount to display to user
     */
    public function getDisplayAmount(): float
    {
        return $this->adjusted_amount;
    }

    /**
     * Get remaining balance
     */
    public function getRemainingBalance(): float
    {
        return max(0, $this->adjusted_amount - $this->paid_amount);
    }
}
