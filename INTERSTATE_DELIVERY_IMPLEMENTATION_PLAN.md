# Interstate Delivery Implementation Plan for Tagxi
## Practical Integration Strategy

---

## EXECUTIVE SUMMARY

This document provides a step-by-step implementation plan for integrating multi-leg interstate delivery into the existing Tagxi platform. The approach leverages existing Tagxi modules (Request, Zone, ServiceLocation, Bidding) while adding new components for interstate logistics.

---

## 1. ANALYSIS OF CURRENT TAGXI ARCHITECTURE

### 1.1 Existing Key Components

```
┌─────────────────────────────────────────────────────────────┐
│                    CURRENT TAGXI SYSTEM                      │
├─────────────────────────────────────────────────────────────┤
│  REQUEST MODEL                                               │
│  ├── request_number (UUID)                                   │
│  ├── user_id, driver_id, zone_type_id                       │
│  ├── is_later, is_out_station, is_rental                   │
│  ├── requestPlace (pick_lat, pick_lng, drop_lat, drop_lng) │
│  ├── requestBill (pricing)                                  │
│  ├── requestMeta (additional data)                         │
│  └── status (pending, accepted, started, completed...)     │
├─────────────────────────────────────────────────────────────┤
│  ZONE SYSTEM                                                 │
│  ├── Zone (geographic areas)                                │
│  ├── ZoneType (vehicle types per zone)                     │
│  ├── ZoneTypePrice (pricing per zone/type)                │
│  └── ServiceLocation (cities with currency/timezone)      │
├─────────────────────────────────────────────────────────────┤
│  BIDDING SYSTEM                                              │
│  ├── is_bid_ride flag in Request                          │
│  ├── offered_ride_fare, accepted_ride_fare               │
│  └── Driver can bid on requests                            │
├─────────────────────────────────────────────────────────────┤
│  DELIVERY FEATURES                                           │
│  ├── transport_type ('taxi', 'delivery')                   │
│  ├── goods_type_id, goods_type_quantity                   │
│  └── requestStops (multi-stop support)                    │
└─────────────────────────────────────────────────────────────┘
```

### 1.2 Integration Strategy

Instead of creating a completely parallel system, we will:
1. **Extend the existing Request model** with interstate fields
2. **Leverage Zone system** for hub management
3. **Use existing bidding** for local legs
4. **Add new tables** for trucking-specific data
5. **Create delivery legs** as child records to Request

---

## 2. DATABASE MODIFICATIONS

### 2.1 Extend Existing `requests` Table

```php
// Migration: 2025_02_10_000003_add_interstate_to_requests.php
Schema::table('requests', function (Blueprint $table) {
    // New delivery type flag
    $table->enum('delivery_mode', ['local', 'interstate', 'international'])
          ->default('local')
          ->after('transport_type');
    
    // Interstate-specific fields (nullable for backward compatibility)
    $table->foreignId('trucking_company_id')->nullable()->constrained('trucking_companies');
    $table->foreignId('origin_hub_id')->nullable()->constrained('trucking_hubs');
    $table->foreignId('destination_hub_id')->nullable()->constrained('trucking_hubs');
    $table->foreignId('supported_route_id')->nullable()->constrained('supported_routes');
    
    // Multi-leg tracking
    $table->integer('current_leg_number')->default(1);
    $table->integer('total_legs')->default(1);
    
    // Financial breakdown
    $table->decimal('local_pickup_fee', 10, 2)->nullable();
    $table->decimal('interstate_transport_fee', 10, 2)->nullable();
    $table->decimal('local_delivery_fee', 10, 2)->nullable();
    $table->decimal('hub_handling_fee', 10, 2)->nullable();
    
    // Hub schedule
    $table->timestamp('expected_hub_arrival')->nullable();
    $table->timestamp('actual_hub_arrival')->nullable();
    $table->timestamp('expected_hub_departure')->nullable();
    $table->timestamp('actual_hub_departure')->nullable();
    
    // Indexes
    $table->index(['delivery_mode', 'status']);
    $table->index(['trucking_company_id', 'status']);
});
```

### 2.2 Create New Tables (Simplified from Architecture)

```php
// Migration: 2025_02_10_000004_create_interstate_tables.php

// 1. TRUCKING COMPANIES (Admin-managed like Owners)
Schema::create('trucking_companies', function (Blueprint $table) {
    $table->id();
    $table->string('company_name');
    $table->string('slug')->unique();
    $table->string('registration_number')->unique();
    $table->string('email');
    $table->string('phone');
    $table->foreignId('user_id')->constrained('users'); // Login account
    $table->string('logo')->nullable();
    $table->enum('status', ['pending', 'active', 'suspended'])->default('pending');
    $table->decimal('commission_rate', 5, 2)->default(15.00);
    $table->integer('fleet_size')->default(0);
    $table->json('service_types')->nullable(); // ['general', 'perishable', 'hazmat']
    $table->json('operating_states')->nullable(); // ['Lagos', 'Ogun', 'Oyo']
    $table->decimal('rating', 2, 1)->default(5.0);
    $table->timestamps();
    $table->softDeletes();
});

// 2. TRUCKING HUBS (Physical locations - extends Zone concept)
Schema::create('trucking_hubs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('trucking_company_id')->constrained()->onDelete('cascade');
    $table->string('hub_name');
    $table->string('hub_code')->unique();
    $table->enum('hub_type', ['origin', 'destination', 'both', 'transit'])->default('both');
    $table->text('address');
    $table->string('city');
    $table->string('state');
    $table->decimal('latitude', 10, 8);
    $table->decimal('longitude', 11, 8);
    $table->string('phone');
    $table->json('operating_hours')->nullable();
    $table->integer('daily_capacity')->default(100);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    
    $table->index(['city', 'state']);
    $table->index(['latitude', 'longitude']);
});

// 3. SUPPORTED ROUTES (Hub-to-hub connections with pricing)
Schema::create('supported_routes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('trucking_company_id')->constrained()->onDelete('cascade');
    $table->foreignId('origin_hub_id')->constrained('trucking_hubs');
    $table->foreignId('destination_hub_id')->constrained('trucking_hubs');
    $table->string('route_code')->unique();
    $table->decimal('distance_km', 10, 2);
    $table->integer('estimated_duration_hours');
    
    // Pricing
    $table->enum('pricing_model', ['fixed', 'slab_weight', 'slab_distance'])->default('fixed');
    $table->decimal('base_price', 12, 2)->default(0);
    $table->decimal('price_per_kg', 8, 2)->default(0);
    $table->decimal('min_charge', 10, 2)->default(2000);
    $table->json('weight_slabs')->nullable(); // [{"min":0,"max":10,"price":3000}]
    
    // Capacity
    $table->integer('max_daily_capacity')->default(50);
    $table->json('departure_slots')->nullable(); // ["08:00", "14:00", "20:00"]
    
    // SLA
    $table->integer('standard_sla_hours')->default(72);
    $table->integer('express_sla_hours')->default(48);
    
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    
    $table->unique(['trucking_company_id', 'origin_hub_id', 'destination_hub_id']);
});

// 4. REQUEST LEGS (Child table for multi-leg tracking)
Schema::create('request_legs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('request_id')->constrained('requests')->onDelete('cascade');
    $table->integer('leg_number'); // 1, 2, 3, 4, 5
    $table->enum('leg_type', [
        'local_pickup',      // Rider: Seller → Origin Hub
        'hub_dropoff',       // Handoff at Origin Hub
        'interstate_transport', // Trucking: Origin Hub → Dest Hub
        'hub_pickup',        // Handoff at Dest Hub
        'local_delivery'     // Rider: Dest Hub → Buyer
    ]);
    
    // Provider assignment
    $table->morphs('provider'); // Can be Driver or TruckingCompany
    $table->string('provider_name')->nullable();
    $table->string('provider_phone')->nullable();
    
    // Locations (JSON for flexibility)
    $table->json('pickup_location'); // {address, lat, lng, hub_id?}
    $table->json('drop_location');
    
    // Financial
    $table->decimal('base_fare', 10, 2);
    $table->decimal('final_fare', 10, 2);
    $table->decimal('provider_earnings', 10, 2);
    
    // Status (matches existing Request status flow)
    $table->enum('status', [
        'pending',
        'accepted',
        'driver_arrived',
        'picked_up',
        'in_transit',
        'completed',
        'cancelled'
    ])->default('pending');
    
    // Timestamps
    $table->timestamp('accepted_at')->nullable();
    $table->timestamp('picked_up_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    
    // Tracking
    $table->decimal('current_lat', 10, 8)->nullable();
    $table->decimal('current_lng', 11, 8)->nullable();
    
    // Proof
    $table->json('pickup_proof')->nullable(); // {photo, signature, otp}
    $table->json('delivery_proof')->nullable();
    
    $table->timestamps();
    
    $table->unique(['request_id', 'leg_number']);
    $table->index(['provider_type', 'provider_id', 'status']);
});

// 5. HUB INVENTORY (Track packages at hubs)
Schema::create('hub_inventory', function (Blueprint $table) {
    $table->id();
    $table->foreignId('hub_id')->constrained('trucking_hubs');
    $table->foreignId('request_id')->constrained('requests');
    $table->foreignId('request_leg_id')->constrained('request_legs');
    $table->string('storage_location')->nullable(); // Rack/shelf
    $table->enum('status', ['received', 'stored', 'ready', 'dispatched'])->default('received');
    $table->timestamp('received_at');
    $table->timestamp('dispatched_at')->nullable();
    $table->foreignId('received_by')->nullable()->constrained('users');
    $table->foreignId('dispatched_by')->nullable()->constrained('users');
    $table->timestamps();
});
```

