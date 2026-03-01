# Multi-Leg Interstate Delivery System Architecture
## Tagxi Platform Extension for Nigeria Logistics Market

---

## 1. EXECUTIVE SUMMARY

This document outlines the architecture for extending Tagxi to support multi-leg interstate goods delivery with multiple trucking companies and physical hubs. The system enables complex delivery chains: Local Pickup → Hub Drop-off → Interstate Transport → Hub Pickup → Local Delivery.

---

## 2. DATABASE SCHEMA DESIGN

### 2.1 Core Tables

```sql
-- TRUCKING COMPANIES (Onboarded like providers)
CREATE TABLE trucking_companies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    registration_number VARCHAR(100) UNIQUE,
    email VARCHAR(255),
    phone VARCHAR(50),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    logo VARCHAR(255),
    status ENUM('pending', 'active', 'suspended', 'inactive') DEFAULT 'pending',
    is_verified BOOLEAN DEFAULT FALSE,
    commission_rate DECIMAL(5,2) DEFAULT 10.00,
    insurance_provider VARCHAR(255),
    insurance_policy_number VARCHAR(100),
    fleet_size INTEGER DEFAULT 0,
    service_types JSON, -- ['general_cargo', 'perishables', 'hazmat', 'oversized']
    operating_hours JSON,
    sla_hours INTEGER DEFAULT 72, -- Standard delivery SLA
    rating DECIMAL(2,1) DEFAULT 5.0,
    total_trips INTEGER DEFAULT 0,
    created_by BIGINT UNSIGNED, -- admin who onboarded
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    INDEX idx_status (status),
    INDEX idx_state (state),
    INDEX idx_verified (is_verified)
);

-- TRUCKING HUBS (Physical Locations)
CREATE TABLE trucking_hubs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trucking_company_id BIGINT UNSIGNED NOT NULL,
    hub_name VARCHAR(255) NOT NULL,
    hub_code VARCHAR(50) UNIQUE NOT NULL,
    hub_type ENUM('origin_only', 'destination_only', 'both', 'transit') DEFAULT 'both',
    address TEXT NOT NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    country VARCHAR(100) DEFAULT 'Nigeria',
    postal_code VARCHAR(20),
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    phone VARCHAR(50),
    email VARCHAR(255),
    operating_hours JSON,
    max_storage_days INTEGER DEFAULT 7,
    daily_capacity INTEGER DEFAULT 100, -- packages per day
    facilities JSON, -- ['parking', 'forklift', 'cold_storage', 'security']
    is_active BOOLEAN DEFAULT TRUE,
    accepts_dropoff BOOLEAN DEFAULT TRUE,
    accepts_pickup BOOLEAN DEFAULT TRUE,
    geofence_radius_meters INTEGER DEFAULT 500,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (trucking_company_id) REFERENCES trucking_companies(id) ON DELETE CASCADE,
    INDEX idx_company (trucking_company_id),
    INDEX idx_city_state (city, state),
    INDEX idx_location (latitude, longitude),
    INDEX idx_hub_type (hub_type),
    INDEX idx_active (is_active),
    SPATIAL INDEX idx_geo (latitude, longitude)
);

-- SUPPORTED ROUTES (Hub to Hub connections)
CREATE TABLE supported_routes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trucking_company_id BIGINT UNSIGNED NOT NULL,
    origin_hub_id BIGINT UNSIGNED NOT NULL,
    destination_hub_id BIGINT UNSIGNED NOT NULL,
    route_code VARCHAR(100) UNIQUE NOT NULL,
    distance_km DECIMAL(10,2),
    estimated_duration_hours INTEGER,
    route_type ENUM('direct', 'via_transit') DEFAULT 'direct',
    transit_hub_id BIGINT UNSIGNED NULL,
    
    -- Pricing Configuration
    pricing_model ENUM('fixed', 'slab_weight', 'slab_distance', 'negotiated') DEFAULT 'fixed',
    base_price DECIMAL(12,2) DEFAULT 0.00,
    price_per_kg DECIMAL(8,2) DEFAULT 0.00,
    price_per_km DECIMAL(8,2) DEFAULT 0.00,
    min_charge DECIMAL(10,2) DEFAULT 1000.00,
    max_charge DECIMAL(12,2) DEFAULT 100000.00,
    
    -- Slab Pricing (JSON for flexibility)
    weight_slabs JSON, -- [{"min":0,"max":5,"price":1500}, {"min":5,"max":20,"price":2500}]
    distance_slabs JSON,
    
    -- Capacity & Scheduling
    max_daily_capacity INTEGER DEFAULT 50,
    available_days JSON, -- [1,2,3,4,5,6] Monday-Saturday
    departure_slots JSON, -- ["08:00", "14:00", "20:00"]
    cutoff_hours_before INTEGER DEFAULT 4,
    
    -- Service Levels
    standard_sla_hours INTEGER DEFAULT 72,
    express_sla_hours INTEGER DEFAULT 48,
    express_surcharge_percent DECIMAL(5,2) DEFAULT 50.00,
    
    is_active BOOLEAN DEFAULT TRUE,
    priority INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (trucking_company_id) REFERENCES trucking_companies(id) ON DELETE CASCADE,
    FOREIGN KEY (origin_hub_id) REFERENCES trucking_hubs(id) ON DELETE CASCADE,
    FOREIGN KEY (destination_hub_id) REFERENCES trucking_hubs(id) ON DELETE CASCADE,
    FOREIGN KEY (transit_hub_id) REFERENCES trucking_hubs(id) ON DELETE SET NULL,
    
    INDEX idx_company (trucking_company_id),
    INDEX idx_route (origin_hub_id, destination_hub_id),
    INDEX idx_active (is_active),
    UNIQUE KEY unique_company_route (trucking_company_id, origin_hub_id, destination_hub_id)
);

-- DELIVERY ORDERS (Parent - Customer-facing)
CREATE TABLE delivery_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) UNIQUE NOT NULL, -- ORD-2024-001234
    
    -- Customer Info
    buyer_id BIGINT UNSIGNED NOT NULL,
    buyer_name VARCHAR(255),
    buyer_phone VARCHAR(50),
    buyer_email VARCHAR(255),
    
    -- Seller Info
    seller_id BIGINT UNSIGNED NOT NULL,
    seller_name VARCHAR(255),
    seller_phone VARCHAR(50),
    
    -- Origin Location (Pickup)
    origin_address TEXT NOT NULL,
    origin_city VARCHAR(100) NOT NULL,
    origin_state VARCHAR(100) NOT NULL,
    origin_latitude DECIMAL(10,8),
    origin_longitude DECIMAL(11,8),
    
    -- Destination Location (Final)
    destination_address TEXT NOT NULL,
    destination_city VARCHAR(100) NOT NULL,
    destination_state VARCHAR(100) NOT NULL,
    destination_latitude DECIMAL(10,8),
    destination_longitude DECIMAL(11,8),
    
    -- Package Details
    package_type ENUM('document', 'parcel', 'bulk', 'pallet', 'container') DEFAULT 'parcel',
    weight_kg DECIMAL(8,2) NOT NULL,
    dimensions_cm JSON, -- {"length":30,"width":20,"height":15}
    volume_cbm DECIMAL(8,3),
    declared_value DECIMAL(12,2),
    description TEXT,
    special_instructions TEXT,
    
    -- Delivery Preferences
    service_type ENUM('standard', 'express', 'same_day') DEFAULT 'standard',
    signature_required BOOLEAN DEFAULT FALSE,
    insurance_required BOOLEAN DEFAULT FALSE,
    insurance_amount DECIMAL(12,2),
    
    -- Selected Route
    selected_route_id BIGINT UNSIGNED,
    selected_trucking_company_id BIGINT UNSIGNED,
    
    -- Financial Summary
    total_amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'NGN',
    local_pickup_fee DECIMAL(10,2) DEFAULT 0.00,
    interstate_fee DECIMAL(10,2) DEFAULT 0.00,
    local_delivery_fee DECIMAL(10,2) DEFAULT 0.00,
    insurance_fee DECIMAL(10,2) DEFAULT 0.00,
    vat_amount DECIMAL(10,2) DEFAULT 0.00,
    
    -- Payment
    payment_status ENUM('pending', 'paid', 'partial', 'refunded', 'failed') DEFAULT 'pending',
    payment_method VARCHAR(50),
    paid_at TIMESTAMP NULL,
    
    -- Order Status (Overall)
    status ENUM(
        'draft',
        'pending_confirmation',
        'confirmed',
        'pickup_scheduled',
        'picked_up',
        'at_origin_hub',
        'in_transit',
        'at_destination_hub',
        'out_for_delivery',
        'delivered',
        'cancelled',
        'failed'
    ) DEFAULT 'draft',
    
    -- Timestamps
    expected_pickup_at TIMESTAMP NULL,
    expected_delivery_at TIMESTAMP NULL,
    actual_pickup_at TIMESTAMP NULL,
    actual_delivery_at TIMESTAMP NULL,
    
    -- Tracking
    tracking_number VARCHAR(100) UNIQUE,
    qr_code VARCHAR(255),
    barcode VARCHAR(255),
    
    -- Hub Assignment
    origin_hub_id BIGINT UNSIGNED,
    destination_hub_id BIGINT UNSIGNED,
    
    -- Metadata
    source ENUM('app', 'web', 'api', 'admin') DEFAULT 'app',
    metadata JSON,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    cancelled_at TIMESTAMP NULL,
    cancelled_reason TEXT,
    
    FOREIGN KEY (buyer_id) REFERENCES users(id),
    FOREIGN KEY (seller_id) REFERENCES users(id),
    FOREIGN KEY (selected_route_id) REFERENCES supported_routes(id),
    FOREIGN KEY (selected_trucking_company_id) REFERENCES trucking_companies(id),
    FOREIGN KEY (origin_hub_id) REFERENCES trucking_hubs(id),
    FOREIGN KEY (destination_hub_id) REFERENCES trucking_hubs(id),
    
    INDEX idx_order_number (order_number),
    INDEX idx_buyer (buyer_id),
    INDEX idx_status (status),
    INDEX idx_tracking (tracking_number),
    INDEX idx_route (origin_state, destination_state),
    INDEX idx_created (created_at)
);

-- DELIVERY LEGS (Child - Internal orchestration)
CREATE TABLE delivery_legs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    delivery_order_id BIGINT UNSIGNED NOT NULL,
    leg_number INTEGER NOT NULL, -- 1, 2, 3, 4 for multi-leg
    leg_type ENUM(
        'local_pickup',        -- Rider picks from seller
        'hub_dropoff',         -- Rider drops at origin hub
        'interstate_transport', -- Trucking company transport
        'hub_pickup',          -- Rider picks from dest hub
        'local_delivery'       -- Rider delivers to buyer
    ) NOT NULL,
    
    -- Provider Assignment
    provider_type ENUM('dispatch_rider', 'trucking_company') NOT NULL,
    provider_id BIGINT UNSIGNED, -- rider_id or trucking_company_id
    provider_name VARCHAR(255),
    provider_phone VARCHAR(50),
    
    -- Route Details
    from_location JSON NOT NULL, -- {"address":"...","lat":6.52,"lng":3.37,"hub_id":5}
    to_location JSON NOT NULL,
    distance_km DECIMAL(10,2),
    estimated_duration_minutes INTEGER,
    
    -- Financial
    base_amount DECIMAL(10,2) NOT NULL,
    surcharge_amount DECIMAL(10,2) DEFAULT 0.00,
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    final_amount DECIMAL(10,2) NOT NULL,
    provider_earnings DECIMAL(10,2) NOT NULL,
    platform_commission DECIMAL(10,2) NOT NULL,
    
    -- Status & Lifecycle
    status ENUM(
        'pending_assignment',
        'assigned',
        'accepted',
        'en_route_to_pickup',
        'arrived_at_pickup',
        'picked_up',
        'in_transit',
        'arrived_at_destination',
        'delivered',
        'failed',
        'cancelled'
    ) DEFAULT 'pending_assignment',
    
    -- Bidding (for local legs)
    bidding_type ENUM('fixed', 'bidding') DEFAULT 'fixed',
    bid_request_id BIGINT UNSIGNED,
    winning_bid_id BIGINT UNSIGNED,
    
    -- Timestamps
    assigned_at TIMESTAMP NULL,
    accepted_at TIMESTAMP NULL,
    started_at TIMESTAMP NULL,
    picked_up_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    failed_at TIMESTAMP NULL,
    
    -- Location Tracking
    current_latitude DECIMAL(10,8),
    current_longitude DECIMAL(11,8),
    location_updated_at TIMESTAMP NULL,
    
    -- Proof of Delivery/Handoff
    pickup_proof JSON, -- {"photo":"url","signature":"base64","otp":"1234","timestamp":"..."}
    delivery_proof JSON,
    
    -- Notes & Issues
    notes TEXT,
    failure_reason TEXT,
    exception_code VARCHAR(50),
    
    -- Relationships
    parent_leg_id BIGINT UNSIGNED NULL, -- For linked legs
    next_leg_id BIGINT UNSIGNED NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (delivery_order_id) REFERENCES delivery_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (bid_request_id) REFERENCES bid_requests(id) ON DELETE SET NULL,
    
    INDEX idx_order (delivery_order_id),
    INDEX idx_leg_number (delivery_order_id, leg_number),
    INDEX idx_status (status),
    INDEX idx_provider (provider_type, provider_id),
    INDEX idx_assigned (assigned_at)
);

-- LEG STATUS HISTORY (Audit Trail)
CREATE TABLE delivery_leg_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    delivery_leg_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(50) NOT NULL,
    previous_status VARCHAR(50),
    changed_by ENUM('system', 'provider', 'admin', 'customer', 'api') DEFAULT 'system',
    changed_by_id BIGINT UNSIGNED,
    location_latitude DECIMAL(10,8),
    location_longitude DECIMAL(11,8),
    notes TEXT,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (delivery_leg_id) REFERENCES delivery_legs(id) ON DELETE CASCADE,
    INDEX idx_leg (delivery_leg_id),
    INDEX idx_created (created_at)
);

-- HUB INVENTORY (Packages at hubs)
CREATE TABLE hub_inventory (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hub_id BIGINT UNSIGNED NOT NULL,
    delivery_order_id BIGINT UNSIGNED NOT NULL,
    delivery_leg_id BIGINT UNSIGNED NOT NULL,
    
    -- Storage Details
    storage_location VARCHAR(100), -- Rack/Shelf number
    received_at TIMESTAMP NOT NULL,
    expected_departure_at TIMESTAMP,
    actual_departure_at TIMESTAMP NULL,
    
    -- Status
    status ENUM('received', 'checked_in', 'stored', 'ready_for_dispatch', 'loaded', 'dispatched') DEFAULT 'received',
    
    -- Handoff Details
    received_by BIGINT UNSIGNED, -- staff_id
    dispatched_by BIGINT UNSIGNED,
    received_from_provider_id BIGINT UNSIGNED,
    dispatched_to_provider_id BIGINT UNSIGNED,
    
    -- Notifications
    customer_notified_at TIMESTAMP NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (hub_id) REFERENCES trucking_hubs(id),
    FOREIGN KEY (delivery_order_id) REFERENCES delivery_orders(id),
    FOREIGN KEY (delivery_leg_id) REFERENCES delivery_legs(id),
    
    INDEX idx_hub (hub_id),
    INDEX idx_order (delivery_order_id),
    INDEX idx_status (status)
);

-- TRUCKING COMPANY RATINGS
CREATE TABLE trucking_company_ratings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trucking_company_id BIGINT UNSIGNED NOT NULL,
    delivery_order_id BIGINT UNSIGNED NOT NULL,
    rated_by ENUM('buyer', 'seller', 'system') NOT NULL,
    rater_id BIGINT UNSIGNED,
    
    rating DECIMAL(2,1) NOT NULL,
    punctuality_rating DECIMAL(2,1),
    condition_rating DECIMAL(2,1),
    communication_rating DECIMAL(2,1),
    
    review TEXT,
    is_visible BOOLEAN DEFAULT TRUE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (trucking_company_id) REFERENCES trucking_companies(id) ON DELETE CASCADE,
    FOREIGN KEY (delivery_order_id) REFERENCES delivery_orders(id) ON DELETE CASCADE,
    
    INDEX idx_company (trucking_company_id),
    INDEX idx_rating (rating)
);
```

