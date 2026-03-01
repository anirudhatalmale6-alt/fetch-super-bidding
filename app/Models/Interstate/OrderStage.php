<?php

namespace App\Models\Interstate;

use Illuminate\Database\Eloquent\Model;
use App\Models\Request\Request;
use App\Models\Request\RequestLeg;

class OrderStage extends Model
{
    protected $fillable = [
        'request_id',
        'request_leg_id',
        'stage_number',
        'stage_code',
        'stage_name',
        'status',
        'started_at',
        'completed_at',
        'duration_minutes',
        'triggered_by_type',
        'triggered_by_id',
        'requires_payment',
        'payment_id',
        'payment_completed_at',
        'metadata',
        'notes',
        'rerouting_attempt',
        'previous_stage_id',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'payment_completed_at' => 'datetime',
        'metadata' => 'array',
        'requires_payment' => 'boolean',
        'duration_minutes' => 'integer',
        'rerouting_attempt' => 'integer',
    ];

    // Stage definitions as per specification
    const STAGES = [
        1 => ['code' => 'pending_pickup', 'name' => 'Pending Pickup', 'requires_payment' => false],
        2 => ['code' => 'picked_up', 'name' => 'Picked Up', 'requires_payment' => false],
        3 => ['code' => 'arrived_trucking_hub', 'name' => 'Arrived at Trucking Hub', 'requires_payment' => false],
        4 => ['code' => 'inspection_pending', 'name' => 'Inspection Pending', 'requires_payment' => false],
        5 => ['code' => 'awaiting_user_approval', 'name' => 'Awaiting User Approval', 'requires_payment' => false],
        6 => ['code' => 'in_transit', 'name' => 'In Transit', 'requires_payment' => true],
        7 => ['code' => 'arrived_destination_hub', 'name' => 'Arrived at Destination Hub', 'requires_payment' => false],
        8 => ['code' => 'last_mile_assigned', 'name' => 'Last Mile Assigned', 'requires_payment' => true],
        9 => ['code' => 'delivered', 'name' => 'Delivered', 'requires_payment' => false],
        10 => ['code' => 'cancelled', 'name' => 'Cancelled', 'requires_payment' => false],
        11 => ['code' => 'rerouting', 'name' => 'Rerouting', 'requires_payment' => false],
    ];

    // Relationships
    public function request()
    {
        return $this->belongsTo(Request::class, 'request_id');
    }

    public function requestLeg()
    {
        return $this->belongsTo(RequestLeg::class, 'request_leg_id');
    }

    public function payment()
    {
        return $this->belongsTo(StagePayment::class, 'payment_id');
    }

    public function previousStage()
    {
        return $this->belongsTo(self::class, 'previous_stage_id');
    }

    public function nextStages()
    {
        return $this->hasMany(self::class, 'previous_stage_id');
    }

    // Scopes
    public function scopeForRequest($query, string $requestId)
    {
        return $query->where('request_id', $requestId);
    }

    public function scopeByStageCode($query, string $stageCode)
    {
        return $query->where('stage_code', $stageCode);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeRequiresPayment($query)
    {
        return $query->where('requires_payment', true);
    }

    public function scopeCurrent($query)
    {
        return $query->whereIn('status', ['pending', 'in_progress'])
            ->orderBy('stage_number', 'asc');
    }

    // Helper Methods
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function canStart(): bool
    {
        return $this->status === 'pending';
    }

    public function canComplete(): bool
    {
        return $this->status === 'in_progress';
    }

    public function start(array $data = []): void
    {
        if (!$this->canStart()) {
            throw new \RuntimeException("Stage {$this->stage_code} cannot be started from status {$this->status}");
        }

        $this->update(array_merge([
            'status' => 'in_progress',
            'started_at' => now(),
        ], $data));
    }

    public function complete(array $data = []): void
    {
        if (!$this->canComplete()) {
            throw new \RuntimeException("Stage {$this->stage_code} cannot be completed from status {$this->status}");
        }

        $completedAt = now();
        $duration = $this->started_at ? $this->started_at->diffInMinutes($completedAt) : null;

        $this->update(array_merge([
            'status' => 'completed',
            'completed_at' => $completedAt,
            'duration_minutes' => $duration,
        ], $data));
    }

    public function cancel(string $reason = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'notes' => $reason,
            'completed_at' => now(),
        ]);
    }

    public function markPaymentCompleted(int $paymentId): void
    {
        $this->update([
            'payment_id' => $paymentId,
            'payment_completed_at' => now(),
        ]);
    }

    public function getStageInfo(): array
    {
        return self::STAGES[$this->stage_number] ?? null;
    }

    public function isPaymentRequired(): bool
    {
        $stageInfo = $this->getStageInfo();
        return $stageInfo ? $stageInfo['requires_payment'] : $this->requires_payment;
    }

    public function getProgressPercentage(): float
    {
        $totalStages = count(self::STAGES);
        return ($this->stage_number / $totalStages) * 100;
    }

    // Static Methods
    public static function getStageByCode(string $code): ?array
    {
        foreach (self::STAGES as $number => $stage) {
            if ($stage['code'] === $code) {
                return array_merge($stage, ['number' => $number]);
            }
        }
        return null;
    }

    public static function getStageByNumber(int $number): ?array
    {
        return self::STAGES[$number] ?? null;
    }

    public static function getNextStageCode(string $currentCode): ?string
    {
        $current = self::getStageByCode($currentCode);
        if (!$current) return null;

        $nextNumber = $current['number'] + 1;
        $nextStage = self::getStageByNumber($nextNumber);

        return $nextStage ? $nextStage['code'] : null;
    }

    public static function getPreviousStageCode(string $currentCode): ?string
    {
        $current = self::getStageByCode($currentCode);
        if (!$current || $current['number'] <= 1) return null;

        $prevNumber = $current['number'] - 1;
        $prevStage = self::getStageByNumber($prevNumber);

        return $prevStage ? $prevStage['code'] : null;
    }
}
