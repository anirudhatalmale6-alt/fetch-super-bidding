<?php

namespace App\Services\Interstate;

use App\Models\Request\Request;
use App\Models\Request\RequestPlace;
use App\Models\Interstate\RequestPackage;
use App\Models\Interstate\RequestLeg;
use App\Models\Interstate\TruckingCompany;
use App\Models\Interstate\TruckingHub;
use App\Models\Interstate\TrackingUpdate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Kreait\Firebase\Contract\Database;

/**
 * Interstate Request Service V2 — Company Selection Flow
 *
 * Flow: User selects company → Leg 1 (local pickup to hub via bidding) →
 *       Company weighs/prices → User approves/rejects → Leg 2 (hub to recipient via bidding)
 *       OR reroute to Company B
 */
class InterstateRequestServiceV2
{
    public function __construct(
        private DimensionalPricingService $pricingService,
        private Database $database
    ) {}

    /**
     * Create interstate request with company selection (V2 flow).
     *
     * Instead of opening bidding to companies, the user selects a company directly.
     * A child bid ride request is created for Leg 1 (local pickup → hub).
     */
    public function createInterstateRequest(array $data): array
    {
        return DB::transaction(function () use ($data) {
            // 1. Validate company and get hub info
            $company = TruckingCompany::with('hubs')->findOrFail($data['trucking_company_id']);

            // Find origin hub (near sender's state)
            $originHub = $this->findHub($company, $data['pickup_state'], $data['origin_hub_id'] ?? null);
            if (!$originHub) {
                throw new \InvalidArgumentException("No hub found for {$company->company_name} in {$data['pickup_state']}");
            }

            // Find destination hub (near recipient's state) - for reference only (Leg 2 created later)
            $destinationHub = $this->findHub($company, $data['destination_state'], $data['destination_hub_id'] ?? null);

            // 2. Process packages for weight calculations
            $divisor = $company->default_volumetric_divisor ?: 5000;
            $packages = $this->processPackages($data['packages'], $divisor);

            // 3. Determine service location
            $serviceLocationId = $this->getServiceLocationId(
                (float) ($data['pick_lat'] ?? 0),
                (float) ($data['pick_lng'] ?? 0)
            );
            if (!$serviceLocationId) {
                $serviceLocationId = \App\Models\Admin\ServiceLocation::first()?->id;
            }

            // 4. Create parent interstate request
            $parentRequest = Request::create([
                'request_number' => $this->generateRequestNumber(),
                'user_id' => $data['user_id'],
                'delivery_mode' => 'interstate',
                'delivery_type' => 'interstate',
                'transport_type' => 'delivery',
                'is_bid_ride' => false, // Parent is NOT a bid ride

                // Company selection (V2: user chose directly)
                'trucking_company_id' => $company->id,
                'origin_hub_id' => $originHub->id,
                'destination_hub_id' => $destinationHub?->id,

                // Legs: start with 2 (pickup + delivery), may grow with reroutes
                'total_legs' => 2,
                'current_leg_number' => 1,

                // Sender/Recipient info
                'sender_phone' => $data['sender_phone'] ?? null,
                'sender_name' => $data['sender_name'] ?? null,
                'recipient_phone' => $data['recipient_phone'] ?? null,
                'recipient_name' => $data['recipient_name'] ?? null,
                'pickup_state' => $data['pickup_state'] ?? null,
                'destination_state' => $data['destination_state'] ?? null,

                // Prices TBD — company will set after weighing
                'local_pickup_fee' => 0,
                'interstate_transport_fee' => 0,
                'local_delivery_fee' => 0,
                'request_eta_amount' => 0,

                // Inspection status
                'inspection_status' => 'not_required', // Changes to awaiting_inspection after Leg 1

                'service_location_id' => $serviceLocationId,
                'trip_start_time' => $data['preferred_pickup_time'] ?? now(),
            ]);

            // 5. Create request place
            RequestPlace::create([
                'request_id' => $parentRequest->id,
                'pick_lat' => $data['pick_lat'] ?? 0,
                'pick_lng' => $data['pick_lng'] ?? 0,
                'pick_address' => $data['pick_address'],
                'drop_lat' => $data['drop_lat'] ?? 0,
                'drop_lng' => $data['drop_lng'] ?? 0,
                'drop_address' => $data['drop_address'],
            ]);

            // 6. Create packages
            foreach ($packages as $index => $packageData) {
                $originalPackage = $data['packages'][$index] ?? [];
                RequestPackage::create([
                    'request_id' => $parentRequest->id,
                    'package_index' => $index + 1,
                    'description' => $originalPackage['description'] ?? $packageData['description'] ?? null,
                    'actual_weight_kg' => $packageData['actual_weight_kg'],
                    'length_cm' => $packageData['length_cm'],
                    'width_cm' => $packageData['width_cm'],
                    'height_cm' => $packageData['height_cm'],
                    'quantity' => $packageData['quantity'] ?? 1,
                    'volumetric_weight_kg' => $packageData['volumetric_weight_kg'],
                    'chargeable_weight_kg' => $packageData['chargeable_weight_kg'],
                    'volumetric_divisor_used' => $divisor,
                    'declared_value' => $packageData['declared_value'] ?? $originalPackage['declared_value'] ?? 0,
                    'is_fragile' => $originalPackage['is_fragile'] ?? false,
                    'requires_insurance' => $originalPackage['requires_insurance'] ?? false,
                    'special_instructions' => $originalPackage['special_instructions'] ?? null,
                    'estimated_weight_kg' => $originalPackage['estimated_weight_kg'] ?? $packageData['actual_weight_kg'],
                    'estimated_length_cm' => $originalPackage['estimated_length_cm'] ?? $packageData['length_cm'],
                    'estimated_width_cm' => $originalPackage['estimated_width_cm'] ?? $packageData['width_cm'],
                    'estimated_height_cm' => $originalPackage['estimated_height_cm'] ?? $packageData['height_cm'],
                    'estimated_declared_value' => $originalPackage['estimated_declared_value'] ?? $packageData['declared_value'] ?? 0,
                ]);
            }

            // 7. Create Leg 1 record (local_pickup: sender → hub)
            $leg1 = RequestLeg::create([
                'request_id' => $parentRequest->id,
                'leg_number' => 1,
                'leg_type' => 'local_pickup',
                'provider_type' => 'App\\Models\\Admin\\Driver',
                'pickup_location' => [
                    'address' => $data['pick_address'],
                    'lat' => $data['pick_lat'] ?? 0,
                    'lng' => $data['pick_lng'] ?? 0,
                ],
                'drop_location' => [
                    'address' => $originHub->address,
                    'lat' => $originHub->latitude,
                    'lng' => $originHub->longitude,
                    'hub_id' => $originHub->id,
                    'hub_name' => $originHub->hub_name,
                ],
                'total_chargeable_weight' => collect($packages)->sum('chargeable_weight_kg'),
                'base_fare' => 0,
                'final_fare' => 0,
                'status' => 'pending',
            ]);

            // 8. Create child bid ride request for Leg 1
            // This is a normal bid ride that dispatch riders will see in their queue
            $childRequest = $this->createChildBidRide(
                parentRequest: $parentRequest,
                legNumber: 1,
                legType: 'local_pickup',
                pickAddress: $data['pick_address'],
                pickLat: $data['pick_lat'] ?? 0,
                pickLng: $data['pick_lng'] ?? 0,
                dropAddress: $originHub->address . ' (' . $originHub->hub_name . ')',
                dropLat: $originHub->latitude,
                dropLng: $originHub->longitude,
                serviceLocationId: $serviceLocationId
            );

            // Link child request to leg
            $leg1->update(['bid_request_id' => $childRequest->id]);

            // 9. Create placeholder Leg 2 (local_delivery: hub → recipient)
            // Actual bid ride for Leg 2 is created AFTER user approves final cost
            RequestLeg::create([
                'request_id' => $parentRequest->id,
                'leg_number' => 2,
                'leg_type' => 'local_delivery',
                'provider_type' => 'App\\Models\\Admin\\Driver',
                'pickup_location' => $destinationHub ? [
                    'address' => $destinationHub->address,
                    'lat' => $destinationHub->latitude,
                    'lng' => $destinationHub->longitude,
                    'hub_id' => $destinationHub->id,
                    'hub_name' => $destinationHub->hub_name,
                ] : [
                    'address' => $originHub->address, // Same hub if no dest hub
                    'lat' => $originHub->latitude,
                    'lng' => $originHub->longitude,
                    'hub_id' => $originHub->id,
                    'hub_name' => $originHub->hub_name,
                ],
                'drop_location' => [
                    'address' => $data['drop_address'],
                    'lat' => $data['drop_lat'] ?? 0,
                    'lng' => $data['drop_lng'] ?? 0,
                ],
                'total_chargeable_weight' => collect($packages)->sum('chargeable_weight_kg'),
                'base_fare' => 0,
                'final_fare' => 0,
                'status' => 'pending', // Will become active after user approves final cost
            ]);

            // 10. Add to Firebase for real-time tracking
            $this->syncToFirebase($parentRequest, $childRequest);

            // 11. Create tracking update
            TrackingUpdate::create([
                'request_id' => $parentRequest->id,
                'new_status' => 'request_created',
                'message' => "Interstate delivery request created. Company: {$company->company_name}. Waiting for dispatch rider to pick up from sender.",
                'created_by_type' => 'system',
            ]);

            return [
                'parent_request' => $parentRequest->load(['packages', 'legs', 'truckingCompany', 'originHub', 'destinationHub']),
                'leg1_bid_request' => $childRequest,
            ];
        });
    }