---

## 3. MODEL RELATIONSHIPS

### 3.1 Extend Existing Request Model

```php
// app/Models/Request/Request.php

class Request extends Model
{
    // ... existing code ...
    
    // NEW: Interstate relationships
    public function truckingCompany()
    {
        return $this->belongsTo(TruckingCompany::class);
    }
    
    public function originHub()
    {
        return $this->belongsTo(TruckingHub::class, 'origin_hub_id');
    }
    
    public function destinationHub()
    {
        return $this->belongsTo(TruckingHub::class, 'destination_hub_id');
    }
    
    public function supportedRoute()
    {
        return $this->belongsTo(SupportedRoute::class);
    }
    
    public function legs()
    {
        return $this->hasMany(RequestLeg::class)->orderBy('leg_number');
    }
    
    public function currentLeg()
    {
        return $this->hasOne(RequestLeg::class)
            ->where('leg_number', $this->current_leg_number);
    }
    
    // NEW: Scopes
    public function scopeInterstate($query)
    {
        return $query->where('delivery_mode', 'interstate');
    }
    
    public function scopeLocal($query)
    {
        return $query->where('delivery_mode', 'local');
    }
    
    // NEW: Helper methods
    public function isInterstate(): bool
    {
        return $this->delivery_mode === 'interstate';
    }
    
    public function isMultiLeg(): bool
    {
        return $this->total_legs > 1;
    }
    
    public function getCurrentLegProvider()
    {
        $leg = $this->currentLeg;
        return $leg ? $leg->provider : null;
    }
}
```

### 3.2 New Models

```php
// app/Models/Interstate/TruckingCompany.php

namespace App\Models\Interstate;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class TruckingCompany extends Model
{
    protected $fillable = [
        'company_name', 'slug', 'registration_number', 'email', 'phone',
        'user_id', 'logo', 'status', 'commission_rate', 'fleet_size',
        'service_types', 'operating_states', 'rating'
    ];
    
    protected $casts = [
        'service_types' => 'array',
        'operating_states' => 'array',
        'commission_rate' => 'decimal:2',
        'rating' => 'decimal:1'
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function hubs()
    {
        return $this->hasMany(TruckingHub::class);
    }
    
    public function routes()
    {
        return $this->hasMany(SupportedRoute::class);
    }
    
    public function activeHubs()
    {
        return $this->hubs()->where('is_active', true);
    }
    
    public function activeRoutes()
    {
        return $this->routes()->where('is_active', true);
    }
}

// app/Models/Interstate/RequestLeg.php

namespace App\Models\Interstate;

use Illuminate\Database\Eloquent\Model;
use App\Models\Request\Request;

class RequestLeg extends Model
{
    protected $fillable = [
        'request_id', 'leg_number', 'leg_type', 'provider_type', 'provider_id',
        'provider_name', 'provider_phone', 'pickup_location', 'drop_location',
        'base_fare', 'final_fare', 'provider_earnings', 'status',
        'accepted_at', 'picked_up_at', 'completed_at', 'current_lat', 'current_lng',
        'pickup_proof', 'delivery_proof'
    ];
    
    protected $casts = [
        'pickup_location' => 'array',
        'drop_location' => 'array',
        'pickup_proof' => 'array',
        'delivery_proof' => 'array',
        'base_fare' => 'decimal:2',
        'final_fare' => 'decimal:2',
        'provider_earnings' => 'decimal:2'
    ];
    
    public function request()
    {
        return $this->belongsTo(Request::class);
    }
    
    public function provider()
    {
        return $this->morphTo();
    }
    
    // Get next leg
    public function nextLeg()
    {
        return $this->hasOne(static::class, 'request_id', 'request_id')
            ->where('leg_number', $this->leg_number + 1);
    }
    
    // Get previous leg
    public function previousLeg()
    {
        return $this->hasOne(static::class, 'request_id', 'request_id')
            ->where('leg_number', $this->leg_number - 1);
    }
    
    // Scope for active legs
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['completed', 'cancelled']);
    }
}
```

---

## 4. SERVICE LAYER IMPLEMENTATION

### 4.1 Route Selection Service