---

## 3. LIFECYCLE STATES

### 3.1 Delivery Order States

```
DRAFT
  ↓ [Customer confirms order]
PENDING_CONFIRMATION
  ↓ [System validates route & pricing]
CONFIRMED
  ↓ [Leg 1 assigned]
PICKUP_SCHEDULED
  ↓ [Rider picks up package]
PICKED_UP
  ↓ [Rider delivers to origin hub]
AT_ORIGIN_HUB
  ↓ [Trucking company departs]
IN_TRANSIT
  ↓ [Arrives at destination hub]
AT_DESTINATION_HUB
  ↓ [Final leg assigned]
OUT_FOR_DELIVERY
  ↓ [Delivered to buyer]
DELIVERED

Alternative paths:
  - Any state → CANCELLED (before pickup)
  - Any state → FAILED (delivery impossible)
```

### 3.2 Delivery Leg States

```
PENDING_ASSIGNMENT
  ↓ [System/Admin assigns provider]
ASSIGNED
  ↓ [Provider accepts]
ACCEPTED
  ↓ [Provider starts journey]
EN_ROUTE_TO_PICKUP
  ↓ [Arrives at pickup location]
ARRIVED_AT_PICKUP
  ↓ [Collects package]
PICKED_UP
  ↓ [In transit to destination]
IN_TRANSIT
  ↓ [Arrives at destination]
ARRIVED_AT_DESTINATION
  ↓ [Completes handoff/delivery]
DELIVERED
```