    /**
     * Create Leg 2 bid ride after user approves final cost.
     */
    public function createLeg2BidRide(Request $parentRequest): Request
    {
        return DB::transaction(function () use ($parentRequest) {
            $leg2 = $parentRequest->legs()->where('leg_number', 2)->first();
            if (!$leg2) {
                throw new \InvalidArgumentException('Leg 2 not found for this request');
            }

            $pickupLocation = $leg2->pickup_location;
            $dropLocation = $leg2->drop_location;

            $serviceLocationId = $this->getServiceLocationId(
                (float) ($pickupLocation['lat'] ?? 0),
                (float) ($pickupLocation['lng'] ?? 0)
            );
            if (!$serviceLocationId) {
                $serviceLocationId = $parentRequest->service_location_id;
            }

            $childRequest = $this->createChildBidRide(
                parentRequest: $parentRequest,
                legNumber: 2,
                legType: 'local_delivery',
                pickAddress: $pickupLocation['address'] ?? '',
                pickLat: $pickupLocation['lat'] ?? 0,
                pickLng: $pickupLocation['lng'] ?? 0,
                dropAddress: $dropLocation['address'] ?? '',
                dropLat: $dropLocation['lat'] ?? 0,
                dropLng: $dropLocation['lng'] ?? 0,
                serviceLocationId: $serviceLocationId
            );

            // Link child request to leg
            $leg2->update(['bid_request_id' => $childRequest->id]);

            // Update parent
            $parentRequest->update(['current_leg_number' => 2]);

            // Create tracking update
            TrackingUpdate::create([
                'request_id' => $parentRequest->id,
                'new_status' => 'leg2_created',
                'message' => 'Final cost approved. Leg 2 delivery request created — waiting for dispatch rider.',
                'created_by_type' => 'system',
            ]);

            // Sync to Firebase
            try {
                $this->database->getReference("interstate-requests/{$parentRequest->id}")->update([
                    'current_leg' => 2,
                    'leg2_request_id' => $childRequest->id,
                    'status' => 'leg2_bidding',
                    'updated_at' => Database::SERVER_TIMESTAMP,
                ]);
            } catch (\Exception $e) {
                \Log::warning('Firebase sync for Leg 2 failed: ' . $e->getMessage());
            }

            return $childRequest;
        });
    }