```php
// app/Services/Interstate/RouteSelectionService.php

namespace App\Services\Interstate;

use App\Models\Interstate\TruckingHub;
use App\Models\Interstate\SupportedRoute;

class RouteSelectionService
{
    /**
     * Find optimal interstate route between two cities
     */
    public function findOptimalRoute(
        string $originCity,
        string $destinationCity,
        float $weightKg,
        string $serviceType = 'standard'
    ): RouteRecommendation {
        
        // 1. Find origin hubs near pickup city
        $originHubs = TruckingHub::where('city', 'LIKE', "%{$originCity}%")
            ->where('hub_type', '!=', 'destination')
            ->where('is_active', true)
            ->get();
        
        // 2. Find destination hubs near delivery city
        $destHubs = TruckingHub::where('city', 'LIKE', "%{$destinationCity}%")
            ->where('hub_type', '!=', 'origin')
            ->where('is_active', true)
            ->get();
        
        if ($originHubs->isEmpty() || $destHubs->isEmpty()) {
            throw new \Exception('No interstate service available for this route');
        }
        
        // 3. Find routes connecting hubs
        $routes = SupportedRoute::whereIn('origin_hub_id', $originHubs->pluck('id'))
            ->whereIn('destination_hub_id', $destHubs->pluck('id'))
            ->where('is_active', true)
            ->with(['truckingCompany', 'originHub', 'destinationHub'])
            ->get();
        
        if ($routes->isEmpty()) {
            throw new \Exception('No trucking companies serve this route');
        }
        
        // 4. Calculate price for each route
        $scoredRoutes = $routes->map(function ($route) use ($weightKg, $serviceType) {
            $price = $this->calculateRoutePrice($route, $weightKg, $serviceType);
            
            // Score based on price (40%), speed (30%), rating (30%)
            $priceScore = max(0, 100 - ($price / 100)); // Lower price = higher score
            $speedScore = $serviceType === 'express' 
                ? (100 - ($route->express_sla_hours / 72 * 100))
                : (100 - ($route->standard_sla_hours / 72 * 100));
            $ratingScore = $route->truckingCompany->rating * 20;
            
            $totalScore = ($priceScore * 0.4) + ($speedScore * 0.3) + ($ratingScore * 0.3);
            
            return [
                'route' => $route,
                'price' => $price,
                'score' => $totalScore,
                'eta_hours' => $serviceType === 'express' 
                    ? $route->express_sla_hours 
                    : $route->standard_sla_hours
            ];
        })->sortByDesc('score');
        
        return new RouteRecommendation(
            bestRoute: $scoredRoutes->first(),
            allOptions: $scoredRoutes->take(3)
        );
    }
    
    private function calculateRoutePrice(SupportedRoute $route, float $weightKg, string $serviceType): float
    {
        $price = match($route->pricing_model) {
            'fixed' => $route->base_price,
            
            'slab_weight' => $this->calculateSlabPrice($route->weight_slabs, $weightKg),
            
            default => $route->base_price + ($weightKg * $route->price_per_kg)
        };
        
        // Apply min charge
        $price = max($price, $route->min_charge);
        
        // Apply express surcharge
        if ($serviceType === 'express') {
            $price *= 1.5; // 50% surcharge
        }
        
        return $price;
    }
    
    private function calculateSlabPrice(?array $slabs, float $weight): float
    {
        if (!$slabs) return 0;
        
        foreach ($slabs as $slab) {
            if ($weight >= $slab['min'] && $weight <= $slab['max']) {
                return $slab['price'];
            }
        }
        
        // Return highest slab if over max
        return $slabs[count($slabs) - 1]['price'] ?? 0;
    }
}

// DTO for route recommendation
class RouteRecommendation
{
    public function __construct(
        public array $bestRoute,
        public \Illuminate\Support\Collection $allOptions
    ) {}
}
```

### 4.2 Request Creation Service

```php
// app/Services/Interstate/InterstateRequestService.php

namespace App\Services\Interstate;

use App\Models\Request\Request;
use App\Models\Interstate\RequestLeg;
use App\Models\Admin\Driver;
use Illuminate\Support\Facades\DB;

class InterstateRequestService
{
    public function __construct(
        private RouteSelectionService $routeSelector,
        private LocalPricingService $localPricing
    ) {}
    
    /**
     * Create interstate delivery request with multiple legs
     */
    public function createInterstateRequest(array $data): Request
    {
        return DB::transaction(function () use ($data) {
            
            // 1. Find optimal interstate route
            $routeRec = $this->routeSelector->findOptimalRoute(
                originCity: $data['pickup_city'],
                destinationCity: $data['drop_city'],
                weightKg: $data['weight_kg'],
                serviceType: $data['service_type'] ?? 'standard'
            );
            
            $bestRoute = $routeRec['route'];
            $interstatePrice = $routeRec['price'];
            
            // 2. Calculate local leg prices using existing Tagxi bidding system
            $localPickupPrice = $this->localPricing->estimateLocalDelivery(
                lat: $data['pick_lat'],
                lng: $data['pick_lng'],
                destLat: $bestRoute->originHub->latitude,
                destLng: $bestRoute->originHub->longitude
            );
            
            $localDeliveryPrice = $this->localPricing->estimateLocalDelivery(
                lat: $bestRoute->destinationHub->latitude,
                lng: $bestRoute->destinationHub->longitude,
                destLat: $data['drop_lat'],
                destLng: $data['drop_lng']
            );
            
            // 3. Calculate total
            $subtotal = $localPickupPrice + $interstatePrice + $localDeliveryPrice;
            $vat = $subtotal * 0.075; // Nigeria VAT
            $total = $subtotal + $vat;
            
            // 4. Create main request record (extends existing Request model)
            $request = Request::create([
                'request_number' => $this->generateRequestNumber(),
                'delivery_mode' => 'interstate',
                'user_id' => $data['user_id'],
                
                // Use existing request_place for pickup/drop
                'transport_type' => 'delivery',
                'is_bid_ride' => true, // Enable bidding for local legs
                
                // Interstate specific
                'trucking_company_id' => $bestRoute->trucking_company_id,
                'origin_hub_id' => $bestRoute->origin_hub_id,
                'destination_hub_id' => $bestRoute->destination_hub_id,
                'supported_route_id' => $bestRoute->id,
                'total_legs' => 5,
                'current_leg_number' => 1,
                
                // Pricing breakdown
                'local_pickup_fee' => $localPickupPrice,
                'interstate_transport_fee' => $interstatePrice,
                'local_delivery_fee' => $localDeliveryPrice,
                
                // Total (will be used for billing)
                'request_eta_amount' => $total,
                
                // Status
                'status' => 'pending', // Will trigger bidding for leg 1
                
                // Service location (use pickup location's service area)
                'service_location_id' => $this->getServiceLocationId($data['pick_lat'], $data['pick_lng']),
            ]);
            
            // 5. Create request place (existing Tagxi functionality)
            $request->requestPlace()->create([
                'pick_lat' => $data['pick_lat'],
                'pick_lng' => $data['pick_lng'],
                'pick_address' => $data['pick_address'],
                'drop_lat' => $data['drop_lat'],
                'drop_lng' => $data['drop_lng'],
                'drop_address' => $data['drop_address'],
            ]);
            
            // 6. Create delivery legs
            $this->createDeliveryLegs($request, $bestRoute, $data, $localPickupPrice, $localDeliveryPrice);
            
            // 7. Emit event for leg 1 bidding
            event(new InterstateRequestCreated($request));
            
            return $request;
        });
    }
    
    private function createDeliveryLegs(
        Request $request,
        $route,
        array $data,
        float $pickupPrice,
        float $deliveryPrice
    ): void {
        $legs = [
            [
                'leg_number' => 1,
                'leg_type' => 'local_pickup',
                'provider_type' => 'driver', // Will be assigned via bidding
                'pickup_location' => [
                    'address' => $data['pick_address'],
                    'lat' => $data['pick_lat'],
                    'lng' => $data['pick_lng']
                ],
                'drop_location' => [
                    'address' => $route->originHub->address,
                    'lat' => $route->originHub->latitude,
                    'lng' => $route->originHub->longitude,
                    'hub_id' => $route->origin_hub_id,
                    'hub_name' => $route->originHub->hub_name
                ],
                'base_fare' => $pickupPrice,
                'final_fare' => $pickupPrice,
                'status' => 'pending' // Will trigger bidding
            ],
            [
                'leg_number' => 2,
                'leg_type' => 'hub_dropoff',
                'provider_type' => 'driver', // Same as leg 1
                'pickup_location' => [
                    'address' => $route->originHub->address,
                    'lat' => $route->originHub->latitude,
                    'lng' => $route->originHub->longitude,
                    'hub_id' => $route->origin_hub_id
                ],
                'drop_location' => [
                    'address' => $route->originHub->address,
                    'lat' => $route->originHub->latitude,
                    'lng' => $route->originHub->longitude,
                    'hub_id' => $route->origin_hub_id
                ],
                'base_fare' => 0, // Included in leg 1
                'final_fare' => 0,
                'status' => 'pending'
            ],
            [
                'leg_number' => 3,
                'leg_type' => 'interstate_transport',
                'provider_type' => 'trucking_company',
                'provider_id' => $route->trucking_company_id,
                'provider_name' => $route->truckingCompany->company_name,
                'provider_phone' => $route->truckingCompany->phone,
                'pickup_location' => [
                    'address' => $route->originHub->address,
                    'lat' => $route->originHub->latitude,
                    'lng' => $route->originHub->longitude,
                    'hub_id' => $route->origin_hub_id,
                    'hub_name' => $route->originHub->hub_name
                ],
                'drop_location' => [
                    'address' => $route->destinationHub->address,
                    'lat' => $route->destinationHub->latitude,
                    'lng' => $route->destinationHub->longitude,
                    'hub_id' => $route->destination_hub_id,
                    'hub_name' => $route->destinationHub->hub_name
                ],
                'base_fare' => $request->interstate_transport_fee,
                'final_fare' => $request->interstate_transport_fee,
                'provider_earnings' => $request->interstate_transport_fee * (1 - ($route->truckingCompany->commission_rate / 100)),
                'status' => 'pending'
            ],
            [
                'leg_number' => 4,
                'leg_type' => 'hub_pickup',
                'provider_type' => 'driver',
                'pickup_location' => [
                    'address' => $route->destinationHub->address,
                    'lat' => $route->destinationHub->latitude,
                    'lng' => $route->destinationHub->longitude,
                    'hub_id' => $route->destination_hub_id
                ],
                'drop_location' => [
                    'address' => $route->destinationHub->address,
                    'lat' => $route->destinationHub->latitude,
                    'lng' => $route->destinationHub->longitude,
                    'hub_id' => $route->destination_hub_id
                ],
                'base_fare' => 0, // Included in leg 5
                'final_fare' => 0,
                'status' => 'pending'
            ],
            [
                'leg_number' => 5,
                'leg_type' => 'local_delivery',
                'provider_type' => 'driver',
                'pickup_location' => [
                    'address' => $route->destinationHub->address,
                    'lat' => $route->destinationHub->latitude,
                    'lng' => $route->destinationHub->longitude,
                    'hub_id' => $route->destination_hub_id,
                    'hub_name' => $route->destinationHub->hub_name
                ],
                'drop_location' => [
                    'address' => $data['drop_address'],
                    'lat' => $data['drop_lat'],
                    'lng' => $data['drop_lng']
                ],
                'base_fare' => $deliveryPrice,
                'final_fare' => $deliveryPrice,
                'status' => 'pending' // Will trigger bidding when leg 3 completes
            ]
        ];
        
        foreach ($legs as $legData) {
            RequestLeg::create(array_merge(['request_id' => $request->id], $legData));
        }
    }
    
    private function generateRequestNumber(): string
    {
        return 'INT-' . date('Y') . '-' . strtoupper(uniqid());
    }
    
    private function getServiceLocationId(float $lat, float $lng): ?int
    {
        // Use existing Tagxi zone detection logic
        $zone = \App\Models\Admin\Zone::containsPoint($lat, $lng)->first();
        return $zone?->service_location_id;
    }
}
```

