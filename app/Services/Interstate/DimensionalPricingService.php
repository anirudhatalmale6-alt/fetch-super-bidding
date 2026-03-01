<?php

namespace App\Services\Interstate;

use App\Models\Interstate\SupportedRoute;
use App\Models\Interstate\RequestPackage;
use App\Models\Request\Request;
use Illuminate\Support\Collection;

/**
 * Service for calculating dimensional freight pricing
 * Uses volumetric weight calculation with configurable divisor
 */
class DimensionalPricingService
{
    /**
     * Standard volumetric divisors
     */
    const DIVISOR_STANDARD = 5000;    // Road freight
    const DIVISOR_AIR = 6000;         // Air freight
    const DIVISOR_EXPRESS = 4000;     // Express courier
    
    /**
     * Calculate volumetric weight for a package
     * 
     * Formula: (Length × Width × Height) / Volumetric Divisor
     * 
     * @param float $lengthCm Length in centimeters
     * @param float $widthCm Width in centimeters
     * @param float $heightCm Height in centimeters
     * @param int $divisor Volumetric divisor (default: 5000)
     * @return float Volumetric weight in kg
     */
    public function calculateVolumetricWeight(
        float $lengthCm,
        float $widthCm,
        float $heightCm,
        int $divisor = self::DIVISOR_STANDARD
    ): float {
        if ($divisor <= 0) {
            throw new \InvalidArgumentException('Volumetric divisor must be greater than 0');
        }
        
        $volume = $lengthCm * $widthCm * $heightCm;
        $volumetricWeight = $volume / $divisor;
        
        return round($volumetricWeight, 2);
    }
    
    /**
     * Calculate chargeable weight for a package
     * Chargeable weight = max(actual weight, volumetric weight)
     * 
     * @param float $actualWeightKg Actual weight in kg
     * @param float $volumetricWeightKg Volumetric weight in kg
     * @return float Chargeable weight in kg
     */
    public function calculateChargeableWeight(
        float $actualWeightKg,
        float $volumetricWeightKg
    ): float {
        return max($actualWeightKg, $volumetricWeightKg);
    }
    
    /**
     * Process package specifications and compute all weights
     * 
     * @param array $packageData Package specifications
     * @param int $volumetricDivisor Divisor to use
     * @return array Computed package data
     */
    public function processPackage(array $packageData, int $volumetricDivisor = self::DIVISOR_STANDARD): array
    {
        $length = $packageData['length_cm'] ?? 0;
        $width = $packageData['width_cm'] ?? 0;
        $height = $packageData['height_cm'] ?? 0;
        $actualWeight = $packageData['actual_weight_kg'] ?? 0;
        $quantity = $packageData['quantity'] ?? 1;
        
        // Calculate volumetric weight for single unit
        $volumetricWeight = $this->calculateVolumetricWeight(
            $length,
            $width,
            $height,
            $volumetricDivisor
        );
        
        // Calculate chargeable weight for single unit
        $chargeableWeight = $this->calculateChargeableWeight(
            $actualWeight,
            $volumetricWeight
        );
        
        // Calculate totals considering quantity
        $totalActualWeight = $actualWeight * $quantity;
        $totalVolumetricWeight = $volumetricWeight * $quantity;
        $totalChargeableWeight = $chargeableWeight * $quantity;
        
        return [
            'actual_weight_kg' => $actualWeight,
            'length_cm' => $length,
            'width_cm' => $width,
            'height_cm' => $height,
            'quantity' => $quantity,
            'volumetric_weight_kg' => $volumetricWeight,
            'chargeable_weight_kg' => $chargeableWeight,
            'volumetric_divisor_used' => $volumetricDivisor,
            'total_actual_weight' => $totalActualWeight,
            'total_volumetric_weight' => $totalVolumetricWeight,
            'total_chargeable_weight' => $totalChargeableWeight,
        ];
    }
    