    /**
     * Create a reroute transfer bid ride (Company A hub → Company B hub).
     */
    public function createRerouteBidRide(
        Request $parentRequest,
        TruckingCompany $newCompany,
        TruckingHub $fromHub,
        TruckingHub $toHub
    ): array {
        return DB::transaction(function () use ($parentRequest, $newCompany, $fromHub, $toHub) {
            // Increment leg count
            $nextLegNumber = $parentRequest->legs()->count() + 1;

            // Create reroute leg record
            $rerouteLeg = RequestLeg::create([
                'request_id' => $parentRequest->id,
                'leg_number' => $nextLegNumber,
                'leg_type' => 'reroute_transfer',
                'provider_type' => 'App\\Models\\Admin\\Driver',
                'pickup_location' => [
                    'address' => $fromHub->address,
                    'lat' => $fromHub->latitude,
                    'lng' => $fromHub->longitude,
                    'hub_id' => $fromHub->id,
                    'hub_name' => $fromHub->hub_name,
                ],
                'drop_location' => [
                    'address' => $toHub->address,
                    'lat' => $toHub->latitude,
                    'lng' => $toHub->longitude,
                    'hub_id' => $toHub->id,
                    'hub_name' => $toHub->hub_name,
                ],
                'total_chargeable_weight' => $parentRequest->packages->sum('chargeable_weight_kg'),
                'base_fare' => 0,
                'final_fare' => 0,
                'status' => 'pending',
            ]);

            // Create child bid ride
            $serviceLocationId = $this->getServiceLocationId($fromHub->latitude, $fromHub->longitude)
                ?: $parentRequest->service_location_id;

            $childRequest = $this->createChildBidRide(
                parentRequest: $parentRequest,
                legNumber: $nextLegNumber,
                legType: 'reroute_transfer',
                pickAddress: $fromHub->address . ' (' . $fromHub->hub_name . ')',
                pickLat: $fromHub->latitude,
                pickLng: $fromHub->longitude,
                dropAddress: $toHub->address . ' (' . $toHub->hub_name . ')',
                dropLat: $toHub->latitude,
                dropLng: $toHub->longitude,
                serviceLocationId: $serviceLocationId
            );

            $rerouteLeg->update(['bid_request_id' => $childRequest->id]);

            // Update parent: assign new company, update leg 2 pickup to new hub
            $parentRequest->update([
                'trucking_company_id' => $newCompany->id,
                'origin_hub_id' => $toHub->id,
                'total_legs' => $parentRequest->total_legs + 1,
                'current_leg_number' => $nextLegNumber,
            ]);

            // Update Leg 2 pickup location to new company's hub
            $leg2 = $parentRequest->legs()->where('leg_type', 'local_delivery')->first();
            if ($leg2) {
                $leg2->update([
                    'pickup_location' => [
                        'address' => $toHub->address,
                        'lat' => $toHub->latitude,
                        'lng' => $toHub->longitude,
                        'hub_id' => $toHub->id,
                        'hub_name' => $toHub->hub_name,
                    ],
                ]);
            }

            // Tracking update
            TrackingUpdate::create([
                'request_id' => $parentRequest->id,
                'new_status' => 'reroute_transfer',
                'message' => "Package rerouted from {$fromHub->hub_name} to {$toHub->hub_name} ({$newCompany->company_name}). Waiting for dispatch rider.",
                'created_by_type' => 'system',
            ]);

            return [
                'reroute_leg' => $rerouteLeg,
                'bid_request' => $childRequest,
            ];
        });
    }