---

## 4. TRUCKING PARTNER SELECTION LOGIC

### 4.1 Route Matching Algorithm

```php
class TruckingPartnerSelector
{
    public function selectOptimalRoute(
        string $originCity,
        string $destinationCity,
        float $weightKg,
        string $serviceType = 'standard',
        array $preferences = []
    ): RouteRecommendation {
        
        // Step 1: Find available origin hubs near pickup
        $originHubs = $this->findNearbyHubs(
            city: $originCity,
            acceptsDropoff: true,
            maxDistanceKm: 50
        );
        
        // Step 2: Find available destination hubs near delivery
        $destinationHubs = $this->findNearbyHubs(
            city: $destinationCity,
            acceptsPickup: true,
            maxDistanceKm: 50
        );
        
        // Step 3: Find routes connecting origin → destination hubs
        $possibleRoutes = SupportedRoute::whereIn('origin_hub_id', $originHubs->pluck('id'))
            ->whereIn('destination_hub_id', $destinationHubs->pluck('id'))
            ->where('is_active', true)
            ->where('max_daily_capacity', '>', DB::raw('daily_booked_count'))
            ->with(['truckingCompany', 'originHub', 'destinationHub'])
            ->get();
        
        // Step 4: Score and rank routes
        $scoredRoutes = $possibleRoutes->map(function($route) use ($weightKg, $serviceType) {
            $score = $this->calculateRouteScore($route, [
                'price_weight' => 0.4,
                'speed_weight' => 0.3,
                'reliability_weight' => 0.2,
                'proximity_weight' => 0.1
            ]);
            
            return [
                'route' => $route,
                'score' => $score,
                'price' => $this->calculatePrice($route, $weightKg, $serviceType),
                'eta_hours' => $serviceType === 'express' 
                    ? $route->express_sla_hours 
                    : $route->standard_sla_hours
            ];
        })->sortByDesc('score');
        
        return new RouteRecommendation(
            routes: $scoredRoutes->take(3),
            recommended: $scoredRoutes->first()
        );
    }
    
    private function calculateRouteScore(SupportedRoute $route, array $weights): float
    {
        $priceScore = $this->normalizePrice($route->base_price);
        $speedScore = 100 - min($route->standard_sla_hours / 72 * 100, 100);
        $reliabilityScore = $route->truckingCompany->rating * 20;
        $proximityScore = $this->calculateProximityScore($route);
        
        return (
            $priceScore * $weights['price_weight'] +
            $speedScore * $weights['speed_weight'] +
            $reliabilityScore * $weights['reliability_weight'] +
            $proximityScore * $weights['proximity_weight']
        );
    }
}
```

