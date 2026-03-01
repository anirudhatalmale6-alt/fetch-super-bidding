<?php

namespace App\Models\Interstate;

use Illuminate\Database\Eloquent\Model;
use App\Models\Request\Request;

class RequestLeg extends Model
{
    protected $fillable = [
        'request_id',
        'leg_number',
        'leg_type',
        'provider_type',
        'provider_id',
        'provider_name',
        'provider_phone',
        'pickup_location',
        'drop_location',
        'total_actual_weight',
        'total_volumetric_weight',
        'total_chargeable_weight',
        'base_fare',
        'minimum_charge_applied',
        'express_surcharge',
        'fragile_surcharge',
        'insurance_charge',
        'other_surcharges',
        'final_fare',
        'provider_earnings',
        'supported_route_id',
        'pricing_breakdown',
        'status',
        'accepted_at',
        'picked_up_at',
        'completed_at',
        'current_lat',
        'current_lng',
        'pickup_proof',
        'delivery_proof',
    ];

    protected $casts = [
        'pickup_location' => 'array',
        'drop_location' => 'array',
        'pricing_breakdown' => 'array',
        'pickup_proof' => 'array',
        'delivery_proof' => 'array',
        'total_actual_weight' => 'decimal:2',
        'total_volumetric_weight' => 'decimal:2',
        'total_chargeable_weight' => 'decimal:2',
        'base_fare' => 'decimal:2',
        'minimum_charge_applied' => 'decimal:2',
        'express_surcharge' => 'decimal:2',
        'fragile_surcharge' => 'decimal:2',
        'insurance_charge' => 'decimal:2',
        'other_surcharges' => 'decimal:2',
        'final_fare' => 'decimal:2',
        'provider_earnings' => 'decimal:2',
        'current_lat' => 'decimal:8',
        'current_lng' => 'decimal:8',
        'accepted_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Relationship to parent request
     */
    public function request()
    {
        return $this->belongsTo(Request::class);
    }

    /**
     * Polymorphic relationship to provider (Driver or TruckingCompany)
     */
    public function provider()
    {
        return $this->morphTo();
    }

    /**
     * Relationship to supported route (for interstate transport legs)
     */
    public function supportedRoute()
    {
        return $this->belongsTo(SupportedRoute::class);
    }

    /**
     * Get next leg
     */
    public function nextLeg()
    {
        return $this->hasOne(static::class, 'request_id', 'request_id')
            ->where('leg_number', $this->leg_number + 1);
    }

    /**
     * Get previous leg
     */
    public function previousLeg()
    {
        return $this->hasOne(static::class, 'request_id', 'request_id')
            ->where('leg_number', $this->leg_number - 1);
    }

    /**
     * Scope: Active legs (not completed or cancelled)
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['completed', 'cancelled']);
    }

    /**
     * Scope: Pending legs
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: By leg type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('leg_type', $type);
    }

    /**
     * Scope: Local legs (pickup or delivery)
     */
    public function scopeLocal($query)
    {
        return $query->whereIn('leg_type', ['local_pickup', 'local_delivery']);
    }

    /**
     * Scope: Interstate transport legs
     */
    public function scopeInterstate($query)
    {
        return $query->where('leg_type', 'interstate_transport');
    }

    /**
     * Check if leg is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if leg is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if leg is in progress
     */
    public function isInProgress(): bool
    {
        return in_array($this->status, ['accepted', 'driver_arrived', 'picked_up', 'in_transit']);
    }

    /**
     * Get display name for leg type
     */
    public function getDisplayNameAttribute(): string
    {
        $names = [
            'local_pickup' => 'Pickup from Seller',
            'hub_dropoff' => 'Drop at Origin Hub',
            'interstate_transport' => 'Interstate Transport',
            'hub_pickup' => 'Pickup from Hub',
            'local_delivery' => 'Delivery to Buyer',
        ];

        return $names[$this->leg_type] ?? 'Unknown';
    }

    /**
     * Get formatted status
     */
    public function getFormattedStatusAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->status));
    }

    /**
     * Get duration in minutes (if completed)
     */
    public function getDurationMinutesAttribute(): ?int
    {
        if (!$this->completed_at || !$this->picked_up_at) {
            return null;
        }

        return $this->picked_up_at->diffInMinutes($this->completed_at);
    }

    /**
     * Update status with timestamp
     */
    public function updateStatus(string $status, array $additionalData = []): void
    {
        $data = array_merge(['status' => $status], $additionalData);

        // Set timestamps based on status
        switch ($status) {
            case 'accepted':
                $data['accepted_at'] = now();
                break;
            case 'picked_up':
                $data['picked_up_at'] = now();
                break;
            case 'completed':
                $data['completed_at'] = now();
                break;
        }

        $this->update($data);
    }

    /**
     * Update current location
     */
    public function updateLocation(float $lat, float $lng): void
    {
        $this->update([
            'current_lat' => $lat,
            'current_lng' => $lng,
        ]);
    }

    /**
     * Add pickup proof
     */
    public function addPickupProof(array $proof): void
    {
        $this->update([
            'pickup_proof' => array_merge($this->pickup_proof ?? [], $proof),
        ]);
    }

    /**
     * Add delivery proof
     */
    public function addDeliveryProof(array $proof): void
    {
        $this->update([
            'delivery_proof' => array_merge($this->delivery_proof ?? [], $proof),
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Get pickup address
     */
    public function getPickupAddressAttribute(): ?string
    {
        return $this->pickup_location['address'] ?? null;
    }

    /**
     * Get drop address
     */
    public function getDropAddressAttribute(): ?string
    {
        return $this->drop_location['address'] ?? null;
    }

    /**
     * Check if this is a hub-related leg
     */
    public function isHubLeg(): bool
    {
        return in_array($this->leg_type, ['hub_dropoff', 'hub_pickup']);
    }

    /**
     * Get hub ID if hub leg
     */
    public function getHubId(): ?int
    {
        if (!$this->isHubLeg()) {
            return null;
        }

        return $this->pickup_location['hub_id'] ?? null;
    }
}