    /**
     * Create a child bid ride request for a local leg.
     * This appears in the driver app as a normal delivery bid ride.
     */
    private function createChildBidRide(
        Request $parentRequest,
        int $legNumber,
        string $legType,
        string $pickAddress,
        float $pickLat,
        float $pickLng,
        string $dropAddress,
        float $dropLat,
        float $dropLng,
        $serviceLocationId
    ): Request {
        // Generate unique request number
        $lastNumber = Request::orderBy('created_at', 'DESC')->pluck('request_number')->first();
        if ($lastNumber) {
            $parts = explode('_', $lastNumber);
            $num = (int) ($parts[1] ?? 0);
        } else {
            $num = 0;
        }
        $requestNumber = 'REQ_' . sprintf('%06d', $num + 1);

        // Get a zone type for delivery (use first delivery zone type in the service location)
        $zoneTypeId = null;
        if ($serviceLocationId) {
            $zoneType = \App\Models\Admin\ZoneType::whereHas('zone', function ($q) use ($serviceLocationId) {
                $q->where('service_location_id', $serviceLocationId);
            })->first();
            $zoneTypeId = $zoneType?->id;
        }

        $serviceLocation = $serviceLocationId
            ? \App\Models\Admin\ServiceLocation::find($serviceLocationId)
            : \App\Models\Admin\ServiceLocation::first();

        $childRequest = Request::create([
            'request_number' => $requestNumber,
            'user_id' => $parentRequest->user_id,
            'is_bid_ride' => true,
            'transport_type' => 'delivery',
            'delivery_mode' => 'local',
            'payment_opt' => 1, // Cash (will be adjusted)
            'ride_otp' => rand(1111, 9999),
            'zone_type_id' => $zoneTypeId,
            'service_location_id' => $serviceLocationId,
            'requested_currency_code' => $serviceLocation?->currency_code ?? 'NGN',
            'requested_currency_symbol' => $serviceLocation?->currency_symbol ?? '₦',
            'unit' => '1',
            'timezone' => $serviceLocation?->timezone ?? 'Africa/Lagos',
            'company_key' => $parentRequest->company_key ?? null,

            // Interstate linking
            'interstate_parent_id' => $parentRequest->id,
            'interstate_leg_number' => $legNumber,
            'interstate_leg_type' => $legType,

            'trip_start_time' => now(),
            'offerred_ride_fare' => 0,
        ]);

        // Create place
        RequestPlace::create([
            'request_id' => $childRequest->id,
            'pick_lat' => $pickLat,
            'pick_lng' => $pickLng,
            'pick_address' => $pickAddress,
            'drop_lat' => $dropLat,
            'drop_lng' => $dropLng,
            'drop_address' => $dropAddress,
        ]);

        // Add to Firebase so drivers can see it
        try {
            $this->database->getReference('requests/' . $childRequest->id)->update([
                'request_id' => $childRequest->id,
                'request_number' => $childRequest->request_number,
                'service_location_id' => $serviceLocationId,
                'user_id' => $childRequest->user_id,
                'pick_address' => $pickAddress,
                'drop_address' => $dropAddress,
                'active' => 1,
                'is_bid_ride' => 1,
                'interstate_parent_id' => $parentRequest->id,
                'interstate_leg_type' => $legType,
                'updated_at' => Database::SERVER_TIMESTAMP,
            ]);
        } catch (\Exception $e) {
            \Log::warning('Firebase sync for child request failed: ' . $e->getMessage());
        }

        return $childRequest;
    }