### 4.3 Leg Orchestration Service

```php
// app/Services/Interstate/LegOrchestrationService.php

namespace App\Services\Interstate;

use App\Models\Request\Request;
use App\Models\Interstate\RequestLeg;
use App\Models\Interstate\HubInventory;
use App\Events\Interstate\LegCompleted;
use App\Events\Interstate\NextLegTriggered;

class LegOrchestrationService
{
    /**
     * Handle leg completion and trigger next leg
     */
    public function completeLeg(RequestLeg $leg, array $proofData = []): void
    {
        DB::transaction(function () use ($leg, $proofData) {
            
            // 1. Update leg status
            $leg->update([
                'status' => 'completed',
                'completed_at' => now(),
                'delivery_proof' => $proofData
            ]);
            
            $request = $leg->request;
            
            // 2. Handle leg-specific actions
            match($leg->leg_type) {
                'local_pickup' => $this->onPickupComplete($leg, $request),
                'hub_dropoff' => $this->onHubDropoff($leg, $request),
                'interstate_transport' => $this->onInterstateComplete($leg, $request),
                'hub_pickup' => $this->onHubPickup($leg, $request),
                'local_delivery' => $this->onDeliveryComplete($leg, $request),
            };
            
            // 3. Trigger next leg if exists
            $nextLeg = $request->legs()
                ->where('leg_number', $leg->leg_number + 1)
                ->first();
            
            if ($nextLeg) {
                $this->triggerNextLeg($nextLeg, $leg);
            } else {
                // All legs complete - mark request as complete
                $request->update([
                    'status' => 'completed',
                    'is_completed' => true,
                    'completed_at' => now()
                ]);
            }
            
            // 4. Emit event
            event(new LegCompleted($leg));
        });
    }
    
    private function onPickupComplete(RequestLeg $leg, Request $request): void
    {
        // Update request status
        $request->update(['status' => 'picked_up']);
    }
    
    private function onHubDropoff(RequestLeg $leg, Request $request): void
    {
        // Create hub inventory record
        HubInventory::create([
            'hub_id' => $leg->drop_location['hub_id'],
            'request_id' => $request->id,
            'request_leg_id' => $leg->id,
            'status' => 'received',
            'received_at' => now()
        ]);
        
        $request->update([
            'status' => 'hub_arrived',
            'actual_hub_arrival' => now()
        ]);
    }
    
    private function onInterstateComplete(RequestLeg $leg, Request $request): void
    {
        // Update hub inventory
        HubInventory::where('request_id', $request->id)
            ->where('hub_id', $leg->pickup_location['hub_id'])
            ->update([
                'status' => 'dispatched',
                'dispatched_at' => now()
            ]);
        
        // Create new inventory record at destination
        HubInventory::create([
            'hub_id' => $leg->drop_location['hub_id'],
            'request_id' => $request->id,
            'request_leg_id' => $leg->id,
            'status' => 'received',
            'received_at' => now()
        ]);
        
        $request->update([
            'status' => 'hub_departed',
            'actual_hub_departure' => now(),
            'current_leg_number' => 4
        ]);
    }
    
    private function onHubPickup(RequestLeg $leg, Request $request): void
    {
        $request->update([
            'status' => 'out_for_delivery',
            'current_leg_number' => 5
        ]);
    }
    
    private function onDeliveryComplete(RequestLeg $leg, Request $request): void
    {
        // Final delivery - will be handled by main complete flow
    }
    
    private function triggerNextLeg(RequestLeg $nextLeg, RequestLeg $completedLeg): void
    {
        // Update current leg number
        $nextLeg->request->update(['current_leg_number' => $nextLeg->leg_number]);
        
        // Activate leg based on type
        match($nextLeg->leg_type) {
            'hub_dropoff' => $this->activateHubDropoff($nextLeg, $completedLeg),
            'interstate_transport' => $this->activateInterstate($nextLeg),
            'hub_pickup' => $this->activateHubPickup($nextLeg),
            'local_delivery' => $this->activateLocalDelivery($nextLeg),
            default => null
        };
        
        event(new NextLegTriggered($nextLeg));
    }
    
    private function activateLocalDelivery(RequestLeg $leg): void
    {
        // Trigger bidding for local delivery leg
        $leg->update(['status' => 'pending']);
        
        // Use existing Tagxi bidding system
        event(new \App\Events\Request\RequestCreatedForBidding($leg->request, $leg));
    }
    
    private function activateInterstate(RequestLeg $leg): void
    {
        // Notify trucking company
        $leg->update(['status' => 'accepted']);
        
        // Send notification to trucking company
        // This could be via email, SMS, or their dashboard
    }
}
```