    /**
     * Calculate freight price for a single package on a specific route
     * 
     * Formula: max(chargeable_weight × price_per_kg, minimum_charge)
     * 
     * @param array $packageData Processed package data
     * @param SupportedRoute $route Route with pricing
     * @param array $options Additional options (express, fragile, insurance)
     * @return FreightCalculationResult
     */
    public function calculatePackageFreight(
        array $packageData,
        SupportedRoute $route,
        array $options = []
    ): FreightCalculationResult {
        
        $chargeableWeight = $packageData['total_chargeable_weight'];
        
        // Apply minimum chargeable weight
        $chargeableWeight = max($chargeableWeight, $route->minimum_chargeable_weight);
        
        // Calculate base freight
        $baseFreight = $chargeableWeight * $route->price_per_kg;
        
        // Apply minimum charge
        $baseFreight = max($baseFreight, $route->minimum_charge);
        
        $calculation = new FreightCalculationResult();
        $calculation->chargeableWeight = $chargeableWeight;
        $calculation->baseFreight = $baseFreight;
        $calculation->minimumChargeApplied = $baseFreight === $route->minimum_charge;
        
        // Calculate surcharges
        $surcharges = [];
        $totalSurcharges = 0;
        
        // Express surcharge
        if ($options['is_express'] ?? false) {
            $expressSurcharge = $baseFreight * ($route->express_surcharge_percent / 100);
            $surcharges['express'] = [
                'percentage' => $route->express_surcharge_percent,
                'amount' => round($expressSurcharge, 2)
            ];
            $totalSurcharges += $expressSurcharge;
            $calculation->expressSurcharge = round($expressSurcharge, 2);
        }
        
        // Fragile surcharge
        if ($options['is_fragile'] ?? false) {
            $fragileSurcharge = $baseFreight * ($route->fragile_surcharge_percent / 100);
            $surcharges['fragile'] = [
                'percentage' => $route->fragile_surcharge_percent,
                'amount' => round($fragileSurcharge, 2)
            ];
            $totalSurcharges += $fragileSurcharge;
            $calculation->fragileSurcharge = round($fragileSurcharge, 2);
        }
        
        // Insurance charge
        if ($options['requires_insurance'] ?? false) {
            $declaredValue = $options['declared_value'] ?? 0;
            $insuranceCharge = $declaredValue * ($route->insurance_rate_percent / 100);
            $surcharges['insurance'] = [
                'rate_percent' => $route->insurance_rate_percent,
                'declared_value' => $declaredValue,
                'amount' => round($insuranceCharge, 2)
            ];
            $totalSurcharges += $insuranceCharge;
            $calculation->insuranceCharge = round($insuranceCharge, 2);
        }
        
        $calculation->surcharges = $surcharges;
        $calculation->totalSurcharges = round($totalSurcharges, 2);
        
        // Calculate final price
        $calculation->totalPrice = round($baseFreight + $totalSurcharges, 2);
        
        // Store calculation breakdown
        $calculation->breakdown = [
            'actual_weight' => $packageData['total_actual_weight'],
            'volumetric_weight' => $packageData['total_volumetric_weight'],
            'chargeable_weight' => $chargeableWeight,
            'price_per_kg' => $route->price_per_kg,
            'base_freight' => $baseFreight,
            'minimum_charge' => $route->minimum_charge,
            'minimum_charge_applied' => $calculation->minimumChargeApplied,
            'surcharges' => $surcharges,
            'total' => $calculation->totalPrice,
        ];
        
        return $calculation;
    }
    
    /**
     * Calculate total freight for multiple packages on a route
     * 
     * @param Collection|array $packages Collection of RequestPackage or package arrays
     * @param SupportedRoute $route Route with pricing
     * @param array $options Additional options
     * @return FreightCalculationResult
     */
    public function calculateTotalFreight(
        $packages,
        SupportedRoute $route,
        array $options = []
    ): FreightCalculationResult {
        
        $totalChargeableWeight = 0;
        $totalActualWeight = 0;
        $totalVolumetricWeight = 0;
        $packageCalculations = [];
        
        foreach ($packages as $package) {
            if ($package instanceof RequestPackage) {
                $totalChargeableWeight += $package->chargeable_weight_kg * $package->quantity;
                $totalActualWeight += $package->actual_weight_kg * $package->quantity;
                $totalVolumetricWeight += $package->volumetric_weight_kg * $package->quantity;
            } else {
                // Process raw package data
                $processed = $this->processPackage($package, $route->volumetric_divisor);
                $totalChargeableWeight += $processed['total_chargeable_weight'];
                $totalActualWeight += $processed['total_actual_weight'];
                $totalVolumetricWeight += $processed['total_volumetric_weight'];
            }
        }
        
        // Apply minimum chargeable weight
        $totalChargeableWeight = max($totalChargeableWeight, $route->minimum_chargeable_weight);
        
        // Calculate base freight
        $baseFreight = $totalChargeableWeight * $route->price_per_kg;
        $baseFreight = max($baseFreight, $route->minimum_charge);
        
        $calculation = new FreightCalculationResult();
        $calculation->chargeableWeight = round($totalChargeableWeight, 2);
        $calculation->baseFreight = round($baseFreight, 2);
        $calculation->minimumChargeApplied = $baseFreight === $route->minimum_charge;
        $calculation->actualWeight = round($totalActualWeight, 2);
        $calculation->volumetricWeight = round($totalVolumetricWeight, 2);
        
        // Calculate surcharges (applied once for the entire shipment)
        $totalSurcharges = 0;
        
        if ($options['is_express'] ?? false) {
            $expressSurcharge = $baseFreight * ($route->express_surcharge_percent / 100);
            $calculation->expressSurcharge = round($expressSurcharge, 2);
            $totalSurcharges += $expressSurcharge;
        }
        
        if ($options['is_fragile'] ?? false) {
            $fragileSurcharge = $baseFreight * ($route->fragile_surcharge_percent / 100);
            $calculation->fragileSurcharge = round($fragileSurcharge, 2);
            $totalSurcharges += $fragileSurcharge;
        }
        
        if ($options['requires_insurance'] ?? false) {
            $declaredValue = $options['declared_value'] ?? 0;
            $insuranceCharge = $declaredValue * ($route->insurance_rate_percent / 100);
            $calculation->insuranceCharge = round($insuranceCharge, 2);
            $totalSurcharges += $insuranceCharge;
        }
        
        $calculation->totalSurcharges = round($totalSurcharges, 2);
        $calculation->totalPrice = round($baseFreight + $totalSurcharges, 2);
        
        $calculation->breakdown = [
            'packages_count' => is_countable($packages) ? count($packages) : 1,
            'total_actual_weight' => $calculation->actualWeight,
            'total_volumetric_weight' => $calculation->volumetricWeight,
            'total_chargeable_weight' => $calculation->chargeableWeight,
            'volumetric_divisor' => $route->volumetric_divisor,
            'price_per_kg' => $route->price_per_kg,
            'base_freight' => $calculation->baseFreight,
            'minimum_charge' => $route->minimum_charge,
            'minimum_charge_applied' => $calculation->minimumChargeApplied,
            'express_surcharge' => $calculation->expressSurcharge,
            'fragile_surcharge' => $calculation->fragileSurcharge,
            'insurance_charge' => $calculation->insuranceCharge,
            'total' => $calculation->totalPrice,
        ];
        
        return $calculation;
    }
    