    /**
     * Find a hub for a company in a given state.
     */
    private function findHub(TruckingCompany $company, string $state, ?int $hubId = null): ?TruckingHub
    {
        if ($hubId) {
            return $company->hubs()->where('id', $hubId)->first();
        }

        return $company->hubs()
            ->where('is_active', true)
            ->where(function ($q) use ($state) {
                $q->where('state', 'LIKE', "%{$state}%")
                  ->orWhere('city', 'LIKE', "%{$state}%");
            })
            ->first();
    }

    private function processPackages(array $packages, int $divisor): array
    {
        return collect($packages)->map(function ($package) use ($divisor) {
            return $this->pricingService->processPackage($package, $divisor);
        })->toArray();
    }

    private function generateRequestNumber(): string
    {
        return 'INT-' . date('Y') . '-' . strtoupper(Str::random(6));
    }

    private function getServiceLocationId(float $lat, float $lng)
    {
        if (!$lat || !$lng) return null;
        try {
            $zone = \App\Models\Admin\Zone::containsPoint($lat, $lng)->first();
            return $zone?->service_location_id;
        } catch (\Exception $e) {
            return \App\Models\Admin\ServiceLocation::first()?->id;
        }
    }

    private function syncToFirebase(Request $parentRequest, Request $childRequest): void
    {
        try {
            $this->database->getReference("interstate-requests/{$parentRequest->id}")->update([
                'request_id' => $parentRequest->id,
                'request_number' => $parentRequest->request_number,
                'user_id' => $parentRequest->user_id,
                'company_id' => $parentRequest->trucking_company_id,
                'status' => 'leg1_bidding',
                'current_leg' => 1,
                'leg1_request_id' => $childRequest->id,
                'updated_at' => Database::SERVER_TIMESTAMP,
            ]);
        } catch (\Exception $e) {
            \Log::warning('Firebase sync failed: ' . $e->getMessage());
        }
    }
}