---

## 5. API CONTROLLERS

### 5.1 Interstate Request Controller

```php
// app/Http/Controllers/Api/V1/Interstate/InterstateRequestController.php

namespace App\Http\Controllers\Api\V1\Interstate;

use App\Http\Controllers\Api\V1\BaseController;
use App\Services\Interstate\InterstateRequestService;
use App\Services\Interstate\RouteSelectionService;
use App\Models\Request\Request;
use Illuminate\Http\Request as HttpRequest;

class InterstateRequestController extends BaseController
{
    public function __construct(
        private InterstateRequestService $requestService,
        private RouteSelectionService $routeSelector
    ) {}
    
    /**
     * Get quote for interstate delivery
     */
    public function getQuote(HttpRequest $request)
    {
        $validated = $request->validate([
            'pickup_city' => 'required|string',
            'drop_city' => 'required|string',
            'pick_lat' => 'required|numeric',
            'pick_lng' => 'required|numeric',
            'drop_lat' => 'required|numeric',
            'drop_lng' => 'required|numeric',
            'weight_kg' => 'required|numeric|min:0.1',
            'service_type' => 'in:standard,express'
        ]);
        
        try {
            $routeRec = $this->routeSelector->findOptimalRoute(
                originCity: $validated['pickup_city'],
                destinationCity: $validated['drop_city'],
                weightKg: $validated['weight_kg'],
                serviceType: $validated['service_type'] ?? 'standard'
            );
            
            return $this->respondSuccess([
                'recommended' => [
                    'trucking_company' => [
                        'id' => $routeRec['bestRoute']['route']->trucking_company_id,
                        'name' => $routeRec['bestRoute']['route']->truckingCompany->company_name,
                        'rating' => $routeRec['bestRoute']['route']->truckingCompany->rating
                    ],
                    'route' => [
                        'origin_hub' => $routeRec['bestRoute']['route']->originHub->hub_name,
                        'destination_hub' => $routeRec['bestRoute']['route']->destinationHub->hub_name,
                        'distance_km' => $routeRec['bestRoute']['route']->distance_km,
                        'eta_hours' => $routeRec['bestRoute']['eta_hours']
                    ],
                    'pricing' => [
                        'interstate_fee' => $routeRec['bestRoute']['price'],
                        'total_estimate' => $routeRec['bestRoute']['price'] * 1.3 // Rough estimate with local legs
                    ]
                ],
                'alternatives' => $routeRec['allOptions']->slice(1)->values()
            ]);
            
        } catch (\Exception $e) {
            return $this->respondError($e->getMessage());
        }
    }
    
    /**
     * Create interstate delivery request
     */
    public function createRequest(HttpRequest $request)
    {
        $validated = $request->validate([
            'pick_address' => 'required|string',
            'pick_lat' => 'required|numeric',
            'pick_lng' => 'required|numeric',
            'pickup_city' => 'required|string',
            'drop_address' => 'required|string',
            'drop_lat' => 'required|numeric',
            'drop_lng' => 'required|numeric',
            'drop_city' => 'required|string',
            'weight_kg' => 'required|numeric|min:0.1',
            'package_description' => 'nullable|string',
            'service_type' => 'in:standard,express',
            'goods_type_id' => 'nullable|exists:goods_types,id'
        ]);
        
        $validated['user_id'] = auth()->id();
        
        try {
            $interstateRequest = $this->requestService->createInterstateRequest($validated);
            
            return $this->respondSuccess([
                'request_id' => $interstateRequest->id,
                'request_number' => $interstateRequest->request_number,
                'tracking_number' => $interstateRequest->request_number,
                'total_amount' => $interstateRequest->request_eta_amount,
                'status' => $interstateRequest->status,
                'legs' => $interstateRequest->legs->map(fn($leg) => [
                    'leg_number' => $leg->leg_number,
                    'leg_type' => $leg->leg_type,
                    'status' => $leg->status,
                    'from' => $leg->pickup_location['address'] ?? 'Unknown',
                    'to' => $leg->drop_location['address'] ?? 'Unknown'
                ])
            ], 'Interstate delivery request created successfully');
            
        } catch (\Exception $e) {
            return $this->respondError($e->getMessage());
        }
    }
    
    /**
     * Get request tracking details
     */
    public function getTracking(string $requestNumber)
    {
        $request = Request::with(['legs', 'truckingCompany', 'originHub', 'destinationHub'])
            ->where('request_number', $requestNumber)
            ->where('delivery_mode', 'interstate')
            ->firstOrFail();
        
        return $this->respondSuccess([
            'request' => [
                'number' => $request->request_number,
                'status' => $request->status,
                'total_amount' => $request->request_eta_amount,
                'created_at' => $request->created_at
            ],
            'trucking_company' => $request->truckingCompany ? [
                'name' => $request->truckingCompany->company_name,
                'phone' => $request->truckingCompany->phone
            ] : null,
            'legs' => $request->legs->map(fn($leg) => [
                'leg_number' => $leg->leg_number,
                'leg_type' => $leg->leg_type,
                'status' => $leg->status,
                'provider' => $leg->provider ? [
                    'name' => $leg->provider_name,
                    'phone' => $leg->provider_phone
                ] : null,
                'from' => $leg->pickup_location,
                'to' => $leg->drop_location,
                'completed_at' => $leg->completed_at
            ]),
            'current_leg' => $request->current_leg_number,
            'progress_percentage' => ($request->current_leg_number / $request->total_legs) * 100
        ]);
    }
    
    /**
     * Get user's interstate requests
     */
    public function getUserRequests()
    {
        $requests = Request::with(['legs', 'truckingCompany'])
            ->where('user_id', auth()->id())
            ->where('delivery_mode', 'interstate')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        return $this->respondSuccess($requests);
    }
}
```

### 5.2 Trucking Company Controller

```php
// app/Http/Controllers/Api/V1/Interstate/TruckingCompanyController.php

namespace App\Http\Controllers\Api\V1\Interstate;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Interstate\RequestLeg;
use App\Models\Interstate\HubInventory;

class TruckingCompanyController extends BaseController
{
    /**
     * Get incoming packages at origin hubs
     */
    public function getIncomingPackages()
    {
        $company = auth()->user()->truckingCompany;
        
        $packages = RequestLeg::with(['request'])
            ->where('provider_type', 'trucking_company')
            ->where('provider_id', $company->id)
            ->where('leg_type', 'interstate_transport')
            ->where('status', 'pending')
            ->get()
            ->map(fn($leg) => [
                'leg_id' => $leg->id,
                'request_number' => $leg->request->request_number,
                'pickup_hub' => $leg->pickup_location['hub_name'],
                'destination_hub' => $leg->drop_location['hub_name'],
                'package_description' => $leg->request->requestPlace?->pick_address,
                'weight' => 'N/A', // Add to request meta
                'status' => $leg->status
            ]);
        
        return $this->respondSuccess($packages);
    }
    
    /**
     * Accept interstate transport leg
     */
    public function acceptTransport(string $legId)
    {
        $leg = RequestLeg::findOrFail($legId);
        
        // Verify ownership
        if ($leg->provider_id !== auth()->user()->truckingCompany->id) {
            return $this->respondForbidden('Not authorized');
        }
        
        $leg->update([
            'status' => 'accepted',
            'accepted_at' => now()
        ]);
        
        return $this->respondSuccess(null, 'Transport accepted');
    }
    
    /**
     * Mark transport as departed from origin hub
     */
    public function markDeparted(string $legId)
    {
        $leg = RequestLeg::findOrFail($legId);
        
        $leg->update([
            'status' => 'in_transit',
            'picked_up_at' => now()
        ]);
        
        // Update hub inventory
        HubInventory::where('request_id', $leg->request_id)
            ->where('hub_id', $leg->pickup_location['hub_id'])
            ->update([
                'status' => 'dispatched',
                'dispatched_at' => now()
            ]);
        
        return $this->respondSuccess(null, 'Transport departed');
    }
    
    /**
     * Mark transport as arrived at destination hub
     */
    public function markArrived(string $legId)
    {
        $leg = RequestLeg::findOrFail($legId);
        
        $leg->update([
            'status' => 'completed',
            'completed_at' => now()
        ]);
        
        // Trigger next leg
        app(\App\Services\Interstate\LegOrchestrationService::class)
            ->completeLeg($leg);
        
        return $this->respondSuccess(null, 'Transport completed');
    }
    
    /**
     * Get hub inventory
     */
    public function getHubInventory(string $hubId)
    {
        $company = auth()->user()->truckingCompany;
        
        // Verify hub belongs to company
        $hub = $company->hubs()->findOrFail($hubId);
        
        $inventory = HubInventory::with(['request', 'request.user'])
            ->where('hub_id', $hubId)
            ->whereIn('status', ['received', 'stored'])
            ->get()
            ->map(fn($item) => [
                'id' => $item->id,
                'request_number' => $item->request->request_number,
                'customer_name' => $item->request->user->name,
                'received_at' => $item->received_at,
                'storage_location' => $item->storage_location,
                'status' => $item->status
            ]);
        
        return $this->respondSuccess($inventory);
    }
}
```