---

## 5. PRICING COMPUTATION

### 5.1 Multi-Leg Pricing Service

```php
class InterstateDeliveryPricingService
{
    public function calculateTotalPrice(DeliveryOrder $order): PricingBreakdown
    {
        $legs = $this->determineDeliveryLegs($order);
        $legPrices = [];
        $total = 0;
        
        foreach ($legs as $index => $leg) {
            $legPrice = match($leg['type']) {
                'local_pickup', 'local_delivery' => 
                    $this->calculateLocalDeliveryPrice($leg, $order),
                'hub_dropoff', 'hub_pickup' => 
                    $this->calculateHubTransferPrice($leg),
                'interstate_transport' => 
                    $this->calculateInterstatePrice($leg, $order),
                default => throw new InvalidLegTypeException()
            };
            
            $legPrices[] = $legPrice;
            $total += $legPrice->finalAmount;
        }
        
        // Add insurance if required
        if ($order->insurance_required) {
            $total += $this->calculateInsurance($order->declared_value);
        }
        
        // Add VAT (7.5% for Nigeria)
        $vat = $total * 0.075;
        
        return new PricingBreakdown(
            legs: $legPrices,
            subtotal: $total,
            vat: $vat,
            total: $total + $vat,
            currency: 'NGN'
        );
    }
    
    private function calculateInterstatePrice(array $leg, DeliveryOrder $order): LegPrice
    {
        $route = SupportedRoute::find($leg['route_id']);
        $weight = $order->weight_kg;
        
        $baseAmount = match($route->pricing_model) {
            'fixed' => $route->base_price,
            
            'slab_weight' => $this->calculateSlabPrice(
                $route->weight_slabs, 
                $weight
            ),
            
            'slab_distance' => $this->calculateSlabPrice(
                $route->distance_slabs,
                $leg['distance_km']
            ),
            
            default => $route->base_price + ($weight * $route->price_per_kg)
        };
        
        // Apply service type surcharge
        if ($order->service_type === 'express') {
            $baseAmount *= (1 + $route->express_surcharge_percent / 100);
        }
        
        // Ensure min/max bounds
        $baseAmount = max($route->min_charge, min($baseAmount, $route->max_charge));
        
        // Calculate splits
        $commission = $baseAmount * ($route->truckingCompany->commission_rate / 100);
        
        return new LegPrice(
            baseAmount: $baseAmount,
            surcharge: 0,
            discount: 0,
            finalAmount: $baseAmount,
            providerEarnings: $baseAmount - $commission,
            platformCommission: $commission
        );
    }
    
    private function calculateSlabPrice(array $slabs, float $value): float
    {
        foreach ($slabs as $slab) {
            if ($value >= $slab['min'] && $value <= $slab['max']) {
                return $slab['price'];
            }
        }
        return $slabs[count($slabs) - 1]['price'] ?? 0;
    }
}
```