    /**
     * Validate package against route limits
     * 
     * @param array $package Package dimensions and weight
     * @param SupportedRoute $route Route with limits
     * @return ValidationResult
     */
    public function validatePackageAgainstRoute(array $package, SupportedRoute $route): ValidationResult
    {
        $result = new ValidationResult();
        $result->isValid = true;
        $result->errors = [];
        
        // Check weight limit
        $maxWeight = $route->max_weight_per_package 
            ?? $route->truckingCompany->max_weight_per_package;
            
        if ($package['actual_weight_kg'] > $maxWeight) {
            $result->isValid = false;
            $result->errors[] = [
                'type' => 'weight_exceeded',
                'message' => "Package weight ({$package['actual_weight_kg']} kg) exceeds maximum allowed ({$maxWeight} kg)",
                'max_allowed' => $maxWeight,
                'actual' => $package['actual_weight_kg']
            ];
        }
        
        // Check dimension limits
        $maxDimensions = $route->max_dimensions_cm 
            ?? $route->truckingCompany->max_dimensions_cm;
            
        if ($maxDimensions) {
            $dimensionFields = ['length_cm', 'width_cm', 'height_cm'];
            foreach ($dimensionFields as $field) {
                $maxKey = str_replace('_cm', '', $field);
                if (isset($package[$field]) && $package[$field] > ($maxDimensions[$maxKey] ?? PHP_FLOAT_MAX)) {
                    $result->isValid = false;
                    $result->errors[] = [
                        'type' => 'dimension_exceeded',
                        'message' => ucfirst($maxKey) . " ({$package[$field]} cm) exceeds maximum ({$maxDimensions[$maxKey]} cm)",
                        'dimension' => $maxKey,
                        'max_allowed' => $maxDimensions[$maxKey],
                        'actual' => $package[$field]
                    ];
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Find eligible routes for a package
     * 
     * @param array $package Package specifications
     * @param Collection $routes Available routes
     * @return Collection Eligible routes with pricing
     */
    public function findEligibleRoutes(array $package, Collection $routes): Collection
    {
        return $routes->filter(function ($route) use ($package) {
            $validation = $this->validatePackageAgainstRoute($package, $route);
            return $validation->isValid;
        })->map(function ($route) use ($package) {
            // Calculate pricing for each eligible route
            $processedPackage = $this->processPackage($package, $route->volumetric_divisor);
            $pricing = $this->calculatePackageFreight($processedPackage, $route);
            
            return [
                'route' => $route,
                'pricing' => $pricing,
                'processed_package' => $processedPackage
            ];
        })->sortBy('pricing.totalPrice');
    }
}

/**
 * Data Transfer Object for freight calculation results
 */
class FreightCalculationResult
{
    public float $actualWeight = 0;
    public float $volumetricWeight = 0;
    public float $chargeableWeight = 0;
    public float $baseFreight = 0;
    public bool $minimumChargeApplied = false;
    public float $expressSurcharge = 0;
    public float $fragileSurcharge = 0;
    public float $insuranceCharge = 0;
    public float $totalSurcharges = 0;
    public float $totalPrice = 0;
    public array $surcharges = [];
    public array $breakdown = [];
    
    /**
     * Get summary for display
     */
    public function getSummary(): array
    {
        return [
            'actual_weight' => $this->actualWeight,
            'volumetric_weight' => $this->volumetricWeight,
            'chargeable_weight' => $this->chargeableWeight,
            'base_freight' => $this->baseFreight,
            'surcharges' => [
                'express' => $this->expressSurcharge,
                'fragile' => $this->fragileSurcharge,
                'insurance' => $this->insuranceCharge,
            ],
            'total' => $this->totalPrice,
        ];
    }
}

/**
 * Data Transfer Object for validation results
 */
class ValidationResult
{
    public bool $isValid = true;
    public array $errors = [];
    
    /**
     * Get first error message
     */
    public function getFirstError(): ?string
    {
        return $this->errors[0]['message'] ?? null;
    }
}