---

## 6. API ROUTES

```php
// routes/api/v1/interstate.php

use Illuminate\Support\Facades\Route;

Route::prefix('interstate')->namespace('Interstate')->middleware('auth')->group(function () {
    
    // User APIs
    Route::post('/quote', 'InterstateRequestController@getQuote');
    Route::post('/request', 'InterstateRequestController@createRequest');
    Route::get('/requests', 'InterstateRequestController@getUserRequests');
    Route::get('/tracking/{requestNumber}', 'InterstateRequestController@getTracking');
    
    // Trucking Company APIs
    Route::middleware('role:trucking_company')->prefix('trucking')->group(function () {
        Route::get('/incoming', 'TruckingCompanyController@getIncomingPackages');
        Route::post('/legs/{legId}/accept', 'TruckingCompanyController@acceptTransport');
        Route::post('/legs/{legId}/depart', 'TruckingCompanyController@markDeparted');
        Route::post('/legs/{legId}/arrive', 'TruckingCompanyController@markArrived');
        Route::get('/hubs/{hubId}/inventory', 'TruckingCompanyController@getHubInventory');
    });
    
    // Driver APIs (for local legs)
    Route::middleware('role:driver')->prefix('driver')->group(function () {
        Route::get('/assigned-legs', 'DriverLegController@getAssignedLegs');
        Route::post('/legs/{legId}/accept', 'DriverLegController@acceptLeg');
        Route::post('/legs/{legId}/arrived', 'DriverLegController@markArrived');
        Route::post('/legs/{legId}/picked-up', 'DriverLegController@markPickedUp');
        Route::post('/legs/{legId}/complete', 'DriverLegController@markComplete');
    });
});
```

---

## 7. FLUTTER APP INTEGRATION

### 7.1 User App Changes

```dart
// lib/models/interstate_delivery_model.dart

class InterstateDeliveryRequest {
  final String id;
  final String requestNumber;
  final String status;
  final double totalAmount;
  final List<DeliveryLeg> legs;
  final int currentLeg;
  final TruckingCompany? truckingCompany;
  
  InterstateDeliveryRequest({
    required this.id,
    required this.requestNumber,
    required this.status,
    required this.totalAmount,
    required this.legs,
    required this.currentLeg,
    this.truckingCompany,
  });
  
  factory InterstateDeliveryRequest.fromJson(Map<String, dynamic> json) {
    return InterstateDeliveryRequest(
      id: json['request_id'],
      requestNumber: json['request_number'],
      status: json['status'],
      totalAmount: json['total_amount'].toDouble(),
      legs: (json['legs'] as List)
          .map((l) => DeliveryLeg.fromJson(l))
          .toList(),
      currentLeg: json['current_leg'] ?? 1,
      truckingCompany: json['trucking_company'] != null
          ? TruckingCompany.fromJson(json['trucking_company'])
          : null,
    );
  }
}

class DeliveryLeg {
  final int legNumber;
  final String legType;
  final String status;
  final Map<String, dynamic> from;
  final Map<String, dynamic> to;
  final Provider? provider;
  
  DeliveryLeg({
    required this.legNumber,
    required this.legType,
    required this.status,
    required this.from,
    required this.to,
    this.provider,
  });
  
  factory DeliveryLeg.fromJson(Map<String, dynamic> json) {
    return DeliveryLeg(
      legNumber: json['leg_number'],
      legType: json['leg_type'],
      status: json['status'],
      from: json['from'] ?? {},
      to: json['to'] ?? {},
      provider: json['provider'] != null
          ? Provider.fromJson(json['provider'])
          : null,
    );
  }
  
  bool get isCompleted => status == 'completed';
  bool get isCurrent => status != 'completed' && status != 'pending';
  bool get isPending => status == 'pending';
  
  String get displayName {
    switch (legType) {
      case 'local_pickup':
        return 'Pickup from Seller';
      case 'hub_dropoff':
        return 'Drop at Origin Hub';
      case 'interstate_transport':
        return 'Interstate Transport';
      case 'hub_pickup':
        return 'Pickup from Hub';
      case 'local_delivery':
        return 'Delivery to You';
      default:
        return 'Unknown';
    }
  }
}
```

### 7.2 New Screens

