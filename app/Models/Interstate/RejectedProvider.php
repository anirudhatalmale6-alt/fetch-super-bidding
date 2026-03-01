<?php

namespace App\Models\Interstate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Request\Request;

class RejectedProvider extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'request_id',
        'provider_type',
        'provider_id',
        'rejection_type',
        'rejection_reason',
        'rejected_by_type',
        'rejected_by_id',
        'stage_code_at_rejection',
        'rerouting_attempt_number',
        'rejected_at',
    ];

    protected $casts = [
        'rejected_at' => 'datetime',
        'rerouting_attempt_number' => 'integer',
    ];

    // Rejection types
    const REJECTION_BID_REJECTED = 'bid_rejected';
    const REJECTION_USER_DECLINED = 'user_declined';
    const REJECTION_TIMEOUT = 'timeout';
    const REJECTION_PERFORMANCE_ISSUE = 'performance_issue';
    const REJECTION_ADMIN_REMOVED = 'admin_removed';

    // Relationships
    public function request()
    {
        return $this->belongsTo(Request::class, 'request_id');
    }

    public function provider()
    {
        return $this->morphTo();
    }

    public function rejectedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'rejected_by_id');
    }

    // Scopes
    public function scopeForRequest($query, string $requestId)
    {
        return $query->where('request_id', $requestId);
    }

    public function scopeForProvider($query, string $providerType, int $providerId)
    {
        return $query->where('provider_type', $providerType)
            ->where('provider_id', $providerId);
    }

    public function scopeByType($query, string $rejectionType)
    {
        return $query->where('rejection_type', $rejectionType);
    }

    public function scopeByReroutingAttempt($query, int $attemptNumber)
    {
        return $query->where('rerouting_attempt_number', $attemptNumber);
    }

    public function scopePermanent($query)
    {
        return $query->whereIn('rejection_type', [
            self::REJECTION_PERFORMANCE_ISSUE,
            self::REJECTION_ADMIN_REMOVED,
        ]);
    }

    // Helper Methods
    public function isBidRejected(): bool
    {
        return $this->rejection_type === self::REJECTION_BID_REJECTED;
    }

    public function isUserDeclined(): bool
    {
        return $this->rejection_type === self::REJECTION_USER_DECLINED;
    }

    public function isTimeout(): bool
    {
        return $this->rejection_type === self::REJECTION_TIMEOUT;
    }

    public function isPermanent(): bool
    {
        return in_array($this->rejection_type, [
            self::REJECTION_PERFORMANCE_ISSUE,
            self::REJECTION_ADMIN_REMOVED,
        ]);
    }

    // Static Methods
    public static function recordRejection(
        string $requestId,
        $provider,
        string $rejectionType,
        string $reason = null,
        $rejectedBy = null,
        string $stageCode = null,
        int $reroutingAttempt = 0
    ): self {
        return self::create([
            'request_id' => $requestId,
            'provider_type' => get_class($provider),
            'provider_id' => $provider->id,
            'rejection_type' => $rejectionType,
            'rejection_reason' => $reason,
            'rejected_by_type' => $rejectedBy ? 'user' : 'system',
            'rejected_by_id' => $rejectedBy?->id,
            'stage_code_at_rejection' => $stageCode,
            'rerouting_attempt_number' => $reroutingAttempt,
            'rejected_at' => now(),
        ]);
    }

    public static function isProviderRejectedForRequest(
        string $requestId,
        string $providerType,
        int $providerId
    ): bool {
        return self::forRequest($requestId)
            ->forProvider($providerType, $providerId)
            ->exists();
    }

    public static function getRejectedProviderIdsForRequest(
        string $requestId,
        string $providerType = null
    ): array {
        $query = self::forRequest($requestId);

        if ($providerType) {
            $query->where('provider_type', $providerType);
        }

        return $query->pluck('provider_id')->unique()->toArray();
    }

    public static function canProviderBidOnRequest(
        $provider,
        string $requestId,
        int $currentReroutingAttempt = 0
    ): bool {
        $rejection = self::forRequest($requestId)
            ->forProvider(get_class($provider), $provider->id)
            ->first();

        if (!$rejection) {
            return true;
        }

        // Permanent rejections block forever
        if ($rejection->isPermanent()) {
            return false;
        }

        // If it's a new rerouting attempt, allow bidding again
        if ($currentReroutingAttempt > $rejection->rerouting_attempt_number) {
            return true;
        }

        return false;
    }
}
