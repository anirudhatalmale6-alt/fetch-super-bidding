<?php

namespace App\Models\Interstate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Request\Request;

class InterstateBid extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'request_id',
        'trucking_company_id',
        'transportation_fee',
        'insurance_fee',
        'estimated_delivery_hours',
        'total_bid_amount',
        'status', // pending, accepted, rejected, withdrawn, expired
        'bid_notes',
        'is_revised',
        'original_bid_id',
        'accepted_at',
        'rejected_at',
        'withdrawn_at',
        'expires_at',
    ];

    protected $casts = [
        'transportation_fee' => 'decimal:2',
        'insurance_fee' => 'decimal:2',
        'total_bid_amount' => 'decimal:2',
        'estimated_delivery_hours' => 'integer',
        'is_revised' => 'boolean',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'withdrawn_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // Relationships
    public function request()
    {
        return $this->belongsTo(Request::class);
    }

    public function truckingCompany()
    {
        return $this->belongsTo(TruckingCompany::class);
    }

    public function originalBid()
    {
        return $this->belongsTo(self::class, 'original_bid_id');
    }

    public function revisedBids()
    {
        return $this->hasMany(self::class, 'original_bid_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    public function scopeByCompany($query, $companyId)
    {
        return $query->where('trucking_company_id', $companyId);
    }

    public function scopeForRequest($query, $requestId)
    {
        return $query->where('request_id', $requestId);
    }

    // Helper Methods
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function canBeRevised(): bool
    {
        return $this->status === 'pending' && !$this->isExpired();
    }

    public function canBeWithdrawn(): bool
    {
        return $this->status === 'pending' && !$this->isExpired();
    }

    public function calculateTotal(): float
    {
        return (float) $this->transportation_fee + (float) $this->insurance_fee;
    }

    public function getFormattedDeliveryTime(): string
    {
        $hours = $this->estimated_delivery_hours;
        
        if ($hours < 24) {
            return "{$hours} hours";
        }
        
        $days = floor($hours / 24);
        $remainingHours = $hours % 24;
        
        if ($remainingHours === 0) {
            return "{$days} day" . ($days > 1 ? 's' : '');
        }
        
        return "{$days}d {$remainingHours}h";
    }

    // Boot method for auto-calculation
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($bid) {
            $bid->total_bid_amount = $bid->calculateTotal();
            
            // Set default expiration (e.g., 24 hours)
            if (empty($bid->expires_at)) {
                $bid->expires_at = now()->addHours(24);
            }
        });

        static::updating(function ($bid) {
            // Recalculate total if fees changed
            if ($bid->isDirty(['transportation_fee', 'insurance_fee'])) {
                $bid->total_bid_amount = $bid->calculateTotal();
            }
        });
    }
}