```dart
// lib/pages/interstate/interstate_quote_screen.dart

class InterstateQuoteScreen extends StatefulWidget {
  final LatLng pickupLocation;
  final LatLng dropLocation;
  final String pickupAddress;
  final String dropAddress;
  
  const InterstateQuoteScreen({
    Key? key,
    required this.pickupLocation,
    required this.dropLocation,
    required this.pickupAddress,
    required this.dropAddress,
  }) : super(key: key);
  
  @override
  State<InterstateQuoteScreen> createState() => _InterstateQuoteScreenState();
}

class _InterstateQuoteScreenState extends State<InterstateQuoteScreen> {
  final TextEditingController _weightController = TextEditingController();
  String _serviceType = 'standard';
  InterstateQuote? _quote;
  bool _loading = false;
  
  Future<void> _getQuote() async {
    setState(() => _loading = true);
    
    try {
      final response = await ApiService.post('/interstate/quote', data: {
        'pickup_city': await _getCityFromLatLng(widget.pickupLocation),
        'drop_city': await _getCityFromLatLng(widget.dropLocation),
        'pick_lat': widget.pickupLocation.latitude,
        'pick_lng': widget.pickupLocation.longitude,
        'drop_lat': widget.dropLocation.latitude,
        'drop_lng': widget.dropLocation.longitude,
        'weight_kg': double.parse(_weightController.text),
        'service_type': _serviceType,
      });
      
      setState(() {
        _quote = InterstateQuote.fromJson(response['data']);
        _loading = false;
      });
    } catch (e) {
      setState(() => _loading = false);
      showError(e.toString());
    }
  }
  
  Future<void> _createRequest() async {
    try {
      final response = await ApiService.post('/interstate/request', data: {
        'pick_address': widget.pickupAddress,
        'pick_lat': widget.pickupLocation.latitude,
        'pick_lng': widget.pickupLocation.longitude,
        'pickup_city': await _getCityFromLatLng(widget.pickupLocation),
        'drop_address': widget.dropAddress,
        'drop_lat': widget.dropLocation.latitude,
        'drop_lng': widget.dropLocation.longitude,
        'drop_city': await _getCityFromLatLng(widget.dropLocation),
        'weight_kg': double.parse(_weightController.text),
        'service_type': _serviceType,
      });
      
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => InterstateTrackingScreen(
            requestNumber: response['data']['request_number'],
          ),
        ),
      );
    } catch (e) {
      showError(e.toString());
    }
  }
  
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Interstate Delivery Quote')),
      body: SingleChildScrollView(
        padding: EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Route visualization
            _buildRouteCard(),
            
            SizedBox(height: 20),
            
            // Weight input
            TextField(
              controller: _weightController,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(
                labelText: 'Package Weight (kg)',
                suffixText: 'kg',
                border: OutlineInputBorder(),
              ),
            ),
            
            SizedBox(height: 16),
            
            // Service type selection
            Text('Service Type', style: Theme.of(context).textTheme.titleMedium),
            Row(
              children: [
                _buildServiceTypeChip('standard', 'Standard\n(3-5 days)'),
                _buildServiceTypeChip('express', 'Express\n(1-2 days)'),
              ],
            ),
            
            SizedBox(height: 20),
            
            // Get quote button
            ElevatedButton(
              onPressed: _loading ? null : _getQuote,
              child: _loading
                  ? CircularProgressIndicator()
                  : Text('Get Quote'),
            ),
            
            // Quote display
            if (_quote != null) ...[
              SizedBox(height: 20),
              _buildQuoteCard(),
            ],
          ],
        ),
      ),
    );
  }
  
  Widget _buildQuoteCard() {
    return Card(
      child: Padding(
        padding: EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Recommended Route', 
                style: Theme.of(context).textTheme.titleLarge),
            SizedBox(height: 12),
            
            // Trucking company info
            ListTile(
              leading: Icon(Icons.local_shipping),
              title: Text(_quote!.truckingCompany.name),
              subtitle: Row(
                children: [
                  Icon(Icons.star, size: 16, color: Colors.amber),
                  Text('${_quote!.truckingCompany.rating}'),
                ],
              ),
            ),
            
            Divider(),
            
            // Route info
            _buildRouteStep(
              icon: Icons.location_on,
              title: 'Origin Hub',
              subtitle: _quote!.route.originHub,
            ),
            _buildRouteStep(
              icon: Icons.arrow_forward,
              title: '${_quote!.route.distanceKm} km',
              subtitle: '${_quote!.route.etaHours} hours',
              isLine: true,
            ),
            _buildRouteStep(
              icon: Icons.location_on_outlined,
              title: 'Destination Hub',
              subtitle: _quote!.route.destinationHub,
            ),
            
            Divider(),
            
            // Pricing
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('Delivery Fee:'),
                Text('₦${_quote!.pricing.interstateFee.toStringAsFixed(2)}'),
              ],
            ),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('Estimated Total:',
                    style: TextStyle(fontWeight: FontWeight.bold)),
                Text('₦${_quote!.pricing.totalEstimate.toStringAsFixed(2)}',
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 18,
                      color: Theme.of(context).primaryColor,
                    )),
              ],
            ),
            
            SizedBox(height: 16),
            
            // Book button
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: _createRequest,
                style: ElevatedButton.styleFrom(
                  padding: EdgeInsets.symmetric(vertical: 16),
                ),
                child: Text('Book Now', style: TextStyle(fontSize: 16)),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
```

### 7.3 Tracking Screen

```dart
// lib/pages/interstate/interstate_tracking_screen.dart

class InterstateTrackingScreen extends StatefulWidget {
  final String requestNumber;
  
  const InterstateTrackingScreen({
    Key? key,
    required this.requestNumber,
  }) : super(key: key);
  
  @override
  State<InterstateTrackingScreen> createState() => _InterstateTrackingScreenState();
}

class _InterstateTrackingScreenState extends State<InterstateTrackingScreen> {
  InterstateDeliveryRequest? _request;
  bool _loading = true;
  Timer? _refreshTimer;
  
  @override
  void initState() {
    super.initState();
    _loadTracking();
    // Auto-refresh every 30 seconds
    _refreshTimer = Timer.periodic(Duration(seconds: 30), (_) => _loadTracking());
  }
  
  @override
  void dispose() {
    _refreshTimer?.cancel();
    super.dispose();
  }
  
  Future<void> _loadTracking() async {
    try {
      final response = await ApiService.get(
        '/interstate/tracking/${widget.requestNumber}'
      );
      
      setState(() {
        _request = InterstateDeliveryRequest.fromJson(response['data']['request']);
        _loading = false;
      });
    } catch (e) {
      setState(() => _loading = false);
      showError(e.toString());
    }
  }
  
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Delivery Tracking'),
        actions: [
          IconButton(
            icon: Icon(Icons.refresh),
            onPressed: _loadTracking,
          ),
        ],
      ),
      body: _loading
          ? Center(child: CircularProgressIndicator())
          : _request == null
              ? Center(child: Text('Request not found'))
              : _buildTrackingView(),
    );
  }
  
  Widget _buildTrackingView() {
    return Column(
      children: [
        // Progress header
        _buildProgressHeader(),
        
        // Map view (simplified)
        Container(
          height: 200,
          color: Colors.grey[200],
          child: Center(child: Text('Map View - Route Visualization')),
        ),
        
        // Legs timeline
        Expanded(
          child: ListView.builder(
            itemCount: _request!.legs.length,
            itemBuilder: (context, index) {
              final leg = _request!.legs[index];
              return _buildLegTile(leg);
            },
          ),
        ),
        
        // Bottom action bar
        _buildBottomBar(),
      ],
    );
  }
  
  Widget _buildLegTile(DeliveryLeg leg) {
    final isCurrent = leg.legNumber == _request!.currentLeg && !leg.isCompleted;
    final isCompleted = leg.isCompleted;
    final isPending = leg.isPending;
    
    Color statusColor = isCompleted
        ? Colors.green
        : isCurrent
            ? Theme.of(context).primaryColor
            : Colors.grey;
    
    return Card(
      margin: EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      elevation: isCurrent ? 4 : 1,
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: statusColor.withOpacity(0.1),
          child: Icon(
            _getLegIcon(leg.legType),
            color: statusColor,
          ),
        ),
        title: Text(
          leg.displayName,
          style: TextStyle(
            fontWeight: isCurrent ? FontWeight.bold : FontWeight.normal,
          ),
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('From: ${_formatAddress(leg.from)}'),
            Text('To: ${_formatAddress(leg.to)}'),
            if (leg.provider != null)
              Text('Provider: ${leg.provider!.name}'),
          ],
        ),
        trailing: _buildStatusChip(leg.status),
        isThreeLine: true,
      ),
    );
  }
  
  Widget _buildStatusChip(String status) {
    Color color;
    IconData icon;
    
    switch (status) {
      case 'completed':
        color = Colors.green;
        icon = Icons.check_circle;
        break;
      case 'in_transit':
        color = Colors.blue;
        icon = Icons.local_shipping;
        break;
      case 'accepted':
        color = Colors.orange;
        icon = Icons.person;
        break;
      default:
        color = Colors.grey;
        icon = Icons.schedule;
    }
    
    return Chip(
      avatar: Icon(icon, size: 16, color: color),
      label: Text(
        status.replaceAll('_', ' ').toUpperCase(),
        style: TextStyle(fontSize: 10, color: color),
      ),
      backgroundColor: color.withOpacity(0.1),
    );
  }
  
  IconData _getLegIcon(String legType) {
    switch (legType) {
      case 'local_pickup':
        return Icons.person_pin;
      case 'hub_dropoff':
        return Icons.warehouse;
      case 'interstate_transport':
        return Icons.local_shipping;
      case 'hub_pickup':
        return Icons.warehouse_outlined;
      case 'local_delivery':
        return Icons.home;
      default:
        return Icons.help;
    }
  }
  
  String _formatAddress(Map<String, dynamic> location) {
    return location['hub_name'] ?? location['address'] ?? 'Unknown';
  }
}
```

