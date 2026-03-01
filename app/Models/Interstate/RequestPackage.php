<?php

namespace App\Models\Interstate;

use Illuminate\Database\Eloquent\Model;
use App\Models\Request\Request;
use App\Services\Interstate\DimensionalPricingService;

class RequestPackage extends Model
{
    protected $fillable = [
        'request_id',
        'package_number',
        'package_index',
        'description',
        // User estimates (for initial bid)
        'estimated_weight_kg',
        'estimated_length_cm',
        'estimated_width_cm',
        'estimated_height_cm',
        'estimated_declared_value',
        // Actual measured values (confirmed by company)
        'actual_weight_kg',
        'actual_length_cm',
        'actual_width_cm',
        'actual_height_cm',
        'actual_declared_value',
        // Final measured values (after physical inspection)
        'final_weight_kg',
        'final_length_cm',
        'final_width_cm',
        'final_height_cm',
        'final_declared_value',
        'final_volumetric_weight_kg',
        'final_chargeable_weight_kg',
        'weight_discrepancy_percent',
        'discrepancy_notes',
        // Weight calculations
        'length_cm',
        'width_cm',
        'height_cm',
        'quantity',
        'volumetric_weight_kg',
        'chargeable_weight_kg',
        'volumetric_divisor_used',
        'declared_value',
        'is_fragile',
        'requires_insurance',
        'special_instructions',
        // Weight confirmation status
        'weight_confirmed',
        'weight_discrepancy',
        'discrepancy_reason',
        'adjustment_approved',
        'adjustment_approved_at',
    ];

    protected $casts = [
        'actual_weight_kg' => 'decimal:2',
        'length_cm' => 'decimal:2',
        'width_cm' => 'decimal:2',
        'height_cm' => 'decimal:2',
        'volumetric_weight_kg' => 'decimal:2',
        'chargeable_weight_kg' => 'decimal:2',
        'declared_value' => 'decimal:2',
        'is_fragile' => 'boolean',
        'requires_insurance' => 'boolean',
        'special_instructions' => 'array',
    ];

    /**
     * Boot method to auto-calculate weights on create/update
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($package) {
            $package->calculateWeights();
            $package->generatePackageNumber();
        });

        static::updating(function ($package) {
            // Recalculate if dimensions or weight changed
            if ($package->isDirty(['actual_weight_kg', 'length_cm', 'width_cm', 'height_cm'])) {
                $package->calculateWeights();
            }
        });
    }

    /**
     * Relationship to parent request
     */
    public function request()
    {
        return $this->belongsTo(Request::class);
    }

    /**
     * Calculate volumetric and chargeable weights
     */
    public function calculateWeights(int $divisor = DimensionalPricingService::DIVISOR_STANDARD): void
    {
        $service = app(DimensionalPricingService::class);

        $this->volumetric_weight_kg = $service->calculateVolumetricWeight(
            (float) $this->length_cm,
            (float) $this->width_cm,
            (float) $this->height_cm,
            $divisor
        );

        $this->chargeable_weight_kg = $service->calculateChargeableWeight(
            (float) $this->actual_weight_kg,
            $this->volumetric_weight_kg
        );

        $this->volumetric_divisor_used = $divisor;
    }

    /**
     * Get total chargeable weight considering quantity
     */
    public function getTotalChargeableWeight(): float
    {
        return $this->chargeable_weight_kg * $this->quantity;
    }

    /**
     * Get total actual weight considering quantity
     */
    public function getTotalActualWeight(): float
    {
        return $this->actual_weight_kg * $this->quantity;
    }

    /**
     * Get total volumetric weight considering quantity
     */
    public function getTotalVolumetricWeight(): float
    {
        return $this->volumetric_weight_kg * $this->quantity;
    }

    /**
     * Get volume in cubic centimeters
     */
    public function getVolumeCubicCm(): float
    {
        return $this->length_cm * $this->width_cm * $this->height_cm;
    }

    /**
     * Get volume in cubic meters
     */
    public function getVolumeCubicMeters(): float
    {
        return $this->getVolumeCubicCm() / 1000000;
    }

    /**
     * Generate unique package number
     */
    protected function generatePackageNumber(): void
    {
        if (empty($this->package_number)) {
            $prefix = 'PKG';
            $requestPart = $this->request_id ? substr($this->request_id, -6) : substr(uniqid(), -6);
            $index = str_pad($this->package_index ?? 1, 3, '0', STR_PAD_LEFT);
            $random = substr(uniqid(), -4);

            $this->package_number = "{$prefix}-{$requestPart}-{$index}-{$random}";
        }
    }

    /**
     * Scope for fragile packages
     */
    public function scopeFragile($query)
    {
        return $query->where('is_fragile', true);
    }

    /**
     * Scope for insured packages
     */
    public function scopeInsured($query)
    {
        return $query->where('requires_insurance', true);
    }

    /**
     * Get formatted dimensions string
     */
    public function getFormattedDimensionsAttribute(): string
    {
        return "{$this->length_cm} × {$this->width_cm} × {$this->height_cm} cm";
    }

    /**
     * Get weight summary for display
     */
    public function getWeightSummaryAttribute(): array
    {
        return [
            'actual' => $this->actual_weight_kg,
            'volumetric' => $this->volumetric_weight_kg,
            'chargeable' => $this->chargeable_weight_kg,
            'quantity' => $this->quantity,
            'total_chargeable' => $this->getTotalChargeableWeight(),
        ];
    }
}
