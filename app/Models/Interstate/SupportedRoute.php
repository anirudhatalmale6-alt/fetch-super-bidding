<?php

namespace App\Models\Interstate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SupportedRoute extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'supported_routes';

    protected $fillable = [
        'trucking_company_id',
        'origin_hub_id',
        'destination_hub_id',
        'origin_city',
        'destination_city',
        'origin_state',
        'destination_state',
        'distance_km',
        'estimated_days',
        'base_rate_per_kg',
        'minimum_charge',
        'express_multiplier',
        'fragile_surcharge_percent',
        'insurance_rate_percent',
        'max_weight_kg',
        'max_length_cm',
        'max_width_cm',
        'max_height_cm',
        'volumetric_divisor',
        'commission_rate',
        'is_active',
        'is_express_available',
        'schedule_info',
        'notes',
    ];

    protected $casts = [
        'distance_km'               => 'float',
        'base_rate_per_kg'          => 'float',
        'minimum_charge'            => 'float',
        'express_multiplier'        => 'float',
        'fragile_surcharge_percent' => 'float',
        'insurance_rate_percent'    => 'float',
        'commission_rate'           => 'float',
        'max_weight_kg'             => 'float',
        'max_length_cm'             => 'float',
        'max_width_cm'              => 'float',
        'max_height_cm'             => 'float',
        'volumetric_divisor'        => 'integer',
        'is_active'                 => 'boolean',
        'is_express_available'      => 'boolean',
    ];

    // ────────────────────────────────────────────────────
    // Relationships
    // ────────────────────────────────────────────────────

    public function truckingCompany()
    {
        return $this->belongsTo(TruckingCompany::class, 'trucking_company_id');
    }

    public function originHub()
    {
        return $this->belongsTo(TruckingHub::class, 'origin_hub_id');
    }

    public function destinationHub()
    {
        return $this->belongsTo(TruckingHub::class, 'destination_hub_id');
    }

    public function requests()
    {
        return $this->hasMany(\App\Models\Request\Request::class, 'supported_route_id');
    }

    public function legs()
    {
        return $this->hasMany(RequestLeg::class, 'supported_route_id');
    }

    // ────────────────────────────────────────────────────
    // Scopes
    // ────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('trucking_company_id', $companyId);
    }

    public function scopeBetween($query, string $originCity, string $destinationCity)
    {
        return $query
            ->where('origin_city', $originCity)
            ->where('destination_city', $destinationCity);
    }

    // ────────────────────────────────────────────────────
    // Accessors / Helpers
    // ────────────────────────────────────────────────────

    /**
     * Return the volumetric divisor used for dimensional weight (default 5000).
     */
    public function getVolumetricDivisor(): int
    {
        return $this->volumetric_divisor ?? 5000;
    }

    /**
     * Human-readable route name.
     */
    public function getRouteNameAttribute(): string
    {
        return "{$this->origin_city} → {$this->destination_city}";
    }

    /**
     * Calculate freight price for a given chargeable weight.
     */
    public function calculateFreight(float $chargeableWeightKg, bool $isExpress = false): float
    {
        $baseFreight = $chargeableWeightKg * $this->base_rate_per_kg;

        if ($baseFreight < $this->minimum_charge) {
            $baseFreight = $this->minimum_charge;
        }

        if ($isExpress && $this->is_express_available) {
            $baseFreight *= ($this->express_multiplier ?? 1.5);
        }

        return round($baseFreight, 2);
    }
}
