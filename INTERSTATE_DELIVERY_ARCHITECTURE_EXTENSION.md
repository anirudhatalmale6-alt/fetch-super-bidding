# INTERSTATE DELIVERY ARCHITECTURE EXTENSION
## Commission, Sequential Payment, Insurance & Request Flow

**Version:** 2.0  
**Date:** 2026-02-11  
**Status:** Technical Implementation Specification

---

## TABLE OF CONTENTS

1. [Executive Summary](#1-executive-summary)
2. [Revised Database Schema](#2-revised-database-schema)
3. [Commission System](#3-commission-system)
4. [Insurance System](#4-insurance-system)
5. [Sequential Leg Payment Flow](#5-sequential-leg-payment-flow)
6. [Request Flow & UI Structure](#6-request-flow--ui-structure)
7. [API Endpoints](#7-api-endpoints)
8. [Financial Settlement Logic](#8-financial-settlement-logic)
9. [Edge Cases & Handling](#9-edge-cases--handling)

---

## 1. EXECUTIVE SUMMARY

This document extends the existing Interstate Delivery Architecture with:

| Feature | Description | Priority |
|---------|-------------|----------|
| **Commission Per Leg** | Platform commission per leg type (percentage or fixed) | Mandatory |
| **Sequential Payment** | Pay-per-leg instead of upfront full payment | Mandatory |
| **Insurance Fee** | Trucking company insurance per goods item | Mandatory |
| **Request Flow** | Multi-step UI with chain builder | Mandatory |

---

## 2. REVISED DATABASE SCHEMA

### 2.1 Platform Commission Configuration

```sql
-- NEW TABLE: platform_commission_configs
-- Admin-configurable commission per leg type
CREATE TABLE platform_commission_configs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- Commission target
    leg_type ENUM('local_pickup', 'interstate_trucking', 'local_dropoff') NOT NULL,
    
    -- Commission structure
    commission_type ENUM('percentage', 'fixed_amount') NOT NULL DEFAULT 'percentage',
    commission_value DECIMAL(10,2) NOT NULL DEFAULT 10.00, -- % or fixed NGN
    
    -- Constraints
    min_commission_amount DECIMAL(10,2) DEFAULT 0.00,
    max_commission_amount DECIMAL(10,2) DEFAULT NULL,
    
    -- Application rules
    apply_to ENUM('base_price', 'total_with_insurance') DEFAULT 'base_price',
    
    -- Status
    is_active BOOLEAN DEFAULT TRUE,
    effective_from TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    effective_until TIMESTAMP NULL,
    
    -- Audit
    created_by BIGINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_leg_type (leg_type),
    INDEX idx_active (is_active)
);

-- Seed default commissions
INSERT INTO platform_commission_configs (leg_type, commission_type, commission_value) VALUES
('local_pickup', 'percentage', 15.00),      -- 15% of rider fare
('interstate_trucking', 'percentage', 10.00), -- 10% of trucking fee
('local_dropoff', 'percentage', 15.00);       -- 15% of rider fare
```

### 2.2 Goods Items Table (Per-Item Insurance)

```sql
-- NEW TABLE: trucking_goods_items
-- Individual goods items for insurance calculation
CREATE TABLE trucking_goods_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT UNSIGNED NOT NULL,
    request_leg_id BIGINT UNSIGNED, -- Associated with interstate transport leg
    
    -- Item identification
    item_number VARCHAR(50) NOT NULL, -- ITM-12345-001
    item_index INTEGER DEFAULT 1,
    
    -- Item details
    description VARCHAR(255),
    category ENUM('electronics', 'fashion', 'food', 'documents', 'fragile', 'general') DEFAULT 'general',
    
    -- Physical specs
    weight_kg DECIMAL(8,2) NOT NULL,
    length_cm DECIMAL(8,2) DEFAULT 0,
    width_cm DECIMAL(8,2) DEFAULT 0,
    height_cm DECIMAL(8,2) DEFAULT 0,
    quantity INTEGER DEFAULT 1,
    
    -- Computed weights
    volumetric_weight_kg DECIMAL(8,2),
    chargeable_weight_kg DECIMAL(8,2),
    
    -- Insurance
    declared_value DECIMAL(12,2) NOT NULL DEFAULT 0,
    requires_insurance BOOLEAN DEFAULT FALSE,
    insurance_type ENUM('none', 'basic', 'premium') DEFAULT 'none',
    insurance_rate_percent DECIMAL(5,2) DEFAULT 0, -- Company-specific rate
    insurance_fee DECIMAL(10,2) DEFAULT 0,
    
    -- Special handling
    is_fragile BOOLEAN DEFAULT FALSE,
    is_hazardous BOOLEAN DEFAULT FALSE,
    is_perishable BOOLEAN DEFAULT FALSE,
    special_instructions TEXT,
    
    -- Status
    status ENUM('pending', 'in_transit', 'delivered', 'damaged', 'lost') DEFAULT 'pending',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    FOREIGN KEY (request_leg_id) REFERENCES request_legs(id) ON DELETE SET NULL,
    INDEX idx_request (request_id),
    INDEX idx_leg (request_leg_id),
    INDEX idx_category (category),
    INDEX idx_status (status)
);
```

### 2.3 Trucking Company Insurance Configuration

```sql
-- ALTER TABLE: trucking_companies
-- Add insurance configuration columns
ALTER TABLE trucking_companies ADD COLUMN (
    -- Insurance type configuration
    insurance_type ENUM('percentage_of_value', 'fixed_per_shipment', 'per_item_rate') DEFAULT 'percentage_of_value',
    insurance_rate_percent DECIMAL(5,2) DEFAULT 1.00, -- Default 1% of declared value
    insurance_fixed_amount DECIMAL(10,2) DEFAULT 0, -- Fixed amount per shipment
    insurance_minimum_amount DECIMAL(10,2) DEFAULT 500.00, -- Minimum insurance fee
    insurance_maximum_amount DECIMAL(10,2) DEFAULT 50000.00, -- Maximum insurance cap
    insurance_mandatory BOOLEAN DEFAULT FALSE, -- Is insurance mandatory?
    
    -- Insurance categories (different rates per category)
    insurance_category_rates JSON NULL, -- {"electronics": 2.0, "fashion": 1.0, "fragile": 3.0}
    
    -- Self insurance or third party
    insurance_provider_type ENUM('self_insured', 'third_party') DEFAULT 'self_insured',
    insurance_provider_name VARCHAR(255) NULL,
    insurance_policy_number VARCHAR(100) NULL,
    
    -- Claims configuration
    max_claim_amount DECIMAL(12,2) DEFAULT 100000.00,
    claim_processing_days INTEGER DEFAULT 14
);

-- ALTER TABLE: supported_routes
-- Route-specific insurance overrides
ALTER TABLE supported_routes ADD COLUMN (
    insurance_rate_override DECIMAL(5,2) NULL, -- Override company default
    insurance_minimum_override DECIMAL(10,2) NULL,
    insurance_notes TEXT NULL
);
```

### 2.4 Leg Financial Records (Enhanced)

```sql
-- ALTER TABLE: request_legs
-- Add detailed financial tracking per leg
ALTER TABLE request_legs ADD COLUMN (
    -- Price components (stored separately for transparency)
    provider_base_price DECIMAL(10,2) DEFAULT 0, -- What provider charges
    insurance_fee DECIMAL(10,2) DEFAULT 0, -- Insurance cost
    platform_commission DECIMAL(10,2) DEFAULT 0, -- Platform fee
    total_leg_price DECIMAL(10,2) DEFAULT 0, -- Final price to customer
    
    -- Provider payout tracking
    provider_payout_amount DECIMAL(10,2) DEFAULT 0, -- After commission deduction
    provider_payout_status ENUM('pending', 'processing', 'paid', 'failed') DEFAULT 'pending',
    provider_payout_at TIMESTAMP NULL,
    provider_payout_reference VARCHAR(100) NULL,
    
    -- Commission breakdown
    commission_calculation_type ENUM('percentage', 'fixed') NULL,
    commission_rate_used DECIMAL(8,2) NULL,
    
    -- Payment tracking (per leg)
    payment_status ENUM('pending', 'awaiting_confirmation', 'paid', 'refunded', 'failed') DEFAULT 'pending',
    paid_amount DECIMAL(10,2) DEFAULT 0,
    paid_at TIMESTAMP NULL,
    payment_reference VARCHAR(100) NULL,
    
    -- Refund tracking
    refund_amount DECIMAL(10,2) DEFAULT 0,
    refund_reason TEXT NULL,
    refunded_at TIMESTAMP NULL,
    
    -- Weight verification adjustment
    original_price DECIMAL(10,2) NULL, -- Before weight verification
    price_adjustment DECIMAL(10,2) DEFAULT 0, -- +/- from weight verification
    adjustment_reason ENUM('weight_verification', 'user_change', 'admin_override') NULL
);

-- ALTER TABLE: leg_payments
-- Enhance for sequential payment tracking
ALTER TABLE leg_payments ADD COLUMN (
    -- Sequential payment state
    leg_state ENUM('awaiting_payment', 'payment_pending', 'paid', 'in_progress', 'completed', 'failed') DEFAULT 'awaiting_payment',
    
    -- Payment authorization
    authorization_code VARCHAR(100) NULL,
    authorized_at TIMESTAMP NULL,
    authorization_expires_at TIMESTAMP NULL,
    
    -- Display info for customer
    display_breakdown JSON NULL, -- Store breakdown for UI display
    
    -- Next leg trigger
    triggers_next_leg BOOLEAN DEFAULT TRUE,
    next_leg_triggered_at TIMESTAMP NULL
);
```

### 2.5 Request Level Financial Summary

```sql
-- ALTER TABLE: requests
-- Add aggregated financial tracking
ALTER TABLE requests ADD COLUMN (
    -- Aggregated financials
    total_provider_base DECIMAL(10,2) DEFAULT 0,
    total_insurance_fee DECIMAL(10,2) DEFAULT 0,
    total_platform_commission DECIMAL(10,2) DEFAULT 0,
    
    -- Payment progress
    legs_paid_count INTEGER DEFAULT 0,
    legs_completed_count INTEGER DEFAULT 0,
    total_paid_amount DECIMAL(12,2) DEFAULT 0,
    remaining_balance DECIMAL(12,2) DEFAULT 0,
    
    -- Current leg payment status
    current_leg_payment_status ENUM('not_required', 'awaiting_payment', 'payment_pending', 'paid') DEFAULT 'not_required',
    
    -- Insurance summary
    total_declared_value DECIMAL(12,2) DEFAULT 0,
    insurance_coverage_status ENUM('none', 'partial', 'full') DEFAULT 'none'
);
```

---

## 3. COMMISSION SYSTEM

### 3.1 Commission Calculation Pseudocode

```php
<?php

/**
 * Commission Calculation Service
 * Centralized commission logic - NOT hardcoded per leg
 */
class CommissionCalculationService
{
    /**
     * Calculate commission for any leg type
     * 
     * @param string $legType 'local_pickup' | 'interstate_trucking' | 'local_dropoff'
     * @param float $baseAmount Provider's base price
     * @param float $insuranceFee Insurance amount (for commission base calculation)
     * @return CommissionBreakdown
     */
    public function calculateCommission(
        string $legType,
        float $baseAmount,
        float $insuranceFee = 0
    ): CommissionBreakdown {
        
        // 1. Get active commission config for leg type
        $config = PlatformCommissionConfig::where('leg_type', $legType)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('effective_until')
                  ->orWhere('effective_until', '>', now());
            })
            ->first();
        
        if (!$config) {
            // Fallback: no commission
            return new CommissionBreakdown(
                baseAmount: $baseAmount,
                commissionType: 'none',
                commissionRate: 0,
                commissionAmount: 0,
                totalPrice: $baseAmount,
                providerPayout: $baseAmount
            );
        }
        
        // 2. Determine commission base amount
        $commissionBase = match($config->apply_to) {
            'base_price' => $baseAmount,
            'total_with_insurance' => $baseAmount + $insuranceFee,
            default => $baseAmount
        };
        
        // 3. Calculate commission amount
        $commissionAmount = match($config->commission_type) {
            'percentage' => $commissionBase * ($config->commission_value / 100),
            'fixed_amount' => $config->commission_value,
            default => 0
        };
        
        // 4. Apply min/max constraints
        if ($config->min_commission_amount > 0) {
            $commissionAmount = max($commissionAmount, $config->min_commission_amount);
        }
        if ($config->max_commission_amount > 0) {
            $commissionAmount = min($commissionAmount, $config->max_commission_amount);
        }
        
        // 5. Calculate final values
        $totalPrice = $baseAmount + $insuranceFee + $commissionAmount;
        $providerPayout = $baseAmount; // Provider gets base price only
        
        return new CommissionBreakdown(
            baseAmount: $baseAmount,
            insuranceFee: $insuranceFee,
            commissionType: $config->commission_type,
            commissionRate: $config->commission_value,
            commissionAmount: round($commissionAmount, 2),
            totalPrice: round($totalPrice, 2),
            providerPayout: round($providerPayout, 2),
            platformRevenue: round($commissionAmount, 2)
        );
    }
    
    /**
     * Calculate commission for a complete request leg
     */
    public function calculateLegCommission(RequestLeg $leg): CommissionBreakdown
    {
        $goodsItems = TruckingGoodsItem::where('request_leg_id', $leg->id)->get();
        $totalInsurance = $goodsItems->sum('insurance_fee');
        
        return $this->calculateCommission(
            legType: $leg->leg_type,
            baseAmount: $leg->provider_base_price,
            insuranceFee: $totalInsurance
        );
    }
    
    /**
     * Get commission config for admin panel
     */
    public function getCommissionConfigs(): Collection
    {
        return PlatformCommissionConfig::all()->map(fn($config) => [
            'leg_type' => $config->leg_type,
            'leg_type_display' => $this->getLegTypeDisplay($config->leg_type),
            'commission_type' => $config->commission_type,
            'commission_value' => $config->commission_value,
            'commission_display' => $config->commission_type === 'percentage' 
                ? "{$config->commission_value}%" 
                : "₦" . number_format($config->commission_value, 2),
            'is_active' => $config->is_active,
            'effective_from' => $config->effective_from,
        ]);
    }
    
    /**
     * Update commission config (admin only)
     */
    public function updateCommissionConfig(
        string $legType,
        string $commissionType,
        float $commissionValue,
        ?float $minAmount = null,
        ?float $maxAmount = null,
        ?int $adminId = null
    ): PlatformCommissionConfig {
        
        // Deactivate existing config
        PlatformCommissionConfig::where('leg_type', $legType)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'effective_until' => now()
            ]);
        
        // Create new config
        return PlatformCommissionConfig::create([
            'leg_type' => $legType,
            'commission_type' => $commissionType,
            'commission_value' => $commissionValue,
            'min_commission_amount' => $minAmount ?? 0,
            'max_commission_amount' => $maxAmount,
            'is_active' => true,
            'effective_from' => now(),
            'created_by' => $adminId
        ]);
    }
}

/**
 * DTO for commission breakdown
 */
class CommissionBreakdown
{
    public function __construct(
        public float $baseAmount,
        public float $insuranceFee = 0,
        public string $commissionType = 'percentage',
        public float $commissionRate = 0,
        public float $commissionAmount = 0,
        public float $totalPrice = 0,
        public float $providerPayout = 0,
        public float $platformRevenue = 0
    ) {}
    
    /**
     * Get display array for API response
     */
    public function toArray(): array
    {
        return [
            'provider_base_price' => $this->baseAmount,
            'insurance_fee' => $this->insuranceFee,
            'platform_commission' => [
                'type' => $this->commissionType,
                'rate' => $this->commissionRate,
                'amount' => $this->commissionAmount,
            ],
            'total_leg_price' => $this->totalPrice,
            'provider_payout' => $this->providerPayout,
            'platform_revenue' => $this->platformRevenue,
        ];
    }
}
```

### 3.2 Commission Configuration Admin Panel

```php
<?php

// app/Http/Controllers/Web/Admin/CommissionConfigController.php

class CommissionConfigController extends Controller
{
    public function index()
    {
        $configs = PlatformCommissionConfig::all();
        return view('admin.commission.index', compact('configs'));
    }
    
    public function update(Request $request, string $legType)
    {
        $validated = $request->validate([
            'commission_type' => 'required|in:percentage,fixed_amount',
            'commission_value' => 'required|numeric|min:0',
            'min_commission_amount' => 'nullable|numeric|min:0',
            'max_commission_amount' => 'nullable|numeric|min:0',
            'apply_to' => 'required|in:base_price,total_with_insurance',
        ]);
        
        $service = new CommissionCalculationService();
        $config = $service->updateCommissionConfig(
            legType: $legType,
            commissionType: $validated['commission_type'],
            commissionValue: $validated['commission_value'],
            minAmount: $validated['min_commission_amount'] ?? null,
            maxAmount: $validated['max_commission_amount'] ?? null,
            adminId: auth()->id()
        );
        
        return redirect()->back()->with('success', 'Commission configuration updated');
    }
}
```

---

## 4. INSURANCE SYSTEM

### 4.1 Insurance Calculation Pseudocode

```php
<?php

/**
 * Insurance Calculation Service
 * Handles per-item insurance calculation based on trucking company config
 */
class InsuranceCalculationService
{
    /**
     * Calculate insurance for a single goods item
     * 
     * @param TruckingCompany $company The trucking company providing insurance
     * @param TruckingGoodsItem $item The goods item
     * @param SupportedRoute|null $route Optional route for overrides
     * @return InsuranceBreakdown
     */
    public function calculateItemInsurance(
        TruckingCompany $company,
        TruckingGoodsItem $item,
        ?SupportedRoute $route = null
    ): InsuranceBreakdown {
        
        // 1. Check if insurance is required
        if (!$item->requires_insurance && !$company->insurance_mandatory) {
            return new InsuranceBreakdown(
                declaredValue: $item->declared_value,
                insuranceType: 'none',
                insuranceRate: 0,
                insuranceFee: 0,
                isMandatory: false
            );
        }
        
        // 2. Determine insurance rate
        $rate = $this->determineInsuranceRate($company, $item, $route);
        
        // 3. Calculate base insurance fee
        $baseFee = match($company->insurance_type) {
            'percentage_of_value' => $item->declared_value * ($rate / 100),
            'fixed_per_shipment' => $company->insurance_fixed_amount / max(1, $item->quantity),
            'per_item_rate' => $this->calculatePerItemRate($company, $item),
            default => 0
        };
        
        // 4. Apply minimum/maximum constraints
        $fee = max($baseFee, $company->insurance_minimum_amount);
        if ($company->insurance_maximum_amount > 0) {
            $fee = min($fee, $company->insurance_maximum_amount);
        }
        
        return new InsuranceBreakdown(
            declaredValue: $item->declared_value,
            insuranceType: $company->insurance_type,
            insuranceRate: $rate,
            insuranceFee: round($fee, 2),
            isMandatory: $company->insurance_mandatory,
            coverageLimit: $company->max_claim_amount,
            providerType: $company->insurance_provider_type,
            providerName: $company->insurance_provider_name
        );
    }
    
    /**
     * Calculate total insurance for all items in a leg
     */
    public function calculateLegInsurance(
        TruckingCompany $company,
        int $requestLegId,
        ?SupportedRoute $route = null
    ): array {
        $items = TruckingGoodsItem::where('request_leg_id', $requestLegId)->get();
        
        $itemBreakdowns = [];
        $totalInsurance = 0;
        $totalDeclaredValue = 0;
        
        foreach ($items as $item) {
            $breakdown = $this->calculateItemInsurance($company, $item, $route);
            $itemBreakdowns[] = [
                'item_id' => $item->id,
                'item_number' => $item->item_number,
                'description' => $item->description,
                'declared_value' => $item->declared_value,
                'insurance' => $breakdown->toArray()
            ];
            $totalInsurance += $breakdown->insuranceFee;
            $totalDeclaredValue += $item->declared_value;
        }
        
        return [
            'items' => $itemBreakdowns,
            'summary' => [
                'total_items' => count($items),
                'total_declared_value' => $totalDeclaredValue,
                'total_insurance_fee' => round($totalInsurance, 2),
                'average_rate' => $totalDeclaredValue > 0 
                    ? round(($totalInsurance / $totalDeclaredValue) * 100, 2) 
                    : 0
            ]
        ];
    }
    
    /**
     * Determine applicable insurance rate
     */
    private function determineInsuranceRate(
        TruckingCompany $company,
        TruckingGoodsItem $item,
        ?SupportedRoute $route = null
    ): float {
        
        // 1. Check for route override
        if ($route && $route->insurance_rate_override !== null) {
            return $route->insurance_rate_override;
        }
        
        // 2. Check for category-specific rate
        if ($company->insurance_category_rates) {
            $categoryRates = json_decode($company->insurance_category_rates, true);
            if (isset($categoryRates[$item->category])) {
                return (float) $categoryRates[$item->category];
            }
        }
        
        // 3. Return default rate
        return $company->insurance_rate_percent;
    }
    
    /**
     * Calculate per-item rate (for 'per_item_rate' insurance type)
     */
    private function calculatePerItemRate(TruckingCompany $company, TruckingGoodsItem $item): float
    {
        // Base rate per kg of chargeable weight
        $weightRate = 50.00; // ₦50 per kg (configurable)
        return $item->chargeable_weight_kg * $weightRate;
    }
    
    /**
     * Create goods items from request data
     */
    public function createGoodsItems(
        int $requestId,
        int $requestLegId,
        array $itemsData,
        int $volumetricDivisor = 5000
    ): array {
        $createdItems = [];
        
        foreach ($itemsData as $index => $itemData) {
            // Calculate volumetric weight
            $volumetricWeight = ($itemData['length_cm'] * $itemData['width_cm'] * $itemData['height_cm']) / $volumetricDivisor;
            
            // Chargeable weight is the higher of actual or volumetric
            $chargeableWeight = max($itemData['weight_kg'], $volumetricWeight);
            
            $item = TruckingGoodsItem::create([
                'request_id' => $requestId,
                'request_leg_id' => $requestLegId,
                'item_number' => "ITM-{$requestId}-" . str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                'item_index' => $index + 1,
                'description' => $itemData['description'] ?? null,
                'category' => $itemData['category'] ?? 'general',
                'weight_kg' => $itemData['weight_kg'],
                'length_cm' => $itemData['length_cm'] ?? 0,
                'width_cm' => $itemData['width_cm'] ?? 0,
                'height_cm' => $itemData['height_cm'] ?? 0,
                'quantity' => $itemData['quantity'] ?? 1,
                'volumetric_weight_kg' => round($volumetricWeight, 2),
                'chargeable_weight_kg' => round($chargeableWeight, 2),
                'declared_value' => $itemData['declared_value'] ?? 0,
                'requires_insurance' => $itemData['requires_insurance'] ?? false,
                'is_fragile' => $itemData['is_fragile'] ?? false,
                'special_instructions' => $itemData['special_instructions'] ?? null,
            ]);
            
            $createdItems[] = $item;
        }
        
        return $createdItems;
    }
}

/**
 * DTO for insurance breakdown
 */
class InsuranceBreakdown
{
    public function __construct(
        public float $declaredValue,
        public string $insuranceType = 'none',
        public float $insuranceRate = 0,
        public float $insuranceFee = 0,
        public bool $isMandatory = false,
        public ?float $coverageLimit = null,
        public string $providerType = 'self_insured',
        public ?string $providerName = null
    ) {}
    
    public function toArray(): array
    {
        return [
            'declared_value' => $this->declaredValue,
            'type' => $this->insuranceType,
            'rate_percent' => $this->insuranceRate,
            'fee' => $this->insuranceFee,
            'mandatory' => $this->isMandatory,
            'coverage_limit' => $this->coverageLimit,
            'provider' => [
                'type' => $this->providerType,
                'name' => $this->providerName,
            ]
        ];
    }
}
```

### 4.2 Order of Operations for Pricing

```php
<?php

/**
 * Complete pricing calculation with proper order of operations
 */
class LegPricingService
{
    public function calculateInterstateLegPrice(
        SupportedRoute $route,
        array $goodsItems,
        array $options = []
    ): LegPriceBreakdown {
        
        $company = $route->truckingCompany;
        $isExpress = $options['is_express'] ?? false;
        $isFragile = $options['is_fragile'] ?? false;
        
        // STEP 1: Calculate chargeable weight
        $totalChargeableWeight = collect($goodsItems)->sum('chargeable_weight_kg');
        
        // STEP 2: Calculate base trucking cost
        $baseFreight = $totalChargeableWeight * $route->price_per_kg;
        $baseFreight = max($baseFreight, $route->minimum_charge);
        
        // STEP 3: Calculate insurance fee (per item)
        $insuranceService = new InsuranceCalculationService();
        $insuranceResult = $insuranceService->calculateLegInsurance(
            company: $company,
            requestLegId: 0, // Will be set after leg creation
            route: $route
        );
        $totalInsurance = $insuranceResult['summary']['total_insurance_fee'];
        
        // STEP 4: Add base cost + insurance
        $subtotalBeforeCommission = $baseFreight + $totalInsurance;
        
        // Apply surcharges
        $expressSurcharge = $isExpress 
            ? $baseFreight * ($route->express_surcharge_percent / 100) 
            : 0;
        $fragileSurcharge = $isFragile 
            ? $baseFreight * ($route->fragile_surcharge_percent / 100) 
            : 0;
        
        $providerBasePrice = $subtotalBeforeCommission + $expressSurcharge + $fragileSurcharge;
        
        // STEP 5: Apply platform commission
        $commissionService = new CommissionCalculationService();
        $commission = $commissionService->calculateCommission(
            legType: 'interstate_trucking',
            baseAmount: $providerBasePrice,
            insuranceFee: $totalInsurance
        );
        
        // STEP 6: Final leg price to customer
        $totalLegPrice = $commission->totalPrice;
        
        return new LegPriceBreakdown(
            chargeableWeight: $totalChargeableWeight,
            baseFreight: round($baseFreight, 2),
            insuranceFee: round($totalInsurance, 2),
            expressSurcharge: round($expressSurcharge, 2),
            fragileSurcharge: round($fragileSurcharge, 2),
            providerBasePrice: round($providerBasePrice, 2),
            platformCommission: $commission->commissionAmount,
            totalLegPrice: round($totalLegPrice, 2),
            providerPayout: $commission->providerPayout,
            insuranceDetails: $insuranceResult
        );
    }
}

class LegPriceBreakdown
{
    public function __construct(
        public float $chargeableWeight,
        public float $baseFreight,
        public float $insuranceFee,
        public float $expressSurcharge,
        public float $fragileSurcharge,
        public float $providerBasePrice,
        public float $platformCommission,
        public float $totalLegPrice,
        public float $providerPayout,
        public array $insuranceDetails = []
    ) {}
    
    public function toDisplayArray(): array
    {
        return [
            'weight' => [
                'chargeable_kg' => $this->chargeableWeight,
            ],
            'cost_breakdown' => [
                'base_freight' => $this->baseFreight,
                'insurance_fee' => $this->insuranceFee,
                'express_surcharge' => $this->expressSurcharge,
                'fragile_surcharge' => $this->fragileSurcharge,
            ],
            'financial' => [
                'provider_base_price' => $this->providerBasePrice,
                'platform_commission' => $this->platformCommission,
                'total_leg_price' => $this->totalLegPrice,
            ],
            'provider_payout' => $this->providerPayout,
            'insurance' => $this->insuranceDetails
        ];
    }
}
```

---

## 5. SEQUENTIAL LEG PAYMENT FLOW

### 5.1 Payment State Machine

```
LEG PAYMENT LIFECYCLE:

┌─────────────────────────────────────────────────────────────────┐
│                        AWAITING_PAYMENT                          │
│  (Previous leg not complete OR leg not yet reached)             │
└───────────────────────┬─────────────────────────────────────────┘
                        │ Previous leg completed
                        ▼
┌─────────────────────────────────────────────────────────────────┐
│                     PAYMENT_PENDING                              │
│  (User shown payment screen with breakdown)                     │
│  • provider_base_price                                          │
│  • insurance_fee                                                │
│  • platform_commission                                          │
│  • total_leg_price                                              │
└───────────────────────┬─────────────────────────────────────────┘
                        │ User confirms payment
                        ▼
┌─────────────────────────────────────────────────────────────────┐
│                     PAYMENT_PROCESSING                           │
│  (Payment gateway processing)                                   │
└───────────────────────┬─────────────────────────────────────────┘
                        │ Payment successful
                        ▼
┌─────────────────────────────────────────────────────────────────┐
│                         PAID                                     │
│  (Payment confirmed, leg activated)                             │
└───────────────────────┬─────────────────────────────────────────┘
                        │ Leg completed
                        ▼
┌─────────────────────────────────────────────────────────────────┐
│                      COMPLETED                                   │
│  (Provider payout released)                                     │
└─────────────────────────────────────────────────────────────────┘
```

### 5.2 Sequential Payment Service

```php
<?php

/**
 * Sequential Leg Payment Service
 * Manages pay-per-leg flow
 */
class SequentialLegPaymentService
{
    public function __construct(
        private CommissionCalculationService $commissionService,
        private InsuranceCalculationService $insuranceService,
        private PaymentGatewayManager $paymentManager
    ) {}
    
    /**
     * Get payment requirements for current leg
     */
    public function getCurrentLegPaymentRequirements(Request $request): ?LegPaymentRequirements
    {
        $currentLegNumber = $request->current_leg_number;
        
        $leg = $request->legs()
            ->where('leg_number', $currentLegNumber)
            ->first();
        
        if (!$leg) {
            return null;
        }
        
        // Check if payment already made
        if ($leg->payment_status === 'paid') {
            return new LegPaymentRequirements(
                legNumber: $currentLegNumber,
                legType: $leg->leg_type,
                status: 'already_paid',
                amountDue: 0,
                breakdown: null
            );
        }
        
        // Calculate payment breakdown
        $breakdown = $this->calculateLegPaymentBreakdown($leg);
        
        return new LegPaymentRequirements(
            legNumber: $currentLegNumber,
            legType: $leg->leg_type,
            status: 'payment_required',
            amountDue: $breakdown->totalLegPrice,
            breakdown: $breakdown,
            paymentDeadline: now()->addHours(2)
        );
    }
    
    /**
     * Calculate complete payment breakdown for a leg
     */
    private function calculateLegPaymentBreakdown(RequestLeg $leg): PaymentBreakdown
    {
        // Get insurance fees for goods items
        $goodsItems = TruckingGoodsItem::where('request_leg_id', $leg->id)->get();
        $totalInsurance = $goodsItems->sum('insurance_fee');
        
        // Calculate commission
        $commission = $this->commissionService->calculateCommission(
            legType: $leg->leg_type,
            baseAmount: $leg->provider_base_price,
            insuranceFee: $totalInsurance
        );
        
        return new PaymentBreakdown(
            legNumber: $leg->leg_number,
            legType: $leg->leg_type,
            providerBasePrice: $leg->provider_base_price,
            insuranceFee: $totalInsurance,
            platformCommission: $commission->commissionAmount,
            totalLegPrice: $commission->totalPrice,
            displayBreakdown: [
                'provider_base_price' => [
                    'label' => $this->getBasePriceLabel($leg->leg_type),
                    'amount' => $leg->provider_base_price
                ],
                'insurance_fee' => [
                    'label' => 'Goods Insurance',
                    'amount' => $totalInsurance,
                    'items' => $goodsItems->map(fn($item) => [
                        'description' => $item->description,
                        'declared_value' => $item->declared_value,
                        'insurance_fee' => $item->insurance_fee
                    ])
                ],
                'platform_commission' => [
                    'label' => 'Platform Service Fee',
                    'amount' => $commission->commissionAmount,
                    'type' => $commission->commissionType,
                    'rate' => $commission->commissionRate
                ]
            ]
        );
    }
    
    /**
     * Process payment for current leg
     */
    public function processLegPayment(
        Request $request,
        string $paymentMethod,
        array $paymentData
    ): PaymentResult {
        
        $requirements = $this->getCurrentLegPaymentRequirements($request);
        
        if (!$requirements || $requirements->status !== 'payment_required') {
            throw new \InvalidArgumentException('No payment required for current leg');
        }
        
        $amount = $requirements->amountDue;
        $leg = $request->legs()
            ->where('leg_number', $request->current_leg_number)
            ->first();
        
        // Create payment intent
        $paymentResult = $this->paymentManager->charge([
            'amount' => $amount,
            'currency' => 'NGN',
            'payment_method' => $paymentMethod,
            'metadata' => [
                'request_id' => $request->id,
                'request_number' => $request->request_number,
                'leg_number' => $leg->leg_number,
                'leg_type' => $leg->leg_type,
                'payment_type' => 'sequential_leg_payment',
                'breakdown' => json_encode($requirements->breakdown->toArray())
            ]
        ], $paymentData);
        
        // Update leg payment status
        if ($paymentResult['status'] === 'success') {
            $this->confirmLegPayment($leg, $paymentResult);
        }
        
        return new PaymentResult(
            success: $paymentResult['status'] === 'success',
            legNumber: $leg->leg_number,
            amount: $amount,
            reference: $paymentResult['reference'] ?? null,
            message: $paymentResult['message'] ?? null
        );
    }
    
    /**
     * Confirm leg payment and activate leg
     */
    private function confirmLegPayment(RequestLeg $leg, array $paymentResult): void
    {
        DB::transaction(function () use ($leg, $paymentResult) {
            // Update leg
            $leg->update([
                'payment_status' => 'paid',
                'paid_amount' => $paymentResult['amount'],
                'paid_at' => now(),
                'payment_reference' => $paymentResult['reference'],
                'status' => 'payment_received' // Will trigger leg activation
            ]);
            
            // Update leg payment record
            LegPayment::where('request_leg_id', $leg->id)->update([
                'payment_status' => 'paid',
                'paid_amount' => $paymentResult['amount'],
                'paid_at' => now(),
                'transaction_id' => $paymentResult['transaction_id'] ?? null,
                'leg_state' => 'paid',
                'triggers_next_leg' => true
            ]);
            
            // Update request totals
            $request = $leg->request;
            $request->update([
                'legs_paid_count' => $request->legs_paid_count + 1,
                'total_paid_amount' => $request->total_paid_amount + $paymentResult['amount'],
                'remaining_balance' => $request->request_eta_amount - ($request->total_paid_amount + $paymentResult['amount']),
                'current_leg_payment_status' => 'paid'
            ]);
            
            // Trigger leg activation
            event(new LegPaymentConfirmed($leg, $paymentResult));
            
            Log::info("Leg {$leg->leg_number} payment confirmed for request {$request->request_number}");
        });
    }
    
    /**
     * Handle leg completion and trigger next leg
     */
    public function onLegCompleted(RequestLeg $completedLeg): void
    {
        $request = $completedLeg->request;
        
        DB::transaction(function () use ($completedLeg, $request) {
            // Mark leg as completed
            $completedLeg->update([
                'status' => 'completed',
                'completed_at' => now()
            ]);
            
            // Release provider payout
            $this->releaseProviderPayout($completedLeg);
            
            // Update request progress
            $request->update([
                'legs_completed_count' => $request->legs_completed_count + 1
            ]);
            
            // Check for next leg
            $nextLeg = $request->legs()
                ->where('leg_number', $completedLeg->leg_number + 1)
                ->first();
            
            if ($nextLeg) {
                // Update current leg number
                $request->update([
                    'current_leg_number' => $nextLeg->leg_number,
                    'current_leg_payment_status' => 'awaiting_payment'
                ]);
                
                // Notify user about next leg payment
                event(new NextLegPaymentRequired($request, $nextLeg));
            } else {
                // All legs complete
                $this->completeRequest($request);
            }
        });
    }
    
    /**
     * Release payout to provider
     */
    private function releaseProviderPayout(RequestLeg $leg): void
    {
        // Calculate payout amount (provider base price - any adjustments)
        $payoutAmount = $leg->provider_payout_amount ?? $leg->provider_base_price;
        
        // Update leg
        $leg->update([
            'provider_payout_status' => 'processing',
        ]);
        
        // Queue payout job
        ProcessProviderPayout::dispatch($leg, $payoutAmount);
        
        Log::info("Provider payout queued for leg {$leg->leg_number}: {$payoutAmount}");
    }
    
    private function getBasePriceLabel(string $legType): string
    {
        return match($legType) {
            'local_pickup' => 'Pickup Service',
            'interstate_trucking' => 'Interstate Transport',
            'local_dropoff' => 'Delivery Service',
            default => 'Service Fee'
        };
    }
}

// DTOs
class LegPaymentRequirements
{
    public function __construct(
        public int $legNumber,
        public string $legType,
        public string $status, // 'payment_required', 'already_paid', 'not_yet'
        public float $amountDue,
        public ?PaymentBreakdown $breakdown,
        public ?Carbon $paymentDeadline = null
    ) {}
}

class PaymentBreakdown
{
    public function __construct(
        public int $legNumber,
        public string $legType,
        public float $providerBasePrice,
        public float $insuranceFee,
        public float $platformCommission,
        public float $totalLegPrice,
        public array $displayBreakdown = []
    ) {}
    
    public function toArray(): array
    {
        return [
            'leg_number' => $this->legNumber,
            'leg_type' => $this->legType,
            'provider_base_price' => $this->providerBasePrice,
            'insurance_fee' => $this->insuranceFee,
            'platform_commission' => $this->platformCommission,
            'total_leg_price' => $this->totalLegPrice,
            'display' => $this->displayBreakdown
        ];
    }
}
```

---

## 6. REQUEST FLOW & UI STRUCTURE

### 6.1 Step-by-Step UI Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        STEP 1: DELIVERY TYPE SELECTION                       │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│   ┌─────────────────┐      ┌─────────────────┐                              │
│   │                 │      │                 │                              │
│   │    METRO        │      │   INTERSTATE    │                              │
│   │   (Same City)   │      │ (Different State)│                             │
│   │                 │      │                 │                              │
│   │  [Local Icon]   │      │  [Truck Icon]   │                              │
│   │                 │      │                 │                              │
│   └────────┬────────┘      └────────┬────────┘                              │
│            │                        │                                       │
│            ▼                        ▼                                       │
│   ┌─────────────────┐      ┌──────────────────────────────────────────┐    │
│   │  STANDARD TAGXI │      │          STEP 2: LOCATION INPUT          │    │
│   │  BIDDING FLOW   │      ├──────────────────────────────────────────┤    │
│   │                 │      │                                          │    │
│   │  (Existing Flow)│      │  PICKUP:                                 │    │
│   │                 │      │  ┌──────────────────────────────────────┐│    │
│   └─────────────────┘      │  │ Address: _________________________   ││    │
│                            │  │ State:   [Dropdown ▼]                ││    │
│                            │  └──────────────────────────────────────┘│    │
│                            │                                          │    │
│                            │  DESTINATION:                            │    │
│                            │  ┌──────────────────────────────────────┐│    │
│                            │  │ Address: _________________________   ││    │
│                            │  │ State:   [Dropdown ▼]                ││    │
│                            │  └──────────────────────────────────────┘│    │
│                            │                                          │    │
│                            │  [VALIDATION: States must be different]  │    │
│                            │                                          │    │
│                            │           [Continue →]                   │    │
│                            └──────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│                     STEP 3: PACKAGE DETAILS                                  │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  PACKAGE ITEMS (Can add multiple):                                          │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │ Item 1                                                                  ││
│  │  Description: [________________________]  Category: [Dropdown ▼]        ││
│  │  Weight (kg): [______]  Quantity: [__]                                  ││
│  │  Dimensions: L:[___]cm × W:[___]cm × H:[___]cm                          ││
│  │  Declared Value (₦): [__________]  [✓] Requires Insurance              ││
│  │  [✓] Fragile Item                                                       ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                                                              │
│                              [+ Add Another Item]                           │
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │  CALCULATED VALUES:                                                     ││
│  │  • Total Actual Weight: 25.5 kg                                         ││
│  │  • Total Volumetric Weight: 32.4 kg                                     ││
│  │  • Chargeable Weight: 32.4 kg (higher of actual/volumetric)             ││
│  │  • Total Declared Value: ₦500,000                                       ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                                                              │
│                                    [Continue →]                             │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│                   STEP 4: LEG SELECTION (CHAIN BUILDER)                      │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │ LEG A: PICKUP (Local Dispatch)                                          ││
│  │ ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━  ││
│  │ From: [Pickup Address] → To: [Origin Hub]                               ││
│  │                                                                         ││
│  │ Available Riders: 5 nearby                                              ││
│  │ [✓] Enable bidding (recommended)                                        ││
│  │ Estimated Price: ₦2,500 - ₦4,000                                        ││
│  │                                                                         ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │ LEG B: INTERSTATE TRUCKING                                              ││
│  │ ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━  ││
│  │ From: [Origin Hub] → To: [Destination Hub]                              ││
│  │ Chargeable Weight: 32.4 kg                                              ││
│  │                                                                         ││
│  │ ELIGIBLE TRUCKING COMPANIES:                                            ││
│  │ ┌─────────────────────────────────────────────────────────────────────┐││
│  │ │ ○ ABC Logistics                              ₦45,000                │││
│  │ │   Route: Lagos Hub → Abuja Hub (12hrs)                              │││
│  │ │   Insurance: ₦5,000 (1% of value)    ★★★★☆ 4.5                    │││
│  │ │   [View Details]                                                    │││
│  │ ├─────────────────────────────────────────────────────────────────────┤││
│  │ │ ● XYZ Transport ← SELECTED                   ₦42,000                │││
│  │ │   Route: Lagos Hub → Abuja Hub (14hrs)                              │││
│  │ │   Insurance: ₦4,200 (0.8% of value)  ★★★★★ 4.8                    │││
│  │ │   [Selected ✓]                                                      │││
│  │ └─────────────────────────────────────────────────────────────────────┘││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │ LEG C: FINAL DELIVERY                                                   ││
│  │ ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━  ││
│  │ From: [Destination Hub]                                                 ││
│  │                                                                         ││
│  │  ○ Hub Pickup (I'll collect from hub)                    FREE         ││
│  │                                                                         ││
│  │  ● Dispatch Delivery (Deliver to my address)             ~₦3,000      ││
│  │    [Enable bidding]                                                     ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │                         PRICE BREAKDOWN                                 ││
│  │ ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━  ││
│  │ LEG A - Pickup:                                                          ││
│  │   Provider Price:        ₦3,000                                         ││
│  │   Platform Commission:   ₦450 (15%)                                     ││
│  │   ─────────────────────────────────                                     ││
│  │   Subtotal:              ₦3,450                                         ││
│  │                                                                          ││
│  │ LEG B - Interstate:                                                      ││
│  │   Provider Price:        ₦35,000                                        ││
│  │   Insurance:             ₦4,200                                         ││
│  │   Platform Commission:   ₦3,920 (10% of ₦39,200)                        ││
│  │   ─────────────────────────────────                                     ││
│  │   Subtotal:              ₦43,120                                        ││
│  │                                                                          ││
│  │ LEG C - Delivery:                                                        ││
│  │   Provider Price:        ₦3,000                                         ││
│  │   Platform Commission:   ₦450 (15%)                                     ││
│  │   ─────────────────────────────────                                     ││
│  │   Subtotal:              ₦3,450                                         ││
│  │                                                                          ││
│  │ GRAND TOTAL:                    ₦50,020                                 ││
│  │ [Pay per leg - you'll pay ₦3,450 to start]                              ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                                                              │
│                              [Confirm & Book →]                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 6.2 Chain Builder API Response Structure

```json
{
  "step": "leg_selection",
  "request_id": "temp-12345",
  "route_summary": {
    "origin_city": "Lagos",
    "destination_city": "Abuja",
    "distance_km": 780,
    "estimated_duration_hours": 12
  },
  "chain": {
    "leg_a": {
      "leg_type": "local_pickup",
      "from": {
        "address": "123 Allen Avenue, Ikeja",
        "lat": 6.5244,
        "lng": 3.3792
      },
      "to": {
        "hub_id": 5,
        "hub_name": "Lagos Central Hub",
        "address": "Lagos Central Hub, Oshodi"
      },
      "options": {
        "enable_bidding": true,
        "estimated_price_range": {
          "min": 2500,
          "max": 4000
        }
      }
    },
    "leg_b": {
      "leg_type": "interstate_trucking",
      "from": {
        "hub_id": 5,
        "hub_name": "Lagos Central Hub"
      },
      "to": {
        "hub_id": 12,
        "hub_name": "Abuja Central Hub"
      },
      "chargeable_weight_kg": 32.4,
      "total_declared_value": 500000,
      "trucking_companies": [
        {
          "id": 3,
          "name": "XYZ Transport",
          "rating": 4.8,
          "logo_url": "https://...",
          "route": {
            "origin_hub": "Lagos Central Hub",
            "destination_hub": "Abuja Central Hub",
            "distance_km": 780,
            "estimated_hours": 14
          },
          "pricing": {
            "provider_base_price": 35000,
            "insurance_fee": 4200,
            "platform_commission": 3920,
            "total_leg_price": 43120,
            "breakdown": {
              "base_freight": 32400,
              "weight_surcharge": 0,
              "insurance_rate": 0.8,
              "commission_rate": 10
            }
          },
          "insurance": {
            "type": "percentage_of_value",
            "rate_percent": 0.8,
            "mandatory": false,
            "provider": "self_insured"
          }
        }
      ]
    },
    "leg_c": {
      "leg_type": "local_dropoff",
      "from": {
        "hub_id": 12,
        "hub_name": "Abuja Central Hub"
      },
      "to": {
        "address": "456 CBD, Abuja",
        "lat": 9.0765,
        "lng": 7.3986
      },
      "options": [
        {
          "type": "hub_pickup",
          "name": "Hub Pickup",
          "description": "Collect from Abuja Central Hub",
          "price": 0
        },
        {
          "type": "dispatch_delivery",
          "name": "Dispatch Delivery",
          "description": "Deliver to your address",
          "estimated_price": 3000,
          "enable_bidding": true
        }
      ]
    }
  },
  "grand_total": {
    "provider_base_total": 41000,
    "insurance_total": 4200,
    "platform_commission_total": 4820,
    "final_total": 50020
  }
}
```

---

## 7. API ENDPOINTS

### 7.1 Delivery Type Selection

```php
<?php

/**
 * POST /api/v1/interstate/delivery-type
 * Step 1: Select delivery type
 */
class DeliveryTypeController
{
    /**
     * Select delivery type and initialize request
     */
    public function selectDeliveryType(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'delivery_type' => 'required|in:metro,interstate',
            'pickup_address' => 'required_if:delivery_type,interstate|string',
            'pickup_state' => 'required_if:delivery_type,interstate|string',
            'destination_address' => 'required_if:delivery_type,interstate|string',
            'destination_state' => 'required_if:delivery_type,interstate|string',
        ]);
        
        // Validate states are different for interstate
        if ($validated['delivery_type'] === 'interstate') {
            if ($validated['pickup_state'] === $validated['destination_state']) {
                return response()->json([
                    'error' => 'pickup_state and destination_state must be different for interstate delivery'
                ], 422);
            }
        }
        
        // Create temporary request session
        $sessionId = $this->createRequestSession($validated);
        
        return response()->json([
            'session_id' => $sessionId,
            'delivery_type' => $validated['delivery_type'],
            'next_step' => $validated['delivery_type'] === 'metro' 
                ? 'standard_bidding_flow' 
                : 'package_details',
            'route_preview' => $validated['delivery_type'] === 'interstate' 
                ? $this->getRoutePreview($validated)
                : null
        ]);
    }
}
```

### 7.2 Package Validation

```php
<?php

/**
 * POST /api/v1/interstate/validate-packages
 * Step 3: Validate package details and calculate weights
 */
class PackageValidationController
{
    /**
     * Validate packages and calculate dimensional weights
     */
    public function validatePackages(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|string',
            'packages' => 'required|array|min:1',
            'packages.*.description' => 'nullable|string|max:255',
            'packages.*.category' => 'required|in:electronics,fashion,food,documents,fragile,general',
            'packages.*.weight_kg' => 'required|numeric|min:0.1|max:1000',
            'packages.*.length_cm' => 'required|numeric|min:1|max:500',
            'packages.*.width_cm' => 'required|numeric|min:1|max:500',
            'packages.*.height_cm' => 'required|numeric|min:1|max:500',
            'packages.*.quantity' => 'integer|min:1|max:100',
            'packages.*.declared_value' => 'required|numeric|min:0',
            'packages.*.requires_insurance' => 'boolean',
            'packages.*.is_fragile' => 'boolean',
        ]);
        
        $session = $this->getRequestSession($validated['session_id']);
        
        // Calculate for each package
        $validatedPackages = [];
        $totals = [
            'actual_weight_kg' => 0,
            'volumetric_weight_kg' => 0,
            'chargeable_weight_kg' => 0,
            'declared_value' => 0,
            'package_count' => 0
        ];
        
        $volumetricDivisor = 5000; // Standard
        
        foreach ($validated['packages'] as $index => $package) {
            $volumetricWeight = ($package['length_cm'] * $package['width_cm'] * $package['height_cm']) / $volumetricDivisor;
            $chargeableWeight = max($package['weight_kg'], $volumetricWeight);
            
            $validatedPackages[] = [
                'index' => $index + 1,
                'description' => $package['description'],
                'category' => $package['category'],
                'dimensions' => [
                    'length_cm' => $package['length_cm'],
                    'width_cm' => $package['width_cm'],
                    'height_cm' => $package['height_cm']
                ],
                'actual_weight_kg' => $package['weight_kg'],
                'volumetric_weight_kg' => round($volumetricWeight, 2),
                'chargeable_weight_kg' => round($chargeableWeight, 2),
                'quantity' => $package['quantity'] ?? 1,
                'declared_value' => $package['declared_value'],
                'requires_insurance' => $package['requires_insurance'] ?? false,
                'is_fragile' => $package['is_fragile'] ?? false
            ];
            
            $totals['actual_weight_kg'] += $package['weight_kg'] * ($package['quantity'] ?? 1);
            $totals['volumetric_weight_kg'] += $volumetricWeight * ($package['quantity'] ?? 1);
            $totals['chargeable_weight_kg'] += $chargeableWeight * ($package['quantity'] ?? 1);
            $totals['declared_value'] += $package['declared_value'] * ($package['quantity'] ?? 1);
            $totals['package_count'] += ($package['quantity'] ?? 1);
        }
        
        // Save to session
        $this->updateRequestSession($validated['session_id'], [
            'packages' => $validatedPackages,
            'totals' => $totals
        ]);
        
        return response()->json([
            'session_id' => $validated['session_id'],
            'packages' => $validatedPackages,
            'totals' => $totals,
            'next_step' => 'leg_selection'
        ]);
    }
}
```

### 7.3 Trucking Company Quote Generation

```php
<?php

/**
 * POST /api/v1/interstate/quotes
 * Step 4: Get trucking company quotes
 */
class QuoteGenerationController
{
    /**
     * Generate quotes from eligible trucking companies
     */
    public function generateQuotes(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|string',
            'service_type' => 'in:standard,express'
        ]);
        
        $session = $this->getRequestSession($validated['session_id']);
        
        // Find available routes
        $routes = SupportedRoute::with(['truckingCompany', 'originHub', 'destinationHub'])
            ->whereHas('originHub', function ($q) use ($session) {
                $q->where('state', 'LIKE', "%{$session['pickup_state']}%");
            })
            ->whereHas('destinationHub', function ($q) use ($session) {
                $q->where('state', 'LIKE', "%{$session['destination_state']}%");
            })
            ->where('is_active', true)
            ->get();
        
        $quotes = [];
        $chargeableWeight = $session['totals']['chargeable_weight_kg'];
        $declaredValue = $session['totals']['declared_value'];
        $isExpress = ($validated['service_type'] ?? 'standard') === 'express';
        
        foreach ($routes as $route) {
            $company = $route->truckingCompany;
            
            // Calculate pricing
            $pricingService = new LegPricingService();
            $breakdown = $pricingService->calculateInterstateLegPrice(
                route: $route,
                goodsItems: $session['packages'],
                options: ['is_express' => $isExpress]
            );
            
            $quotes[] = [
                'quote_id' => "Q-{$route->id}-" . uniqid(),
                'company' => [
                    'id' => $company->id,
                    'name' => $company->company_name,
                    'logo' => $company->logo,
                    'rating' => $company->rating,
                    'total_trips' => $company->total_trips ?? 0
                ],
                'route' => [
                    'route_id' => $route->id,
                    'origin_hub' => [
                        'id' => $route->originHub->id,
                        'name' => $route->originHub->hub_name,
                        'city' => $route->originHub->city,
                        'state' => $route->originHub->state
                    ],
                    'destination_hub' => [
                        'id' => $route->destinationHub->id,
                        'name' => $route->destinationHub->hub_name,
                        'city' => $route->destinationHub->city,
                        'state' => $route->destinationHub->state
                    ],
                    'distance_km' => $route->distance_km,
                    'estimated_duration_hours' => $isExpress 
                        ? $route->express_sla_hours 
                        : $route->standard_sla_hours
                ],
                'pricing' => $breakdown->toDisplayArray(),
                'insurance' => [
                    'type' => $company->insurance_type,
                    'mandatory' => $company->insurance_mandatory,
                    'rate_percent' => $company->insurance_rate_percent,
                    'coverage_limit' => $company->max_claim_amount
                ]
            ];
        }
        
        // Sort by total price
        usort($quotes, fn($a, $b) => $a['pricing']['financial']['total_leg_price'] <=> $b['pricing']['financial']['total_leg_price']);
        
        return response()->json([
            'session_id' => $validated['session_id'],
            'service_type' => $validated['service_type'] ?? 'standard',
            'package_summary' => $session['totals'],
            'quotes' => $quotes,
            'recommended_quote_id' => $quotes[0]['quote_id'] ?? null,
            'next_step' => 'confirm_booking'
        ]);
    }
}
```

### 7.4 Commission Application API

```php
<?php

/**
 * GET /api/v1/interstate/commission-config
 * Get current commission configuration
 */
class CommissionConfigController
{
    /**
     * Get active commission configurations
     */
    public function getCommissionConfig(): JsonResponse
    {
        $service = new CommissionCalculationService();
        $configs = $service->getCommissionConfigs();
        
        return response()->json([
            'commissions' => $configs,
            'effective_date' => $configs->first()?->effective_from ?? now()
        ]);
    }
}

/**
 * Admin: PUT /api/v1/admin/commission-config/{leg_type}
 * Update commission configuration
 */
class AdminCommissionController
{
    /**
     * Update commission config (Admin only)
     */
    public function updateConfig(Request $request, string $legType): JsonResponse
    {
        $validated = $request->validate([
            'commission_type' => 'required|in:percentage,fixed_amount',
            'commission_value' => 'required|numeric|min:0',
            'min_commission_amount' => 'nullable|numeric|min:0',
            'max_commission_amount' => 'nullable|numeric|min:0',
            'apply_to' => 'required|in:base_price,total_with_insurance'
        ]);
        
        $service = new CommissionCalculationService();
        $config = $service->updateCommissionConfig(
            legType: $legType,
            commissionType: $validated['commission_type'],
            commissionValue: $validated['commission_value'],
            minAmount: $validated['min_commission_amount'] ?? null,
            maxAmount: $validated['max_commission_amount'] ?? null,
            adminId: auth()->id()
        );
        
        return response()->json([
            'message' => 'Commission configuration updated successfully',
            'config' => [
                'leg_type' => $config->leg_type,
                'commission_type' => $config->commission_type,
                'commission_value' => $config->commission_value,
                'effective_from' => $config->effective_from
            ]
        ]);
    }
}
```

---

## 8. FINANCIAL SETTLEMENT LOGIC PER LEG

### 8.1 Settlement Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    FINANCIAL SETTLEMENT PER LEG                              │
└─────────────────────────────────────────────────────────────────────────────┘

LEG PAYMENT RECEIVED
        │
        ▼
┌───────────────────┐
│  1. HOLD PAYMENT  │ ──→ Payment held in escrow account
│     IN ESCROW     │
└─────────┬─────────┘
          │
          ▼
┌───────────────────┐
│  2. ACTIVATE LEG  │ ──→ Provider notified, leg begins
│   PROVIDER BEGINS │
└─────────┬─────────┘
          │
          ▼
┌───────────────────┐
│ 3. LEG COMPLETION │ ──→ Delivery proof submitted
│    PROOF RECEIVED │
└─────────┬─────────┘
          │
          ▼
┌───────────────────┐     ┌──────────────────────────────────────────────┐
│  4. SPLIT FUNDS   │────→│ PLATFORM COMMISSION                          │
│                   │     │ • Commission amount → Platform revenue        │
│                   │     │ • Transferred immediately                     │
│                   │     └──────────────────────────────────────────────┘
│                   │
│                   │────→│ INSURANCE FUND (if applicable)               │
│                   │     │ • Insurance fee → Insurance reserve           │
│                   │     │ • Held until delivery confirmed               │
│                   │     └──────────────────────────────────────────────┘
│                   │
│                   │────→│ PROVIDER PAYOUT                              │
│                   │     │ • Base price → Provider wallet/bank           │
│                   │     │ • Released after leg completion               │
│                   │     └──────────────────────────────────────────────┘
└───────────────────┘

SETTLEMENT TIMING:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

LOCAL PICKUP LEG:
  • Platform commission: Immediate on payment
  • Insurance: N/A (no insurance on local legs)
  • Rider payout: Released 2 hours after pickup completion

INTERSTATE TRUCKING LEG:
  • Platform commission: Immediate on payment
  • Insurance fee: Held in reserve until final delivery
  • Trucking company payout: Released 24hrs after hub arrival (T+1)

LOCAL DELIVERY LEG:
  • Platform commission: Immediate on payment
  • Insurance: N/A
  • Rider payout: Released 2 hours after delivery completion
```

### 8.2 Settlement Service

```php
<?php

/**
 * Leg Financial Settlement Service
 * Handles fund splitting and provider payouts per leg
 */
class LegSettlementService
{
    public function __construct(
        private PaymentGatewayManager $paymentManager,
        private WalletService $walletService,
        private EscrowService $escrowService
    ) {}
    
    /**
     * Process settlement when leg payment is received
     */
    public function processLegPaymentSettlement(RequestLeg $leg): void
    {
        $breakdown = $this->getLegFinancialBreakdown($leg);
        
        DB::transaction(function () use ($leg, $breakdown) {
            // 1. Hold funds in escrow
            $this->escrowService->holdFunds(
                reference: $leg->payment_reference,
                amount: $breakdown->totalLegPrice,
                metadata: [
                    'leg_id' => $leg->id,
                    'leg_type' => $leg->leg_type,
                    'breakdown' => $breakdown->toArray()
                ]
            );
            
            // 2. Deduct platform commission immediately
            if ($breakdown->platformCommission > 0) {
                $this->escrowService->allocateToPlatform(
                    reference: $leg->payment_reference,
                    amount: $breakdown->platformCommission,
                    description: "Platform commission for leg {$leg->leg_number}"
                );
                
                // Credit platform revenue account
                $this->creditPlatformRevenue($breakdown->platformCommission, $leg);
            }
            
            // 3. Handle insurance fee
            if ($breakdown->insuranceFee > 0) {
                $this->escrowService->allocateToInsuranceReserve(
                    reference: $leg->payment_reference,
                    amount: $breakdown->insuranceFee,
                    description: "Insurance reserve for leg {$leg->leg_number}"
                );
            }
            
            // 4. Remainder stays in escrow for provider payout
            $remainingInEscrow = $breakdown->providerBasePrice;
            
            Log::info("Leg payment settled to escrow", [
                'leg_id' => $leg->id,
                'total' => $breakdown->totalLegPrice,
                'platform_commission' => $breakdown->platformCommission,
                'insurance_reserve' => $breakdown->insuranceFee,
                'provider_pending' => $remainingInEscrow
            ]);
        });
    }
    
    /**
     * Release provider payout when leg is completed
     */
    public function releaseProviderPayout(RequestLeg $leg): void
    {
        $breakdown = $this->getLegFinancialBreakdown($leg);
        $payoutAmount = $breakdown->providerPayout;
        
        DB::transaction(function () use ($leg, $payoutAmount, $breakdown) {
            // 1. Release from escrow
            $this->escrowService->releaseToProvider(
                reference: $leg->payment_reference,
                amount: $payoutAmount
            );
            
            // 2. Process payout based on provider type
            match($leg->leg_type) {
                'local_pickup', 'local_dropoff' => $this->payoutToRider($leg, $payoutAmount),
                'interstate_trucking' => $this->payoutToTruckingCompany($leg, $payoutAmount),
                default => null
            };
            
            // 3. Update leg record
            $leg->update([
                'provider_payout_status' => 'paid',
                'provider_payout_at' => now(),
                'provider_payout_reference' => 'PAY-' . uniqid()
            ]);
            
            // 4. Release insurance reserve if final leg
            if ($breakdown->insuranceFee > 0) {
                $this->releaseInsuranceReserve($leg, $breakdown->insuranceFee);
            }
            
            Log::info("Provider payout released", [
                'leg_id' => $leg->id,
                'provider_id' => $leg->provider_id,
                'amount' => $payoutAmount
            ]);
        });
    }
    
    /**
     * Payout to dispatch rider (local legs)
     */
    private function payoutToRider(RequestLeg $leg, float $amount): void
    {
        $rider = $leg->provider; // Driver model
        
        // Option 1: Credit to rider wallet
        $this->walletService->credit(
            userId: $rider->user_id,
            amount: $amount,
            type: 'leg_payout',
            reference: $leg->payment_reference,
            description: "Payout for leg {$leg->leg_number} - {$leg->leg_type}"
        );
        
        // Option 2: Queue for bank transfer (if configured)
        if ($rider->preferred_payout_method === 'bank_transfer') {
            ProcessBankPayout::dispatch($rider, $amount, $leg);
        }
    }
    
    /**
     * Payout to trucking company (interstate legs)
     */
    private function payoutToTruckingCompany(RequestLeg $leg, float $amount): void
    {
        $company = $leg->provider; // TruckingCompany model
        
        // Credit to company wallet
        $this->walletService->credit(
            userId: $company->user_id,
            amount: $amount,
            type: 'interstate_leg_payout',
            reference: $leg->payment_reference,
            description: "Payout for interstate transport - Request {$leg->request->request_number}"
        );
        
        // Notify company
        event(new ProviderPayoutReleased($company, $amount, $leg));
    }
    
    /**
     * Release insurance reserve to trucking company
     */
    private function releaseInsuranceReserve(RequestLeg $leg, float $amount): void
    {
        $company = $leg->request->truckingCompany;
        
        // Credit insurance fee to company (they bear the risk)
        $this->walletService->credit(
            userId: $company->user_id,
            amount: $amount,
            type: 'insurance_fee',
            reference: $leg->payment_reference,
            description: "Insurance coverage fee"
        );
        
        Log::info("Insurance reserve released", [
            'leg_id' => $leg->id,
            'company_id' => $company->id,
            'amount' => $amount
        ]);
    }
    
    /**
     * Get financial breakdown for a leg
     */
    private function getLegFinancialBreakdown(RequestLeg $leg): FinancialBreakdown
    {
        $insurance = TruckingGoodsItem::where('request_leg_id', $leg->id)->sum('insurance_fee');
        
        return new FinancialBreakdown(
            providerBasePrice: $leg->provider_base_price,
            insuranceFee: $insurance,
            platformCommission: $leg->platform_commission,
            totalLegPrice: $leg->total_leg_price,
            providerPayout: $leg->provider_payout_amount ?? $leg->provider_base_price
        );
    }
    
    /**
     * Handle refund for cancelled leg
     */
    public function processLegRefund(RequestLeg $leg, string $reason): void
    {
        $amount = $leg->paid_amount;
        
        DB::transaction(function () use ($leg, $amount, $reason) {
            // Process refund through payment gateway
            $refundResult = $this->paymentManager->refund([
                'amount' => $amount,
                'original_transaction_id' => $leg->payment_reference,
                'reason' => $reason
            ]);
            
            // Update leg record
            $leg->update([
                'payment_status' => 'refunded',
                'refund_amount' => $amount,
                'refund_reason' => $reason,
                'refunded_at' => now()
            ]);
            
            Log::info("Leg refund processed", [
                'leg_id' => $leg->id,
                'amount' => $amount,
                'reason' => $reason
            ]);
        });
    }
}

class FinancialBreakdown
{
    public function __construct(
        public float $providerBasePrice,
        public float $insuranceFee,
        public float $platformCommission,
        public float $totalLegPrice,
        public float $providerPayout
    ) {}
    
    public function toArray(): array
    {
        return [
            'provider_base_price' => $this->providerBasePrice,
            'insurance_fee' => $this->insuranceFee,
            'platform_commission' => $this->platformCommission,
            'total_leg_price' => $this->totalLegPrice,
            'provider_payout' => $this->providerPayout
        ];
    }
}
```

---

## 9. EDGE CASES & HANDLING

### 9.1 User Changes Trucking Company

```php
<?php

/**
 * Handle trucking company change after initial selection
 */
class TruckingCompanyChangeHandler
{
    /**
     * Process trucking company change
     */
    public function handleChange(
        Request $request,
        int $newRouteId,
        string $reason = 'user_request'
    ): ChangeResult {
        
        return DB::transaction(function () use ($request, $newRouteId, $reason) {
            // 1. Validate change is allowed
            $currentLeg = $request->currentLeg;
            
            if ($currentLeg->leg_type !== 'interstate_transport') {
                throw new \InvalidArgumentException('Can only change before interstate leg begins');
            }
            
            if ($currentLeg->status !== 'pending' && $currentLeg->status !== 'awaiting_payment') {
                throw new \InvalidArgumentException('Cannot change after leg has started');
            }
            
            // 2. Get old and new route details
            $oldRoute = $request->supportedRoute;
            $newRoute = SupportedRoute::with('truckingCompany')->findOrFail($newRouteId);
            
            // 3. Calculate price difference
            $oldPrice = $this->calculateLegPrice($request, $oldRoute);
            $newPrice = $this->calculateLegPrice($request, $newRoute);
            $priceDifference = $newPrice->totalLegPrice - $oldPrice->totalLegPrice;
            
            // 4. Handle payment adjustment
            if ($priceDifference > 0) {
                // User needs to pay more
                $this->createAdditionalPaymentRequest($request, $priceDifference, $newRoute);
            } elseif ($priceDifference < 0) {
                // User gets refund
                $this->createRefund($request, abs($priceDifference), $reason);
            }
            
            // 5. Update request and leg
            $interstateLeg = $request->legs()
                ->where('leg_type', 'interstate_transport')
                ->first();
            
            $interstateLeg->update([
                'supported_route_id' => $newRoute->id,
                'provider_id' => $newRoute->trucking_company_id,
                'provider_name' => $newRoute->truckingCompany->company_name,
                'provider_base_price' => $newPrice->providerBasePrice,
                'insurance_fee' => $newPrice->insuranceFee,
                'platform_commission' => $newPrice->platformCommission,
                'total_leg_price' => $newPrice->totalLegPrice,
                'provider_payout_amount' => $newPrice->providerPayout,
                'pricing_breakdown' => array_merge(
                    $interstateLeg->pricing_breakdown ?? [],
                    ['company_change' => [
                        'old_route_id' => $oldRoute->id,
                        'new_route_id' => $newRoute->id,
                        'price_difference' => $priceDifference,
                        'changed_at' => now()->toIso8601String(),
                        'reason' => $reason
                    ]]
                )
            ]);
            
            $request->update([
                'trucking_company_id' => $newRoute->trucking_company_id,
                'origin_hub_id' => $newRoute->origin_hub_id,
                'destination_hub_id' => $newRoute->destination_hub_id,
                'supported_route_id' => $newRoute->id,
                'interstate_transport_fee' => $newPrice->totalLegPrice
            ]);
            
            // 6. Update downstream legs (hub locations changed)
            $this->updateDownstreamLegs($request, $newRoute);
            
            // 7. Notify parties
            event(new TruckingCompanyChanged($request, $oldRoute, $newRoute, $priceDifference));
            
            return new ChangeResult(
                success: true,
                oldRoute: $oldRoute,
                newRoute: $newRoute,
                priceDifference: $priceDifference,
                paymentAction: $priceDifference > 0 ? 'additional_payment' : ($priceDifference < 0 ? 'refund' : 'none')
            );
        });
    }
    
    private function updateDownstreamLegs(Request $request, SupportedRoute $newRoute): void
    {
        // Update hub pickup leg
        $request->legs()
            ->where('leg_type', 'hub_pickup')
            ->update([
                'pickup_location' => [
                    'hub_id' => $newRoute->destination_hub_id,
                    'hub_name' => $newRoute->destinationHub->hub_name,
                    'address' => $newRoute->destinationHub->address,
                    'lat' => $newRoute->destinationHub->latitude,
                    'lng' => $newRoute->destinationHub->longitude
                ]
            ]);
        
        // Update local delivery leg
        $deliveryLeg = $request->legs()
            ->where('leg_type', 'local_delivery')
            ->first();
        
        if ($deliveryLeg) {
            $pickup = $deliveryLeg->pickup_location;
            $pickup['hub_id'] = $newRoute->destination_hub_id;
            $pickup['hub_name'] = $newRoute->destinationHub->hub_name;
            $pickup['address'] = $newRoute->destinationHub->address;
            $pickup['lat'] = $newRoute->destinationHub->latitude;
            $pickup['lng'] = $newRoute->destinationHub->longitude;
            
            $deliveryLeg->update(['pickup_location' => $pickup]);
        }
    }
}
```

### 9.2 Insurance Rejection

```php
<?php

/**
 * Handle insurance rejection scenarios
 */
class InsuranceRejectionHandler
{
    /**
     * Handle when trucking company rejects insurance coverage
     */
    public function handleRejection(
        Request $request,
        TruckingCompany $company,
        string $reason
    ): RejectionResult {
        
        $goodsItems = TruckingGoodsItem::where('request_id', $request->id)->get();
        
        // Option 1: Find alternative insurance provider
        $alternative = $this->findAlternativeInsurance($request, $company);
        
        if ($alternative) {
            return new RejectionResult(
                status: 'alternative_available',
                action: 'switch_insurance_provider',
                alternativeProvider: $alternative,
                message: 'Alternative insurance provider available'
            );
        }
        
        // Option 2: Require declared value reduction
        $maxInsurableValue = $company->max_claim_amount;
        $currentValue = $goodsItems->sum('declared_value');
        
        if ($currentValue > $maxInsurableValue) {
            return new RejectionResult(
                status: 'value_exceeds_limit',
                action: 'reduce_declared_value',
                maxAllowedValue: $maxInsurableValue,
                currentValue: $currentValue,
                message: "Maximum insurable value is ₦" . number_format($maxInsurableValue)
            );
        }
        
        // Option 3: Require high-risk item removal
        $highRiskItems = $goodsItems->filter(fn($item) => 
            $item->is_hazardous || $item->category === 'fragile'
        );
        
        if ($highRiskItems->isNotEmpty()) {
            return new RejectionResult(
                status: 'high_risk_items',
                action: 'remove_items_or_accept_liability',
                problematicItems: $highRiskItems->map(fn($i) => [
                    'id' => $i->id,
                    'description' => $i->description,
                    'reason' => $i->is_hazardous ? 'hazardous_material' : 'fragile'
                ]),
                message: 'High-risk items require special handling'
            );
        }
        
        // Option 4: Proceed without insurance
        return new RejectionResult(
            status: 'insurance_denied',
            action: 'proceed_without_insurance_or_cancel',
            message: 'Insurance not available for this shipment'
        );
    }
}
```

### 9.3 Overweight Cargo

```php
<?php

/**
 * Handle overweight cargo scenarios
 */
class OverweightCargoHandler
{
    /**
     * Handle weight verification showing overweight
     */
    public function handleOverweight(
        RequestLeg $leg,
        float $verifiedWeight,
        float $maxAllowedWeight
    ): OverweightResult {
        
        $overage = $verifiedWeight - $maxAllowedWeight;
        $overagePercent = ($overage / $maxAllowedWeight) * 100;
        
        // Tier 1: Minor overage (up to 10%)
        if ($overagePercent <= 10) {
            $additionalCharge = $this->calculateOverageCharge($leg, $overage);
            
            return new OverweightResult(
                status: 'minor_overage',
                action: 'accept_with_additional_charge',
                verifiedWeight: $verifiedWeight,
                maxAllowedWeight: $maxAllowedWeight,
                overage: $overage,
                additionalCharge: $additionalCharge,
                message: "Cargo is {$overagePercent}% overweight. Additional charge: ₦" . number_format($additionalCharge)
            );
        }
        
        // Tier 2: Moderate overage (10-25%)
        if ($overagePercent <= 25) {
            return new OverweightResult(
                status: 'moderate_overage',
                action: 'require_trucking_company_approval',
                verifiedWeight: $verifiedWeight,
                maxAllowedWeight: $maxAllowedWeight,
                overage: $overage,
                message: 'Overweight cargo requires trucking company approval'
            );
        }
        
        // Tier 3: Severe overage (>25%)
        return new OverweightResult(
            status: 'severe_overage',
            action: 'reject_or_split_shipment',
            verifiedWeight: $verifiedWeight,
            maxAllowedWeight: $maxAllowedWeight,
            overage: $overage,
            suggestedActions: [
                'split_into_multiple_shipments',
                'find_alternative_trucking_company',
                'cancel_and_reship'
            ],
            message: 'Cargo significantly exceeds weight limits'
        );
    }
    
    private function calculateOverageCharge(RequestLeg $leg, float $overage): float
    {
        $route = $leg->supportedRoute;
        // Charge 1.5x the per-kg rate for overage
        return $overage * $route->price_per_kg * 1.5;
    }
}
```

### 9.4 Commission Override by Admin

```php
<?php

/**
 * Admin commission override handler
 */
class CommissionOverrideHandler
{
    /**
     * Admin override of commission for a specific leg
     */
    public function overrideCommission(
        RequestLeg $leg,
        float $newCommissionAmount,
        string $reason,
        int $adminId
    ): OverrideResult {
        
        return DB::transaction(function () use ($leg, $newCommissionAmount, $reason, $adminId) {
            $oldCommission = $leg->platform_commission;
            $commissionDifference = $newCommissionAmount - $oldCommission;
            
            // Update leg
            $leg->update([
                'platform_commission' => $newCommissionAmount,
                'total_leg_price' => $leg->total_leg_price + $commissionDifference,
                'commission_override' => [
                    'original_amount' => $oldCommission,
                    'new_amount' => $newCommissionAmount,
                    'difference' => $commissionDifference,
                    'reason' => $reason,
                    'admin_id' => $adminId,
                    'overridden_at' => now()->toIso8601String()
                ]
            ]);
            
            // Create audit log
            CommissionOverrideLog::create([
                'request_leg_id' => $leg->id,
                'original_commission' => $oldCommission,
                'new_commission' => $newCommissionAmount,
                'reason' => $reason,
                'admin_id' => $adminId
            ]);
            
            // If commission reduced, refund difference to user
            if ($commissionDifference < 0) {
                $this->processCommissionRefund($leg, abs($commissionDifference));
            }
            
            return new OverrideResult(
                success: true,
                oldCommission: $oldCommission,
                newCommission: $newCommissionAmount,
                difference: $commissionDifference,
                userRefund: $commissionDifference < 0 ? abs($commissionDifference) : 0
            );
        });
    }
}
```

### 9.5 Edge Case Summary Table

| Edge Case | Detection | Primary Action | Fallback Action | Notification |
|-----------|-----------|----------------|-----------------|--------------|
| **Trucking Company Change** | User request before leg activation | Recalculate pricing, handle payment diff | N/A | User, Old/New Company |
| **Insurance Rejection** | Company declines coverage | Offer alternatives | Proceed without insurance | User, Company |
| **Overweight Cargo** | Weight verification > max | Charge overage fee | Split shipment | User, Company |
| **Commission Override** | Admin action | Adjust commission, refund if needed | N/A | User (if refund) |
| **Payment Failure** | Gateway decline | Retry with alternative method | Hold leg, notify user | User |
| **Provider No-Show** | Timeout after acceptance | Reassign provider | Full leg refund | User |
| **Hub Closure** | Unexpected hub unavailability | Route to alternative hub | Full refund | User |
| **Weight Dispute** | User disputes verified weight | Mediation review | Use average weight | User, Company |

---

## APPENDIX A: DATABASE MIGRATIONS SUMMARY

```php
<?php

// Migration: 2025_02_11_000004_add_commission_insurance_sequential_payment.php

class AddCommissionInsuranceSequentialPayment extends Migration
{
    public function up()
    {
        // 1. Platform commission config table
        Schema::create('platform_commission_configs', function (Blueprint $table) {
            $table->id();
            $table->enum('leg_type', ['local_pickup', 'interstate_trucking', 'local_dropoff']);
            $table->enum('commission_type', ['percentage', 'fixed_amount']);
            $table->decimal('commission_value', 10, 2);
            $table->decimal('min_commission_amount', 10, 2)->default(0);
            $table->decimal('max_commission_amount', 10, 2)->nullable();
            $table->enum('apply_to', ['base_price', 'total_with_insurance'])->default('base_price');
            $table->boolean('is_active')->default(true);
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->unique('leg_type');
        });
        
        // 2. Goods items table
        Schema::create('trucking_goods_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->onDelete('cascade');
            $table->foreignId('request_leg_id')->nullable()->constrained('request_legs')->onDelete('set null');
            $table->string('item_number');
            $table->integer('item_index')->default(1);
            $table->string('description')->nullable();
            $table->enum('category', ['electronics', 'fashion', 'food', 'documents', 'fragile', 'general'])->default('general');
            $table->decimal('weight_kg', 8, 2);
            $table->decimal('length_cm', 8, 2)->default(0);
            $table->decimal('width_cm', 8, 2)->default(0);
            $table->decimal('height_cm', 8, 2)->default(0);
            $table->integer('quantity')->default(1);
            $table->decimal('volumetric_weight_kg', 8, 2);
            $table->decimal('chargeable_weight_kg', 8, 2);
            $table->decimal('declared_value', 12, 2)->default(0);
            $table->boolean('requires_insurance')->default(false);
            $table->enum('insurance_type', ['none', 'basic', 'premium'])->default('none');
            $table->decimal('insurance_rate_percent', 5, 2)->default(0);
            $table->decimal('insurance_fee', 10, 2)->default(0);
            $table->boolean('is_fragile')->default(false);
            $table->boolean('is_hazardous')->default(false);
            $table->boolean('is_perishable')->default(false);
            $table->text('special_instructions')->nullable();
            $table->enum('status', ['pending', 'in_transit', 'delivered', 'damaged', 'lost'])->default('pending');
            $table->timestamps();
            $table->index('request_id');
            $table->index('request_leg_id');
        });
        
        // 3. Add insurance columns to trucking_companies
        Schema::table('trucking_companies', function (Blueprint $table) {
            $table->enum('insurance_type', ['percentage_of_value', 'fixed_per_shipment', 'per_item_rate'])
                ->default('percentage_of_value')->after('rating');
            $table->decimal('insurance_rate_percent', 5, 2)->default(1.00)->after('insurance_type');
            $table->decimal('insurance_fixed_amount', 10, 2)->default(0)->after('insurance_rate_percent');
            $table->decimal('insurance_minimum_amount', 10, 2)->default(500.00)->after('insurance_fixed_amount');
            $table->decimal('insurance_maximum_amount', 10, 2)->default(50000.00)->after('insurance_minimum_amount');
            $table->boolean('insurance_mandatory')->default(false)->after('insurance_maximum_amount');
            $table->json('insurance_category_rates')->nullable()->after('insurance_mandatory');
            $table->enum('insurance_provider_type', ['self_insured', 'third_party'])
                ->default('self_insured')->after('insurance_category_rates');
            $table->string('insurance_provider_name')->nullable()->after('insurance_provider_type');
            $table->string('insurance_policy_number')->nullable()->after('insurance_provider_name');
            $table->decimal('max_claim_amount', 12, 2)->default(100000.00)->after('insurance_policy_number');
            $table->integer('claim_processing_days')->default(14)->after('max_claim_amount');
        });
        
        // 4. Add columns to request_legs
        Schema::table('request_legs', function (Blueprint $table) {
            $table->decimal('provider_base_price', 10, 2)->default(0)->after('provider_phone');
            $table->decimal('platform_commission', 10, 2)->default(0)->after('insurance_charge');
            $table->decimal('total_leg_price', 10, 2)->default(0)->after('platform_commission');
            $table->decimal('provider_payout_amount', 10, 2)->default(0)->after('total_leg_price');
            $table->enum('provider_payout_status', ['pending', 'processing', 'paid', 'failed'])
                ->default('pending')->after('provider_payout_amount');
            $table->timestamp('provider_payout_at')->nullable()->after('provider_payout_status');
            $table->string('provider_payout_reference')->nullable()->after('provider_payout_at');
            $table->enum('payment_status', ['pending', 'awaiting_confirmation', 'paid', 'refunded', 'failed'])
                ->default('pending')->after('delivery_proof');
            $table->decimal('paid_amount', 10, 2)->default(0)->after('payment_status');
            $table->timestamp('paid_at')->nullable()->after('paid_amount');
            $table->string('payment_reference')->nullable()->after('paid_at');
            $table->decimal('refund_amount', 10, 2)->default(0)->after('payment_reference');
            $table->text('refund_reason')->nullable()->after('refund_amount');
            $table->timestamp('refunded_at')->nullable()->after('refund_reason');
        });
        
        // 5. Add columns to leg_payments
        Schema::table('leg_payments', function (Blueprint $table) {
            $table->enum('leg_state', ['awaiting_payment', 'payment_pending', 'paid', 'in_progress', 'completed', 'failed'])
                ->default('awaiting_payment')->after('payment_status');
            $table->string('authorization_code')->nullable()->after('leg_state');
            $table->timestamp('authorized_at')->nullable()->after('authorization_code');
            $table->timestamp('authorization_expires_at')->nullable()->after('authorized_at');
            $table->json('display_breakdown')->nullable()->after('authorization_expires_at');
            $table->boolean('triggers_next_leg')->default(true)->after('display_breakdown');
            $table->timestamp('next_leg_triggered_at')->nullable()->after('triggers_next_leg');
        });
        
        // 6. Add columns to requests
        Schema::table('requests', function (Blueprint $table) {
            $table->decimal('total_provider_base', 10, 2)->default(0)->after('local_delivery_fee');
            $table->decimal('total_insurance_fee', 10, 2)->default(0)->after('total_provider_base');
            $table->decimal('total_platform_commission', 10, 2)->default(0)->after('total_insurance_fee');
            $table->integer('legs_paid_count')->default(0)->after('total_platform_commission');
            $table->integer('legs_completed_count')->default(0)->after('legs_paid_count');
            $table->decimal('total_paid_amount', 12, 2)->default(0)->after('legs_completed_count');
            $table->decimal('remaining_balance', 12, 2)->default(0)->after('total_paid_amount');
            $table->enum('current_leg_payment_status', ['not_required', 'awaiting_payment', 'payment_pending', 'paid'])
                ->default('not_required')->after('remaining_balance');
            $table->decimal('total_declared_value', 12, 2)->default(0)->after('current_leg_payment_status');
            $table->enum('insurance_coverage_status', ['none', 'partial', 'full'])
                ->default('none')->after('total_declared_value');
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('platform_commission_configs');
        Schema::dropIfExists('trucking_goods_items');
        
        Schema::table('trucking_companies', function (Blueprint $table) {
            $table->dropColumn([
                'insurance_type', 'insurance_rate_percent', 'insurance_fixed_amount',
                'insurance_minimum_amount', 'insurance_maximum_amount', 'insurance_mandatory',
                'insurance_category_rates', 'insurance_provider_type', 'insurance_provider_name',
                'insurance_policy_number', 'max_claim_amount', 'claim_processing_days'
            ]);
        });
        
        Schema::table('request_legs', function (Blueprint $table) {
            $table->dropColumn([
                'provider_base_price', 'platform_commission', 'total_leg_price',
                'provider_payout_amount', 'provider_payout_status', 'provider_payout_at',
                'provider_payout_reference', 'payment_status', 'paid_amount', 'paid_at',
                'payment_reference', 'refund_amount', 'refund_reason', 'refunded_at'
            ]);
        });
        
        Schema::table('leg_payments', function (Blueprint $table) {
            $table->dropColumn([
                'leg_state', 'authorization_code', 'authorized_at', 'authorization_expires_at',
                'display_breakdown', 'triggers_next_leg', 'next_leg_triggered_at'
            ]);
        });
        
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn([
                'total_provider_base', 'total_insurance_fee', 'total_platform_commission',
                'legs_paid_count', 'legs_completed_count', 'total_paid_amount',
                'remaining_balance', 'current_leg_payment_status', 'total_declared_value',
                'insurance_coverage_status'
            ]);
        });
    }
}
```

---

## END OF DOCUMENT

**Implementation Checklist:**

- [ ] Run database migrations
- [ ] Seed default commission configurations
- [ ] Implement CommissionCalculationService
- [ ] Implement InsuranceCalculationService
- [ ] Implement SequentialLegPaymentService
- [ ] Implement LegSettlementService
- [ ] Create admin commission configuration UI
- [ ] Create trucking company insurance configuration UI
- [ ] Update chain builder UI/API
- [ ] Implement payment per leg flow
- [ ] Test all edge cases

**Document Version History:**

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-02-11 | Initial comprehensive architecture extension |
