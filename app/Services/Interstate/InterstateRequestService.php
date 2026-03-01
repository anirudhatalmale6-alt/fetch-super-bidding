<?php

namespace App\Services\Interstate;

use App\Models\Request\Request;
use App\Models\Request\RequestPlace;
use App\Models\Interstate\RequestPackage;
use App\Models\Interstate\RequestLeg;
use App\Models\Interstate\SupportedRoute;
use App\Models\Admin\Zone;
use App\Events\Interstate\InterstateRequestCreated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InterstateRequestService
{
    public function __construct(
        private DimensionalPricingService $pricingService
    ) {}

    /**
     * Create a new interstate delivery request with dimensional freight pricing
     * 
     * @param array $data Request data including packages with dimensions
     * @return Request
     * @throws \Exception
     */
    public function createInterstateRequest(array $data): Request
    {
        return DB::transaction(function () use ($data) {
            
            // 1. Validate and get the selected route
            $route = SupportedRoute::with(['truckingCompany', 'originHub', 'destinationHub'])
                ->findOrFail($data['route_id']);

            // 2. Process packages and calculate weights
            $packages = $this->processPackages(
                $data['packages'], 
                $route->getVolumetricDivisor()
            );

            // 3. Validate packages against route limits
            $this->validatePackagesAgainstRoute($packages, $route);

            // 4. Calculate freight pricing for interstate leg
            $pricingOptions = [
                'is_express' => ($data['service_type'] ?? 'standard') === 'express',
                'is_fragile' => collect($packages)->contains('is_fragile', true),
                'requires_insurance' => $data['requires_insurance'] ?? false,
                'declared_value' => collect($packages)->sum('declared_value'),
            ];

            $freightCalculation = $this->pricingService->calculateTotalFreight(
                $packages,
                $route,
                $pricingOptions
            );

            // 5. Calculate local leg prices (using existing Tagxi logic)
            $localPickupPrice = $this->estimateLocalDeliveryPrice(
                $data['pick_lat'],
                $data['pick_lng'],
                $route->originHub->latitude,
                $route->originHub->longitude
            );

            $localDeliveryPrice = $this->estimateLocalDeliveryPrice(
                $route->destinationHub->latitude,
                $route->destinationHub->longitude,
                $data['drop_lat'],
                $data['drop_lng']
            );

            // 6. Calculate totals
            $subtotal = $localPickupPrice + $freightCalculation->totalPrice + $localDeliveryPrice;
            $vat = $subtotal * 0.075; // Nigeria VAT
            $total = $subtotal + $vat;

            // 7. Create main request
            $request = Request::create([
                'request_number' => $this->generateRequestNumber(),
                'user_id' => $data['user_id'],
                'delivery_mode' => 'interstate',
                'transport_type' => 'delivery',
                'is_bid_ride' => true, // Enable bidding for local legs
                
                // Interstate specific
                'trucking_company_id' => $route->trucking_company_id,
                'origin_hub_id' => $route->origin_hub_id,
                'destination_hub_id' => $route->destination_hub_id,
                'supported_route_id' => $route->id,
                'total_legs' => 5,
                'current_leg_number' => 1,
                
                // Financial breakdown
                'local_pickup_fee' => $localPickupPrice,
                'interstate_transport_fee' => $freightCalculation->totalPrice,
                'local_delivery_fee' => $localDeliveryPrice,
                
                // Total
                'request_eta_amount' => $total,
                
                // Status
                'status' => 'pending',
                
                // Service location
                'service_location_id' => $this->getServiceLocationId(
                    $data['pick_lat'], 
                    $data['pick_lng']
                ),
                
                // Timestamps
                'trip_start_time' => $data['preferred_pickup_time'] ?? now(),
            ]);

            // 8. Create request place
            RequestPlace::create([
                'request_id' => $request->id,
                'pick_lat' => $data['pick_lat'],
                'pick_lng' => $data['pick_lng'],
                'pick_address' => $data['pick_address'],
                'drop_lat' => $data['drop_lat'],
                'drop_lng' => $data['drop_lng'],
                'drop_address' => $data['drop_address'],
            ]);

            // 9. Create packages
            foreach ($packages as $index => $packageData) {
                RequestPackage::create([
                    'request_id' => $request->id,
                    'package_index' => $index + 1,
                    'description' => $packageData['description'] ?? null,
                    'actual_weight_kg' => $packageData['actual_weight_kg'],
                    'length_cm' => $packageData['length_cm'],
                    'width_cm' => $packageData['width_cm'],
                    'height_cm' => $packageData['height_cm'],
                    'quantity' => $packageData['quantity'] ?? 1,
                    'volumetric_weight_kg' => $packageData['volumetric_weight_kg'],
                    'chargeable_weight_kg' => $packageData['chargeable_weight_kg'],
                    'volumetric_divisor_used' => $route->getVolumetricDivisor(),
                    'declared_value' => $packageData['declared_value'] ?? 0,
                    'is_fragile' => $packageData['is_fragile'] ?? false,
                    'requires_insurance' => $packageData['requires_insurance'] ?? false,
                    'special_instructions' => $packageData['special_instructions'] ?? null,
                ]);
            }

            // 10. Create delivery legs
            $this->createDeliveryLegs($request, $route, $data, $freightCalculation, $localPickupPrice, $localDeliveryPrice);

            // 11. Emit event for bidding on first leg
            event(new InterstateRequestCreated($request));

            return $request->load(['packages', 'legs', 'truckingCompany', 'originHub', 'destinationHub']);
        });
    }

    /**
     * Process packages and calculate volumetric weights
     */
    private function processPackages(array $packages, int $divisor): array
    {
        return collect($packages)->map(function ($package) use ($divisor) {
            return $this->pricingService->processPackage($package, $divisor);
        })->toArray();
    }

    /**
     * Validate packages against route limits
     */
    private function validatePackagesAgainstRoute(array $packages, SupportedRoute $route): void
    {
        foreach ($packages as $index => $package) {
            $validation = $this->pricingService->validatePackageAgainstRoute($package, $route);
            
            if (!$validation->isValid) {
                $error = $validation->getFirstError();
                throw new \InvalidArgumentException(
                    "Package " . ($index + 1) . " validation failed: {$error}"
                );
            }
        }
    }

    /**
     * Create delivery legs for the interstate request
     */
    private function createDeliveryLegs(
        Request $request,
        SupportedRoute $route,
        array $data,
        \App\Services\Interstate\FreightCalculationResult $freightCalc,
        float $localPickupPrice,
        float $localDeliveryPrice
    ): void {
        
        $totalChargeableWeight = $freightCalc->chargeableWeight;
        
        // Leg 1: Local Pickup (Seller → Origin Hub)
        RequestLeg::create([
            'request_id' => $request->id,
            'leg_number' => 1,
            'leg_type' => 'local_pickup',
            'provider_type' => 'App\\Models\\Admin\\Driver', // Will be assigned via bidding
            'pickup_location' => [
                'address' => $data['pick_address'],
                'lat' => $data['pick_lat'],
                'lng' => $data['pick_lng'],
            ],
            'drop_location' => [
                'address' => $route->originHub->address,
                'lat' => $route->originHub->latitude,
                'lng' => $route->originHub->longitude,
                'hub_id' => $route->origin_hub_id,
                'hub_name' => $route->originHub->hub_name,
            ],
            'total_chargeable_weight' => $totalChargeableWeight,
            'base_fare' => $localPickupPrice,
            'final_fare' => $localPickupPrice,
            'status' => 'pending',
        ]);

        // Leg 2: Hub Dropoff (Handoff at Origin Hub)
        RequestLeg::create([
            'request_id' => $request->id,
            'leg_number' => 2,
            'leg_type' => 'hub_dropoff',
            'provider_type' => 'App\\Models\\Admin\\Driver', // Same driver as leg 1
            'pickup_location' => [
                'address' => $route->originHub->address,
                'lat' => $route->originHub->latitude,
                'lng' => $route->originHub->longitude,
                'hub_id' => $route->origin_hub_id,
            ],
            'drop_location' => [
                'address' => $route->originHub->address,
                'lat' => $route->originHub->latitude,
                'lng' => $route->originHub->longitude,
                'hub_id' => $route->origin_hub_id,
            ],
            'total_chargeable_weight' => $totalChargeableWeight,
            'base_fare' => 0, // Included in leg 1
            'final_fare' => 0,
            'status' => 'pending',
        ]);

        // Leg 3: Interstate Transport (Origin Hub → Destination Hub)
        RequestLeg::create([
            'request_id' => $request->id,
            'leg_number' => 3,
            'leg_type' => 'interstate_transport',
            'provider_type' => 'App\\Models\\Interstate\\TruckingCompany',
            'provider_id' => $route->trucking_company_id,
            'provider_name' => $route->truckingCompany->company_name,
            'provider_phone' => $route->truckingCompany->phone,
            'supported_route_id' => $route->id,
            'pickup_location' => [
                'address' => $route->originHub->address,
                'lat' => $route->originHub->latitude,
                'lng' => $route->originHub->longitude,
                'hub_id' => $route->origin_hub_id,
                'hub_name' => $route->originHub->hub_name,
            ],
            'drop_location' => [
                'address' => $route->destinationHub->address,
                'lat' => $route->destinationHub->latitude,
                'lng' => $route->destinationHub->longitude,
                'hub_id' => $route->destination_hub_id,
                'hub_name' => $route->destinationHub->hub_name,
            ],
            'total_chargeable_weight' => $totalChargeableWeight,
            'base_fare' => $freightCalc->baseFreight,
            'minimum_charge_applied' => $freightCalc->minimumChargeApplied ? $route->minimum_charge : 0,
            'express_surcharge' => $freightCalc->expressSurcharge,
            'fragile_surcharge' => $freightCalc->fragileSurcharge,
            'insurance_charge' => $freightCalc->insuranceCharge,
            'final_fare' => $freightCalc->totalPrice,
            'provider_earnings' => $freightCalc->totalPrice * (1 - ($route->truckingCompany->commission_rate / 100)),
            'pricing_breakdown' => $freightCalc->breakdown,
            'status' => 'pending',
        ]);

        // Leg 4: Hub Pickup (Handoff at Destination Hub)
        RequestLeg::create([
            'request_id' => $request->id,
            'leg_number' => 4,
            'leg_type' => 'hub_pickup',
            'provider_type' => 'App\\Models\\Admin\\Driver',
            'pickup_location' => [
                'address' => $route->destinationHub->address,
                'lat' => $route->destinationHub->latitude,
                'lng' => $route->destinationHub->longitude,
                'hub_id' => $route->destination_hub_id,
            ],
            'drop_location' => [
                'address' => $route->destinationHub->address,
                'lat' => $route->destinationHub->latitude,
                'lng' => $route->destinationHub->longitude,
                'hub_id' => $route->destination_hub_id,
            ],
            'total_chargeable_weight' => $totalChargeableWeight,
            'base_fare' => 0, // Included in leg 5
            'final_fare' => 0,
            'status' => 'pending',
        ]);

        // Leg 5: Local Delivery (Destination Hub → Buyer)
        RequestLeg::create([
            'request_id' => $request->id,
            'leg_number' => 5,
            'leg_type' => 'local_delivery',
            'provider_type' => 'App\\Models\\Admin\\Driver',
            'pickup_location' => [
                'address' => $route->destinationHub->address,
                'lat' => $route->destinationHub->latitude,
                'lng' => $route->destinationHub->longitude,
                'hub_id' => $route->destination_hub_id,
                'hub_name' => $route->destinationHub->hub_name,
            ],
            'drop_location' => [
                'address' => $data['drop_address'],
                'lat' => $data['drop_lat'],
                'lng' => $data['drop_lng'],
            ],
            'total_chargeable_weight' => $totalChargeableWeight,
            'base_fare' => $localDeliveryPrice,
            'final_fare' => $localDeliveryPrice,
            'status' => 'pending',
        ]);
    }

    /**
     * Generate unique request number
     */
    private function generateRequestNumber(): string
    {
        $prefix = 'INT';
        $year = date('Y');
        $random = strtoupper(Str::random(6));
        
        return "{$prefix}-{$year}-{$random}";
    }

    /**
     * Get service location ID from coordinates
     */
    private function getServiceLocationId(float $lat, float $lng): ?int
    {
        $zone = Zone::containsPoint($lat, $lng)->first();
        return $zone?->service_location_id;
    }

    /**
     * Estimate local delivery price
     */
    private function estimateLocalDeliveryPrice(
        float $fromLat,
        float $fromLng,
        float $toLat,
        float $toLng
    ): float {
        // Calculate distance using Haversine formula
        $distance = $this->calculateDistance($fromLat, $fromLng, $toLat, $toLng);
        
        // Base price calculation (simplified - use actual Tagxi pricing logic)
        $basePrice = 500; // Base fare
        $perKmRate = 100; // Per km rate
        
        return $basePrice + ($distance * $perKmRate);
    }

    /**
     * Calculate distance between two points using Haversine formula
     */
    private function calculateDistance(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2
    ): float {
        $earthRadius = 6371; // km
        
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);
        
        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($lngDelta / 2) * sin($lngDelta / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }
}