---

## 8. INTEGRATION WITH EXISTING TAGXI FEATURES

### 8.1 Modify Existing Request Creation Flow

```php
// app/Http/Controllers/Api/V1/Request/CreateNewRequestController.php

class CreateNewRequestController extends BaseController
{
    public function createRequest(CreateRequestRequest $request)
    {
        // Check if this is an interstate delivery
        $deliveryMode = $request->input('delivery_mode', 'local');
        
        if ($deliveryMode === 'interstate') {
            // Delegate to interstate service
            $interstateService = app(InterstateRequestService::class);
            $interstateRequest = $interstateService->createInterstateRequest($request->all());
            
            return $this->respondSuccess([
                'success' => true,
                'request_id' => $interstateRequest->id,
                'message' => 'Interstate delivery request created'
            ]);
        }
        
        // Continue with existing local delivery flow
        // ... existing code ...
    }
}
```

### 8.2 Extend Bidding System for Local Legs

```php
// app/Listeners/CreateBidRequestForLeg.php

class CreateBidRequestForLeg
{
    public function handle(NextLegTriggered $event)
    {
        $leg = $event->leg;
        
        // Only create bid for local legs with drivers
        if ($leg->provider_type !== 'driver') {
            return;
        }
        
        if (!in_array($leg->leg_type, ['local_pickup', 'local_delivery'])) {
            return;
        }
        
        // Use existing bidding system
        $request = $leg->request;
        
        // Find nearby drivers
        $drivers = Driver::nearby(
            lat: $leg->pickup_location['lat'],
            lng: $leg->pickup_location['lng'],
            radius: 10 // km
        )->where('is_available', true)->get();
        
        // Create bid requests
        foreach ($drivers as $driver) {
            BidRequest::create([
                'request_id' => $request->id,
                'leg_id' => $leg->id,
                'driver_id' => $driver->id,
                'requested_fare' => $leg->base_fare,
                'status' => 'pending'
            ]);
        }
        
        // Notify drivers via FCM
        event(new BidRequestCreated($request, $leg, $drivers));
    }
}
```

---

## 9. ADMIN BACKEND INTEGRATION

### 9.1 Add to Admin Navigation

```php
// resources/views/admin/layouts/navigation.blade.php

<!-- Add under Master or create new section -->
<li class="treeview {{ 'interstate' == $main_menu ? 'active menu-open' : '' }}">
    <a href="javascript: void(0);">
        <i class="fa fa-truck"></i>
        <span> Interstate Delivery </span>
        <span class="pull-right-container">
            <i class="fa fa-angle-right pull-right"></i>
        </span>
    </a>
    <ul class="treeview-menu">
        <li class="{{ 'trucking_companies' == $sub_menu ? 'active' : '' }}">
            <a href="{{ url('/interstate/trucking-companies') }}">
                <i class="fa fa-circle-thin"></i>Trucking Companies
            </a>
        </li>
        <li class="{{ 'trucking_hubs' == $sub_menu ? 'active' : '' }}">
            <a href="{{ url('/interstate/hubs') }}">
                <i class="fa fa-circle-thin"></i>Hubs
            </a>
        </li>
        <li class="{{ 'supported_routes' == $sub_menu ? 'active' : '' }}">
            <a href="{{ url('/interstate/routes') }}">
                <i class="fa fa-circle-thin"></i>Routes
            </a>
        </li>
        <li class="{{ 'interstate_requests' == $sub_menu ? 'active' : '' }}">
            <a href="{{ url('/interstate/requests') }}">
                <i class="fa fa-circle-thin"></i>All Requests
            </a>
        </li>
    </ul>
</li>
```

### 9.2 Admin Controllers

Create standard CRUD controllers for:
- `TruckingCompanyController` (similar to OwnerController)
- `TruckingHubController` (similar to ZoneController)
- `SupportedRouteController` (for route pricing)
- `InterstateRequestController` (for monitoring)

---

## 10. IMPLEMENTATION ROADMAP

### Phase 1: Database & Models (Week 1)
- [ ] Run migrations for interstate tables
- [ ] Extend Request model with interstate fields
- [ ] Create TruckingCompany, TruckingHub, SupportedRoute models
- [ ] Create RequestLeg model
- [ ] Set up model relationships

### Phase 2: Core Services (Week 2)
- [ ] Implement RouteSelectionService
- [ ] Implement InterstateRequestService
- [ ] Implement LegOrchestrationService
- [ ] Write unit tests for services

### Phase 3: API Layer (Week 3)
- [ ] Create InterstateRequestController
- [ ] Create TruckingCompanyController
- [ ] Add API routes
- [ ] API documentation

### Phase 4: Admin Backend (Week 4)
- [ ] Create admin controllers
- [ ] Build admin views (index, create, edit)
- [ ] Add navigation menu
- [ ] Admin authentication for trucking companies

### Phase 5: Flutter User App (Week 5-6)
- [ ] Create InterstateQuoteScreen
- [ ] Create InterstateTrackingScreen
- [ ] Add interstate option to booking flow
- [ ] Integrate with existing booking screens

### Phase 6: Flutter Driver App (Week 7)
- [ ] Show interstate legs in driver dashboard
- [ ] Handle hub handoff flow
- [ ] Proof of delivery for hubs

### Phase 7: Testing & Deployment (Week 8)
- [ ] End-to-end testing
- [ ] Performance testing
- [ ] Deploy to staging
- [ ] Bug fixes
- [ ] Production deployment

---

## 11. KEY CONSIDERATIONS

### 11.1 Backward Compatibility
- All new fields in `requests` table are nullable
- Existing local deliveries continue to work unchanged
- New `delivery_mode` field defaults to 'local'

### 11.2 Performance
- Add indexes on new foreign keys
- Use eager loading for legs in API responses
- Cache route recommendations (Redis)
- Implement pagination for hub inventory

### 11.3 Security
- Trucking company authentication via same auth system
- Role-based access control
- Validate hub ownership before updates
- Audit trail for all leg status changes

### 11.4 Monitoring
- Track hub utilization
- Monitor interstate delivery success rate
- Alert on delayed legs
- Dashboard for trucking company performance

---

## SUMMARY

This implementation plan provides a **practical, phased approach** to integrating interstate delivery into Tagxi:

✅ **Leverages existing architecture** - Extends Request model, uses Zone system, integrates with bidding  
✅ **Minimal breaking changes** - All new fields are nullable, defaults maintain backward compatibility  
✅ **Clear separation** - New tables for trucking-specific data, RequestLegs for multi-leg tracking  
✅ **Reusable components** - Services can be tested independently  
✅ **Flutter integration** - New screens that integrate with existing booking flow  
✅ **Admin ready** - Full backend management for trucking companies, hubs, routes  

The key insight is that Tagxi's existing **Request model is already flexible enough** to support interstate delivery through:
1. Adding `delivery_mode` enum
2. Creating RequestLeg child records
3. Using existing bidding system for local legs
4. Adding new provider type (trucking_company)