---

## 6. BACKEND ORCHESTRATION

### 6.1 Core Services

```php
// Delivery Order Service
class DeliveryOrderService
{
    public function createOrder(CreateOrderRequest $request): DeliveryOrder
    {
        return DB::transaction(function() use ($request) {
            // 1. Validate cities are served
            $this->validateServiceCoverage($request->origin_city, $request->destination_city);
            
            // 2. Find optimal route
            $routeRecommendation = $this->routeSelector->selectOptimalRoute(
                originCity: $request->origin_city,
                destinationCity: $request->destination_city,
                weightKg: $request->weight_kg,
                serviceType: $request->service_type
            );
            
            // 3. Calculate pricing
            $pricing = $this->pricingService->calculateTotalPrice(
                order: $request,
                route: $routeRecommendation->recommended
            );
            
            // 4. Create order
            $order = DeliveryOrder::create([
                'order_number' => $this->generateOrderNumber(),
                'buyer_id' => $request->buyer_id,
                'seller_id' => $request->seller_id,
                'origin_hub_id' => $routeRecommendation->recommended->origin_hub_id,
                'destination_hub_id' => $routeRecommendation->recommended->destination_hub_id,
                'selected_route_id' => $routeRecommendation->recommended->id,
                'selected_trucking_company_id' => $routeRecommendation->recommended->trucking_company_id,
                'total_amount' => $pricing->total,
                'local_pickup_fee' => $pricing->legs[0]->finalAmount,
                'interstate_fee' => $pricing->legs[2]->finalAmount,
                'local_delivery_fee' => $pricing->legs[4]->finalAmount ?? 0,
                'status' => 'pending_confirmation',
                ...$request->validated()
            ]);
            
            // 5. Create delivery legs
            $this->createDeliveryLegs($order, $routeRecommendation->recommended);
            
            // 6. Reserve capacity
            $this->capacityService->reserveCapacity(
                routeId: $routeRecommendation->recommended->id,
                date: $request->preferred_pickup_date,
                slots: 1
            );
            
            // 7. Emit events
            event(new DeliveryOrderCreated($order));
            
            return $order;
        });
    }
    
    private function createDeliveryLegs(DeliveryOrder $order, SupportedRoute $route): void
    {
        $legs = [
            [
                'leg_number' => 1,
                'leg_type' => 'local_pickup',
                'provider_type' => 'dispatch_rider',
                'from_location' => $this->formatLocation($order->pickup_address),
                'to_location' => $this->formatHubLocation($route->originHub),
                'base_amount' => $order->local_pickup_fee
            ],
            [
                'leg_number' => 2,
                'leg_type' => 'hub_dropoff',
                'provider_type' => 'dispatch_rider',
                'from_location' => $this->formatHubLocation($route->originHub),
                'to_location' => $this->formatHubLocation($route->originHub), // Same location, handoff
                'base_amount' => 0 // Included in pickup
            ],
            [
                'leg_number' => 3,
                'leg_type' => 'interstate_transport',
                'provider_type' => 'trucking_company',
                'provider_id' => $route->trucking_company_id,
                'from_location' => $this->formatHubLocation($route->originHub),
                'to_location' => $this->formatHubLocation($route->destinationHub),
                'base_amount' => $order->interstate_fee
            ],
            [
                'leg_number' => 4,
                'leg_type' => 'hub_pickup',
                'provider_type' => 'dispatch_rider',
                'from_location' => $this->formatHubLocation($route->destinationHub),
                'to_location' => $this->formatHubLocation($route->destinationHub),
                'base_amount' => 0 // Included in delivery
            ],
            [
                'leg_number' => 5,
                'leg_type' => 'local_delivery',
                'provider_type' => 'dispatch_rider',
                'from_location' => $this->formatHubLocation($route->destinationHub),
                'to_location' => $this->formatLocation($order->delivery_address),
                'base_amount' => $order->local_delivery_fee
            ]
        ];
        
        foreach ($legs as $legData) {
            DeliveryLeg::create([
                'delivery_order_id' => $order->id,
                'status' => 'pending_assignment',
                ...$legData
            ]);
        }
    }
}

// Leg Orchestration Service
class LegOrchestrationService
{
    public function onLegCompleted(DeliveryLeg $completedLeg): void
    {
        $order = $completedLeg->deliveryOrder;
        
        // Update order status based on leg completion
        match($completedLeg->leg_type) {
            'local_pickup' => $order->updateStatus('picked_up'),
            'hub_dropoff' => $this->onHubDropoff($completedLeg, $order),
            'interstate_transport' => $this->onInterstateComplete($completedLeg, $order),
            'hub_pickup' => $order->updateStatus('out_for_delivery'),
            'local_delivery' => $this->onDeliveryComplete($completedLeg, $order),
        };
        
        // Trigger next leg if applicable
        $nextLeg = $this->getNextLeg($completedLeg);
        if ($nextLeg) {
            $this->triggerNextLeg($nextLeg, $completedLeg);
        }
    }
    
    private function onHubDropoff(DeliveryLeg $leg, DeliveryOrder $order): void
    {
        // Create hub inventory record
        HubInventory::create([
            'hub_id' => $order->origin_hub_id,
            'delivery_order_id' => $order->id,
            'delivery_leg_id' => $leg->id,
            'status' => 'received',
            'received_from_provider_id' => $leg->provider_id
        ]);
        
        $order->updateStatus('at_origin_hub');
        
        // Notify trucking company
        event(new PackageReadyForTransport($order));
    }
    
    private function triggerNextLeg(DeliveryLeg $nextLeg, DeliveryLeg $completedLeg): void
    {
        match($nextLeg->leg_type) {
            'interstate_transport' => $this->assignTruckingCompany($nextLeg),
            'local_delivery' => $this->createBiddingRequest($nextLeg),
            default => $this->assignNearbyRider($nextLeg)
        };
    }
}
```

