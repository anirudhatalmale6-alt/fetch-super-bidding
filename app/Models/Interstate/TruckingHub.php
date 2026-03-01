<?php

namespace App\Models\Interstate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TruckingHub extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'trucking_hubs';

    protected $fillable = [
        'trucking_company_id',
        'hub_name',
        'name',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'phone',
        'email',
        'latitude',
        'longitude',
        'is_primary',
        'is_active',
        'operating_hours',
        'notes',
    ];

    protected $casts = [
        'latitude'        => 'float',
        'longitude'       => 'float',
        'is_primary'      => 'boolean',
        'is_active'       => 'boolean',
        'operating_hours' => 'array',
    ];

    // ────────────────────────────────────────────────────
    // Relationships
    // ────────────────────────────────────────────────────

    public function truckingCompany()
    {
        return $this->belongsTo(TruckingCompany::class, 'trucking_company_id');
    }

    public function routesAsOrigin()
    {
        return $this->hasMany(SupportedRoute::class, 'origin_hub_id');
    }

    public function routesAsDestination()
    {
        return $this->hasMany(SupportedRoute::class, 'destination_hub_id');
    }

    public function legsAsPickup()
    {
        return $this->hasMany(RequestLeg::class, 'origin_hub_id');
    }

    public function legsAsDrop()
    {
        return $this->hasMany(RequestLeg::class, 'destination_hub_id');
    }

    // ────────────────────────────────────────────────────
    // Scopes
    // ────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeInCity($query, string $city)
    {
        return $query->where('city', $city);
    }

    public function scopeInState($query, string $state)
    {
        return $query->where('state', $state);
    }

    // ────────────────────────────────────────────────────
    // Accessors
    // ────────────────────────────────────────────────────

    public function getHubNameAttribute($value): string
    {
        // Support both 'hub_name' and 'name' columns
        return $value ?? $this->attributes['name'] ?? 'Unknown Hub';
    }

    public function getFullAddressAttribute(): string
    {
        return implode(', ', array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->country,
        ]));
    }
}
