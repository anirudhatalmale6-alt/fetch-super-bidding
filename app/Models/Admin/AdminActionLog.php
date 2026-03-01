<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class AdminActionLog extends Model
{
    protected $fillable = [
        'admin_id',
        'target_id',
        'target_type',
        'action',
        'action_category',
        'previous_state',
        'new_state',
        'description',
        'reason',
        'ip_address',
        'user_agent',
        'risk_level',
        'requires_review',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'previous_state' => 'array',
        'new_state' => 'array',
        'requires_review' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    // Risk levels
    const RISK_LOW = 'low';
    const RISK_MEDIUM = 'medium';
    const RISK_HIGH = 'high';
    const RISK_CRITICAL = 'critical';

    // Action categories
    const CATEGORY_ORDER = 'order';
    const CATEGORY_COMPANY = 'company';
    const CATEGORY_ROUTE = 'route';
    const CATEGORY_HUB = 'hub';
    const CATEGORY_PAYMENT = 'payment';
    const CATEGORY_STAGE = 'stage';

    // Relationships
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // Scopes
    public function scopeByAdmin($query, int $adminId)
    {
        return $query->where('admin_id', $adminId);
    }

    public function scopeByTarget($query, string $targetType, string $targetId)
    {
        return $query->where('target_type', $targetType)
            ->where('target_id', $targetId);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('action_category', $category);
    }

    public function scopeByRiskLevel($query, string $riskLevel)
    {
        return $query->where('risk_level', $riskLevel);
    }

    public function scopeHighRisk($query)
    {
        return $query->whereIn('risk_level', [self::RISK_HIGH, self::RISK_CRITICAL]);
    }

    public function scopePendingReview($query)
    {
        return $query->where('requires_review', true)
            ->whereNull('reviewed_at');
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Helper Methods
    public function isHighRisk(): bool
    {
        return in_array($this->risk_level, [self::RISK_HIGH, self::RISK_CRITICAL]);
    }

    public function isReviewed(): bool
    {
        return !is_null($this->reviewed_at);
    }

    public function markAsReviewed(int $reviewerId): void
    {
        $this->update([
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'requires_review' => false,
        ]);
    }

    public function getChangesSummary(): array
    {
        $changes = [];
        $previous = $this->previous_state ?? [];
        $new = $this->new_state ?? [];

        foreach ($new as $key => $value) {
            if (isset($previous[$key]) && $previous[$key] !== $value) {
                $changes[$key] = [
                    'from' => $previous[$key],
                    'to' => $value,
                ];
            }
        }

        return $changes;
    }

    // Static Methods
    public static function log(
        int $adminId,
        string $action,
        string $category,
        string $description,
        array $options = []
    ): self {
        $data = array_merge([
            'admin_id' => $adminId,
            'action' => $action,
            'action_category' => $category,
            'description' => $description,
            'risk_level' => self::RISK_LOW,
            'requires_review' => false,
        ], $options);

        // Auto-detect high-risk actions
        $highRiskActions = [
            'stage_override',
            'company_blacklist',
            'fee_adjustment',
            'payment_refund',
            'order_cancellation',
        ];

        if (in_array($action, $highRiskActions)) {
            $data['risk_level'] = self::RISK_HIGH;
            $data['requires_review'] = true;
        }

        // Capture IP and user agent if not provided
        if (empty($data['ip_address']) && request()) {
            $data['ip_address'] = request()->ip();
        }
        if (empty($data['user_agent']) && request()) {
            $data['user_agent'] = request()->userAgent();
        }

        return self::create($data);
    }

    public static function logStageOverride(
        int $adminId,
        string $requestId,
        string $fromStage,
        string $toStage,
        string $reason
    ): self {
        return self::log($adminId, 'stage_override', self::CATEGORY_STAGE,
            "Stage manually overridden from '{$fromStage}' to '{$toStage}'",
            [
                'target_type' => 'request',
                'target_id' => $requestId,
                'previous_state' => ['stage' => $fromStage],
                'new_state' => ['stage' => $toStage],
                'reason' => $reason,
            ]
        );
    }

    public static function logCompanyBlacklist(
        int $adminId,
        int $companyId,
        string $reason
    ): self {
        return self::log($adminId, 'company_blacklist', self::CATEGORY_COMPANY,
            "Company ID {$companyId} blacklisted",
            [
                'target_type' => 'company',
                'target_id' => $companyId,
                'reason' => $reason,
            ]
        );
    }

    public static function logFeeAdjustment(
        int $adminId,
        string $requestId,
        float $originalFee,
        float $newFee,
        string $reason
    ): self {
        return self::log($adminId, 'fee_adjustment', self::CATEGORY_PAYMENT,
            "Fee adjusted from {$originalFee} to {$newFee}",
            [
                'target_type' => 'request',
                'target_id' => $requestId,
                'previous_state' => ['fee' => $originalFee],
                'new_state' => ['fee' => $newFee],
                'reason' => $reason,
            ]
        );
    }

    public static function getRecentActions(int $limit = 50)
    {
        return self::with('admin')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public static function getPendingReviewCount(): int
    {
        return self::pendingReview()->count();
    }

    public static function getActionsForTarget(string $targetType, string $targetId)
    {
        return self::byTarget($targetType, $targetId)
            ->with('admin')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