---

## 7. API ENDPOINTS

### 7.1 Core APIs

```php
// routes/api/interstate_delivery.php

// Customer APIs
Route::post('/interstate/quote', [InterstateDeliveryController::class, 'getQuote']);
Route::post('/interstate/orders', [InterstateDeliveryController::class, 'createOrder']);
Route::get('/interstate/orders/{order}', [InterstateDeliveryController::class, 'getOrder']);
Route::get('/interstate/orders/{order}/tracking', [InterstateDeliveryController::class, 'getTracking']);
Route::get('/interstate/orders/{order}/timeline', [InterstateDeliveryController::class, 'getTimeline']);

// Trucking Company APIs
Route::middleware('auth:trucking')->group(function () {
    Route::get('/trucking/dashboard', [TruckingCompanyController::class, 'dashboard']);
    Route::get('/trucking/orders', [TruckingCompanyController::class, 'listOrders']);
    Route::post('/trucking/orders/{leg}/accept', [TruckingCompanyController::class, 'acceptOrder']);
    Route::post('/trucking/orders/{leg}/depart', [TruckingCompanyController::class, 'markDeparted']);
    Route::post('/trucking/orders/{leg}/arrive', [TruckingCompanyController::class, 'markArrived']);
    Route::get('/trucking/hubs/{hub}/inventory', [TruckingCompanyController::class, 'getInventory']);
});

// Hub Staff APIs
Route::middleware('auth:hub_staff')->group(function () {
    Route::post('/hubs/{hub}/check-in', [HubController::class, 'checkInPackage']);
    Route::post('/hubs/{hub}/check-out', [HubController::class, 'checkOutPackage']);
    Route::get('/hubs/{hub}/pending-pickups', [HubController::class, 'getPendingPickups']);
});

// Admin APIs
Route::middleware('auth:admin')->group(function () {
    Route::apiResource('trucking-companies', AdminTruckingCompanyController::class);
    Route::apiResource('trucking-hubs', AdminTruckingHubController::class);
    Route::apiResource('supported-routes', AdminSupportedRouteController::class);
    Route::post('/admin/orders/{order}/reassign', [AdminOrderController::class, 'reassign']);
    Route::post('/admin/orders/{order}/refund', [AdminOrderController::class, 'processRefund']);
});
```

