<?php

namespace App\Models\Interstate;

use Illuminate\Database\Eloquent\Model;
use App\Models\Request\Request;
use App\Models\Payment\UserWallet;

class StagePayment extends Model
{
    protected $fillable = [
        'request_id',
        'order_stage_id',
        'leg_payment_id',
        'stage_number',
        'stage_code',
        'amount',
        'currency',
        'payment_type',
        'status',
        'gateway',
        'transaction_reference',
        'gateway_transaction_id',
        'gateway_response',
        'initiated_at',
        'completed_at',
        'failed_at',
        'initiated_by',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'initiated_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'gateway_response' => 'array',
        'metadata' => 'array',
    ];

    // Payment types
    const TYPE_STAGE_UNLOCK = 'stage_unlock';
    const TYPE_ADDITIONAL_CHARGE = 'additional_charge';
    const TYPE_REFUND = 'refund';
    const TYPE_ADJUSTMENT = 'adjustment';

    // Statuses
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';

    // Relationships
    public function request()
    {
        return $this->belongsTo(Request::class, 'request_id');
    }

    public function orderStage()
    {
        return $this->belongsTo(OrderStage::class, 'order_stage_id');
    }

    public function legPayment()
    {
        return $this->belongsTo(LegPayment::class, 'leg_payment_id');
    }

    public function initiator()
    {
        return $this->belongsTo(\App\Models\User::class, 'initiated_by');
    }

    // Scopes
    public function scopeForRequest($query, string $requestId)
    {
        return $query->where('request_id', $requestId);
    }

    public function scopeForStage($query, int $stageNumber)
    {
        return $query->where('stage_number', $stageNumber);
    }

    public function scopeByType($query, string $paymentType)
    {
        return $query->where('payment_type', $paymentType);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    // Helper Methods
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isRefunded(): bool
    {
        return $this->status === self::STATUS_REFUNDED;
    }

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'initiated_at' => now(),
        ]);
    }

    public function markAsCompleted(string $gatewayTransactionId = null, array $response = []): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'gateway_transaction_id' => $gatewayTransactionId,
            'gateway_response' => $response,
        ]);

        // Update the linked order stage
        if ($this->orderStage) {
            $this->orderStage->markPaymentCompleted($this->id);
        }
    }

    public function markAsFailed(string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'failed_at' => now(),
            'notes' => $reason,
        ]);
    }

    public function markAsRefunded(): void
    {
        $this->update([
            'status' => self::STATUS_REFUNDED,
        ]);
    }

    public function canRetry(): bool
    {
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_PENDING]);
    }

    // Static Methods
    public static function createForStage(
        OrderStage $stage,
        float $amount,
        string $paymentType = self::TYPE_STAGE_UNLOCK,
        int $initiatedBy = null
    ): self {
        return self::create([
            'request_id' => $stage->request_id,
            'order_stage_id' => $stage->id,
            'stage_number' => $stage->stage_number,
            'stage_code' => $stage->stage_code,
            'amount' => $amount,
            'payment_type' => $paymentType,
            'status' => self::STATUS_PENDING,
            'initiated_by' => $initiatedBy,
        ]);
    }

    public static function getTotalPaidForRequest(string $requestId): float
    {
        return self::forRequest($requestId)
            ->completed()
            ->sum('amount') ?? 0;
    }

    public static function getPendingPaymentsForRequest(string $requestId): array
    {
        return self::forRequest($requestId)
            ->pending()
            ->with('orderStage')
            ->get()
            ->toArray();
    }

    public static function hasPendingPaymentForStage(string $requestId, int $stageNumber): bool
    {
        return self::forRequest($requestId)
            ->forStage($stageNumber)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_PROCESSING])
            ->exists();
    }
}