---

## 8. FRONTEND UX

### 8.1 Buyer Journey

```
1. INITIATE DELIVERY
   ├─ Enter seller location (or select from orders)
   ├─ Enter delivery location
   ├─ Package details (weight, dimensions, type)
   └─ Service preferences (standard/express)

2. VIEW QUOTE & OPTIONS
   ├─ Map showing route (origin → hub → hub → destination)
   ├─ Multiple trucking company options with:
   │  ├─ Price comparison
   │  ├─ ETA comparison
   │  ├─ Company rating
   │  └─ Insurance options
   └─ Total price breakdown

3. CONFIRM & PAY
   ├─ Review order summary
   ├─ Select payment method
   ├─ Apply promo code (optional)
   └─ Complete payment

4. TRACKING DASHBOARD
   ├─ Real-time map view
   ├─ Timeline of all legs
   ├─ Current status indicator
   ├─ Rider/Truck details with contact
   ├─ Estimated arrival times
   └─ Download invoice/receipt

5. DELIVERY COMPLETION
   ├─ Delivery proof (photo/signature)
   ├─ Rate experience
   └─ Report issues if any
```

### 8.2 Trucking Company Dashboard

```
DASHBOARD
├── Incoming Packages (at origin hubs)
├── In Transit (current loads)
├── Delivered Today
├── Revenue Summary
└─ Alerts (delays, issues)

OPERATIONS
├── Route Management
│   ├─ View all routes
   ├─ Update pricing
   ├─ Set capacity
   └─ Schedule maintenance
├── Hub Management
│   ├─ Inventory by hub
   ├─ Check-in packages
   ├─ Check-out packages
   └─ Storage capacity
└── Fleet Tracking
    ├─ GPS locations
    ├─ ETA updates
    └─ Driver communication

ANALYTICS
├── Revenue by route
├── Performance metrics
├── Customer ratings
└── Operational efficiency
```

---

## 9. TAGXI CORE MODULE EXTENSIONS

### 9.1 Modified/Extended Modules

| Module | Changes Required |
|--------|-----------------|
| **Request Model** | Add `delivery_type` enum: 'local', 'interstate', 'international' |
| **Bid System** | Extend for interstate route bidding; add trucking company bidding |
| **Pricing Engine** | Add slab-based pricing; multi-leg pricing aggregation |
| **Provider Model** | Create polymorphic relationship for riders + trucking companies |
| **Tracking System** | Support multi-leg tracking; hub inventory tracking |
| **Notification System** | Add interstate-specific events (hub arrival, customs, etc.) |
| **Payment System** | Split payment across multiple providers; delayed payouts |
| **Zone System** | Extend to interstate zones; hub-based territories |

### 9.2 New Services

```
app/Services/InterstateDelivery/
├── RouteSelectionService.php
├── PricingCalculationService.php
├── LegOrchestrationService.php
├── HubInventoryService.php
├── CapacityManagementService.php
└── ProviderMatchingService.php

app/Models/Interstate/
├── TruckingCompany.php
├── TruckingHub.php
├── SupportedRoute.php
├── DeliveryOrder.php
├── DeliveryLeg.php
└── HubInventory.php
```

---

## 10. EDGE CASES & HANDLING

### 10.1 Hub Unavailability

```php
class HubUnavailabilityHandler
{
    public function handle(HubInventory $package): void
    {
        // Find alternative hub from same company
        $alternativeHub = $this->findAlternativeHub($package);
        
        if ($alternativeHub) {
            // Redirect to alternative hub
            $this->redirectPackage($package, $alternativeHub);
            $this->notifyCustomer($package->deliveryOrder, 
                'Package redirected to alternative hub');
        } else {
            // Find alternative trucking company
            $alternativeRoute = $this->routeSelector->findAlternativeRoute($package);
            
            if ($alternativeRoute) {
                $this->reassignToRoute($package, $alternativeRoute);
            } else {
                // Hold at current location, notify admin
                $this->escalateToAdmin($package, 'No alternative hub available');
            }
        }
    }
}
```

### 10.2 Trucking Delay

```php
class DelayHandler
{
    public function handleDelay(DeliveryLeg $leg, int $delayHours): void
    {
        // Update ETAs for all downstream legs
        $affectedLegs = $leg->deliveryOrder->legs()
            ->where('leg_number', '>', $leg->leg_number)
            ->get();
        
        foreach ($affectedLegs as $affectedLeg) {
            $affectedLeg->incrementExpectedTime(hours: $delayHours);
        }
        
        // Update customer expectations
        $order = $leg->deliveryOrder;
        $order->expected_delivery_at = $order->expected_delivery_at->addHours($delayHours);
        $order->save();
        
        // Compensation logic
        if ($delayHours > $order->selectedRoute->standard_sla_hours) {
            $this->applyServiceCredit($order);
        }
        
        // Notifications
        event(new DeliveryDelayed($order, $delayHours, $leg));
    }
}
```

### 10.3 Reassignment Logic

```php
class LegReassignmentService
{
    public function reassignLeg(DeliveryLeg $leg, string $reason): void
    {
        match($leg->provider_type) {
            'dispatch_rider' => $this->reassignRider($leg, $reason),
            'trucking_company' => $this->reassignTrucking($leg, $reason)
        };
    }
    
    private function reassignTrucking(DeliveryLeg $leg, string $reason): void
    {
        $order = $leg->deliveryOrder;
        
        // Find alternative trucking company
        $alternatives = $this->routeSelector->selectOptimalRoute(
            originCity: $order->origin_city,
            destinationCity: $order->destination_city,
            weightKg: $order->weight_kg,
            excludeCompanies: [$leg->provider_id]
        );
        
        if ($alternatives->routes->isEmpty()) {
            // No alternative - full refund and cancellation
            $this->refundAndCancel($order, 'No alternative transport available');
            return;
        }
        
        // Update to new provider
        $newRoute = $alternatives->routes->first();
        $leg->update([
            'provider_id' => $newRoute->trucking_company_id,
            'from_location' => $this->formatHubLocation($newRoute->originHub),
            'to_location' => $this->formatHubLocation($newRoute->destinationHub),
            'base_amount' => $newRoute->calculatePrice($order->weight_kg),
            'status' => 'pending_assignment'
        ]);
        
        // Price adjustment
        $priceDiff = $newRoute->price - $leg->original_price;
        if ($priceDiff > 0) {
            $this->requestAdditionalPayment($order, $priceDiff);
        } elseif ($priceDiff < 0) {
            $this->issueRefund($order, abs($priceDiff));
        }
        
        // Notify customer
        event(new LegReassigned($leg, $newRoute, $reason));
    }
}
```

### 10.4 Refund Policy

```php
class InterstateRefundPolicy
{
    public function calculateRefund(DeliveryOrder $order): RefundCalculation
    {
        $completedLegs = $order->legs()->whereIn('status', ['delivered', 'completed'])->count();
        $totalLegs = $order->legs()->count();
        
        return match(true) {
            // Before pickup: Full refund
            $completedLegs === 0 => new RefundCalculation(
                amount: $order->total_amount,
                type: 'full',
                reason: 'Order cancelled before pickup'
            ),
            
            // After pickup but before interstate: Partial refund (minus local leg)
            $completedLegs === 1 => new RefundCalculation(
                amount: $order->total_amount - $order->local_pickup_fee,
                type: 'partial',
                reason: 'Order cancelled after pickup'
            ),
            
            // After interstate departure: No refund for interstate leg
            $completedLegs >= 2 && $completedLegs < $totalLegs => new RefundCalculation(
                amount: $order->local_delivery_fee, // Only refund undelivered local leg
                type: 'partial',
                reason: 'Order cancelled in transit'
            ),
            
            // Delivered: No refund
            default => new RefundCalculation(
                amount: 0,
                type: 'none',
                reason: 'Order completed'
            )
        };
    }
}
```

---

## 11. DEPLOYMENT NOTES FOR NIGERIA

### 11.1 Regulatory Considerations

- **NIPOST Integration**: For inter-state tracking compliance
- **Customs Documentation**: For cross-border future expansion
- **Waybill Generation**: Auto-generate CMR/e-waybill
- **Insurance Requirements**: Mandatory goods-in-transit insurance

### 11.2 Payment Methods

- Paystack/Flutterwave for card payments
- Bank transfer with verification
- USSD payment option
- Cash on delivery (for local legs only)

### 11.3 Communication

- SMS notifications (Termii, Africa's Talking)
- WhatsApp Business API for rich updates
- In-app push notifications
- IVR for hub inquiries

---

## 12. MONITORING & ALERTS

```yaml
Key Metrics:
  - Hub utilization rate (>80% alert)
  - Route on-time performance (<90% alert)
  - Trucking company acceptance rate (<70% alert)
  - Package dwell time at hub (>24hrs alert)
  - Failed delivery rate (>5% alert)

Dashboards:
  - Real-time leg tracking
  - Hub capacity visualization
  - Route profitability analysis
  - Provider performance ranking
```

---

## SUMMARY

This architecture provides a robust foundation for multi-leg interstate delivery with:

✅ **Modular Design**: Each leg is independently trackable and manageable  
✅ **Scalability**: Supports multiple trucking companies and unlimited hubs  
✅ **Flexibility**: Supports various pricing models and service types  
✅ **Reliability**: Comprehensive error handling and recovery mechanisms  
✅ **Extensibility**: Ready for future transport modes (rail, air, sea)  

The system integrates seamlessly with existing Tagxi modules while adding the complexity required for interstate logistics operations in Nigeria.
