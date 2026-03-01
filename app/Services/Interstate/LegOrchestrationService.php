<?php

namespace App\Services\Interstate;

use App\Models\Request\Request;
use App\Models\Interstate\RequestLeg;
use App\Models\Interstate\HubInventory;
use App\Models\Interstate\SupportedRoute;
use App\Services\Interstate\Payment\MultiLegPaymentService;
use App\Events\Interstate\LegCompleted;
use App\Events\Interstate\NextLegTriggered;
use App\Events\Interstate\WeightVerificationRequired;
use App\Events\Interstate\LegPaymentRequired;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for orchestrating multi-leg interstate delivery
 * Handles leg transitions, weight verification, and payment prompts
 */
class LegOrchestrationService
{
    public function __construct(
        private MultiLegPaymentService $paymentService,
        private DimensionalPricingService $pricingService
    ) {}

    /**
     * Handle leg completion and trigger next leg
     * This is the core orchestration method
     */
    public function completeLeg(RequestLeg $leg, array $proofData = [], ?array $verifiedWeightData = null): void
    {
        DB::transaction(function () use ($leg, $proofData, $verifiedWeightData) {
            
            $request = $leg->request;
            $previousStatus = $leg->status;
            
            // 1. Update leg status
            $updateData = [
                'status' => 'completed',
                'completed_at' => now(),
            ];
            
            if (!empty($proofData)) {
                $updateData['delivery_proof'] = $proofData;
            }
            
            // Store verified weight data if provided (for interstate transport legs)
            if ($verifiedWeightData && $leg->leg_type === 'interstate_transport') {
                $updateData['pricing_breakdown'] = array_merge(
                    $leg->pricing_breakdown ?? [],
                    ['verified_weight_data' => $verifiedWeightData]
                );
                $updateData['total_chargeable_weight'] = $verifiedWeightData['verified_chargeable_weight'] ?? $leg->total_chargeable_weight;
            }
            
            $leg->update($updateData);
            
            Log::info("Leg {$leg->leg_number} ({$leg->leg_type}) completed for request {$request->request_number}");
            
            // 2. Handle leg-specific actions
            match($leg->leg_type) {
                'local_pickup' => $this->onPickupComplete($leg, $request),
                'hub_dropoff' => $this->onHubDropoff($leg, $request),
                'interstate_transport' => $this->onInterstateComplete($leg, $request, $verifiedWeightData),
                'hub_pickup' => $this->onHubPickup($leg, $request),
                'local_delivery' => $this->onDeliveryComplete($leg, $request),
            };
            
            // 3. Check if there's a next leg
            $nextLeg = $request->legs()
                ->where('leg_number', $leg->leg_number + 1)
                ->first();
            
            if ($nextLeg) {
                // For interstate transport completion, handle weight verification and payment
                if ($leg->leg_type === 'interstate_transport' && $verifiedWeightData) {
                    $this->handleInterstatePaymentAdjustment($leg, $nextLeg, $verifiedWeightData);
                } else {
                    $this->triggerNextLeg($nextLeg, $leg);
                }
            } else {
                // All legs complete - mark request as complete
                $this->completeRequest($request);
            }
            
            // 4. Emit event
            event(new LegCompleted($leg, $previousStatus));
        });
    }

    /**
     * Handle local pickup completion (Leg 1)
     */
    private function onPickupComplete(RequestLeg $leg, Request $request): void
    {
        $request->update(['status' => 'picked_up']);
        
        Log::info("Package picked up for request {$request->request_number}");
    }

    /**
     * Handle hub dropoff completion (Leg 2)
     */
    private function onHubDropoff(RequestLeg $leg, Request $request): void
    {
        // Create hub inventory record
        HubInventory::create([
            'hub_id' => $leg->drop_location['hub_id'],
            'request_id' => $request->id,
            'request_leg_id' => $leg->id,
            'status' => 'received',
            'received_at' => now(),
            'received_by' => $leg->provider_id, // Driver who dropped off
        ]);
        
        $request->update([
            'status' => 'at_origin_hub',
            'actual_hub_arrival' => now()
        ]);
        
        // Notify trucking company that package is ready
        event(new \App\Events\Interstate\PackageReadyForTransport($request));
        
        Log::info("Package arrived at origin hub for request {$request->request_number}");
    }

    /**
     * Handle interstate transport completion (Leg 3)
     * This is where weight verification happens
     */
    private function onInterstateComplete(RequestLeg $leg, Request $request, ?array $verifiedWeightData): void
    {
        // Update hub inventory at origin hub
        HubInventory::where('request_id', $request->id)
            ->where('hub_id', $leg->pickup_location['hub_id'])
            ->update([
                'status' => 'dispatched',
                'dispatched_at' => now(),
                'dispatched_by' => $leg->provider_id,
            ]);
        
        // Create new inventory record at destination hub
        HubInventory::create([
            'hub_id' => $leg->drop_location['hub_id'],
            'request_id' => $request->id,
            'request_leg_id' => $leg->id,
            'status' => 'received',
            'received_at' => now(),
            'received_from_provider_id' => $leg->provider_id,
        ]);
        
        $request->update([
            'status' => 'at_destination_hub',
            'actual_hub_departure' => now(),
        ]);
        
        Log::info("Interstate transport completed for request {$request->request_number}", [
            'verified_weight' => $verifiedWeightData['verified_chargeable_weight'] ?? null,
            'original_weight' => $leg->total_chargeable_weight,
        ]);
    }

    /**
     * Handle hub pickup completion (Leg 4)
     */
    private function onHubPickup(RequestLeg $leg, Request $request): void
    {
        // Update hub inventory
        HubInventory::where('request_id', $request->id)
            ->where('hub_id', $leg->pickup_location['hub_id'])
            ->update([
                'status' => 'dispatched',
                'dispatched_at' => now(),
                'dispatched_by' => $leg->provider_id,
            ]);
        
        $request->update([
            'status' => 'out_for_delivery',
            'current_leg_number' => 5,
        ]);
        
        Log::info("Package picked up from destination hub for request {$request->request_number}");
    }

    /**
     * Handle final delivery completion (Leg 5)
     */
    private function onDeliveryComplete(RequestLeg $leg, Request $request): void
    {
        // Final leg - will be handled by completeRequest
        Log::info("Final delivery completed for request {$request->request_number}");
    }

    /**
     * Trigger the next leg in the chain
     */
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
        
        event(new NextLegTriggered($nextLeg, $completedLeg));
    }

    /**
     * Handle interstate transport weight verification and payment adjustment
     * This is the KEY method for the multi-leg payment flow
     */
    private function handleInterstatePaymentAdjustment(
        RequestLeg $completedLeg, 
        RequestLeg $nextLeg, 
        array $verifiedWeightData
    ): void {
        $request = $completedLeg->request;
        $route = SupportedRoute::find($completedLeg->supported_route_id);
        
        if (!$route) {
            Log::error("Route not found for leg {$completedLeg->id}");
            $this->triggerNextLeg($nextLeg, $completedLeg);
            return;
        }
        
        // Get original and verified weights
        $originalWeight = $completedLeg->total_chargeable_weight;
        $verifiedWeight = $verifiedWeightData['verified_chargeable_weight'] ?? $originalWeight;
        $originalFare = $completedLeg->final_fare;
        
        // Recalculate fare based on verified weight
        $recalculatedFare = $this->calculateAdjustedFare($route, $verifiedWeight, $completedLeg);
        
        $priceDifference = $recalculatedFare - $originalFare;
        
        Log::info("Weight verification for request {$request->request_number}", [
            'original_weight' => $originalWeight,
            'verified_weight' => $verifiedWeight,
            'original_fare' => $originalFare,
            'recalculated_fare' => $recalculatedFare,
            'difference' => $priceDifference,
        ]);
        
        // Update leg with verified pricing
        $completedLeg->update([
            'total_chargeable_weight' => $verifiedWeight,
            'final_fare' => $recalculatedFare,
            'provider_earnings' => $recalculatedFare * (1 - ($route->truckingCompany->commission_rate / 100)),
            'pricing_breakdown' => array_merge(
                $completedLeg->pricing_breakdown ?? [],
                [
                    'weight_verification' => [
                        'original_weight' => $originalWeight,
                        'verified_weight' => $verifiedWeight,
                        'original_fare' => $originalFare,
                        'verified_fare' => $recalculatedFare,
                        'difference' => $priceDifference,
                        'verified_at' => now()->toIso8601String(),
                    ]
                ]
            ),
        ]);
        
        // Update request totals
        $request->update([
            'interstate_transport_fee' => $recalculatedFare,
            'request_eta_amount' => $request->local_pickup_fee + $recalculatedFare + $request->local_delivery_fee,
        ]);
        
        // Handle payment based on price difference
        if ($priceDifference > 0) {
            // User needs to pay additional amount
            $this->paymentService->createAdditionalPaymentRequest($request, $completedLeg, $priceDifference);
            
            // Emit event to notify user app
            event(new LegPaymentRequired($request, $completedLeg, $priceDifference, 'additional'));
            
            // DON'T trigger next leg yet - wait for payment
            Log::info("Additional payment required for request {$request->request_number}: {$priceDifference}");
            
        } elseif ($priceDifference < 0) {
            // User gets refund
            $this->paymentService->createRefund($request, $completedLeg, abs($priceDifference));
            
            // Emit refund event
            event(new LegPaymentRequired($request, $completedLeg, abs($priceDifference), 'refund'));
            
            // Trigger next leg immediately (refund is processed async)
            $this->triggerNextLeg($nextLeg, $completedLeg);
            
            Log::info("Refund issued for request {$request->request_number}: " . abs($priceDifference));
            
        } else {
            // No price change - trigger next leg
            $this->triggerNextLeg($nextLeg, $completedLeg);
        }
    }

    /**
     * Calculate adjusted fare based on verified weight
     */
    private function calculateAdjustedFare(SupportedRoute $route, float $verifiedWeight, RequestLeg $leg): float
    {
        // Get original pricing options from leg
        $originalBreakdown = $leg->pricing_breakdown ?? [];
        $isExpress = ($originalBreakdown['express_surcharge'] ?? 0) > 0;
        $isFragile = ($originalBreakdown['fragile_surcharge'] ?? 0) > 0;
        $requiresInsurance = ($originalBreakdown['insurance_charge'] ?? 0) > 0;
        $declaredValue = $originalBreakdown['declared_value'] ?? 0;
        
        // Calculate new fare
        $chargeableWeight = max($verifiedWeight, $route->minimum_chargeable_weight);
        $baseFreight = $chargeableWeight * $route->price_per_kg;
        $baseFreight = max($baseFreight, $route->minimum_charge);
        
        // Apply surcharges
        if ($isExpress) {
            $baseFreight *= (1 + $route->express_surcharge_percent / 100);
        }
        
        if ($isFragile) {
            $baseFreight *= (1 + $route->fragile_surcharge_percent / 100);
        }
        
        if ($requiresInsurance && $declaredValue > 0) {
            $baseFreight += ($declaredValue * $route->insurance_rate_percent / 100);
        }
        
        return round($baseFreight, 2);
    }

    /**
     * Activate hub dropoff leg (Leg 2)
     * This happens automatically after pickup
     */
    private function activateHubDropoff(RequestLeg $nextLeg, RequestLeg $completedLeg): void
    {
        // Leg 2 uses the same driver as Leg 1
        if ($completedLeg->provider_type === 'App\Models\Admin\Driver') {
            $nextLeg->update([
                'provider_id' => $completedLeg->provider_id,
                'provider_name' => $completedLeg->provider_name,
                'provider_phone' => $completedLeg->provider_phone,
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);
        }
    }

    /**
     * Activate interstate transport leg (Leg 3)
     */
    private function activateInterstate(RequestLeg $leg): void
    {
        $request = $leg->request;
        $route = SupportedRoute::find($leg->supported_route_id);
        
        if (!$route) {
            Log::error("Cannot activate interstate leg - route not found for leg {$leg->id}");
            return;
        }
        
        // Update leg status to accepted (trucking company is pre-assigned)
        $leg->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);
        
        // Notify trucking company
        event(new \App\Events\Interstate\InterstateLegActivated($leg, $route));
        
        Log::info("Interstate transport leg activated for request {$request->request_number}");
    }

    /**
     * Activate hub pickup leg (Leg 4)
     */
    private function activateHubPickup(RequestLeg $leg): void
    {
        // This is a handoff - driver will be assigned when they arrive
        $leg->update(['status' => 'pending']);
        
        // Notify destination hub
        event(new \App\Events\Interstate\PackageArrivedAtDestinationHub($leg->request));
    }

    /**
     * Activate local delivery leg (Leg 5)
     * Triggers bidding for a driver
     */
    private function activateLocalDelivery(RequestLeg $leg): void
    {
        $leg->update(['status' => 'pending']);
        
        // Trigger bidding using existing Tagxi system
        event(new \App\Events\Interstate\LocalDeliveryLegReadyForBidding($leg));
        
        Log::info("Local delivery leg ready for bidding - request {$leg->request->request_number}");
    }

    /**
     * Complete the entire request
     */
    private function completeRequest(Request $request): void
    {
        $request->update([
            'status' => 'completed',
            'is_completed' => true,
            'completed_at' => now(),
        ]);
        
        // Final payment settlement
        $this->paymentService->finalizeRequestPayments($request);
        
        event(new \App\Events\Interstate\InterstateRequestCompleted($request));
        
        Log::info("Interstate request completed: {$request->request_number}");
    }

    /**
     * Process payment confirmation and trigger next leg
     * Called from payment webhook/controller
     */
    public function processLegPaymentConfirmation(Request $request, RequestLeg $leg): void
    {
        Log::info("Payment confirmed for leg {$leg->leg_number} of request {$request->request_number}");
        
        // Find next leg
        $nextLeg = $request->legs()
            ->where('leg_number', $leg->leg_number + 1)
            ->first();
        
        if ($nextLeg) {
            $this->triggerNextLeg($nextLeg, $leg);
        }
    }

    /**
     * Handle weight verification from trucking company
     * This is called when trucking company submits verified weight
     */
    public function processWeightVerification(
        RequestLeg $leg, 
        float $verifiedChargeableWeight,
        ?array $verificationDetails = null
    ): array {
        
        if ($leg->leg_type !== 'interstate_transport') {
            throw new \InvalidArgumentException('Weight verification only applies to interstate transport legs');
        }
        
        $originalWeight = $leg->total_chargeable_weight;
        $weightDifference = $verifiedChargeableWeight - $originalWeight;
        
        $verificationData = [
            'verified_chargeable_weight' => $verifiedChargeableWeight,
            'original_chargeable_weight' => $originalWeight,
            'weight_difference' => $weightDifference,
            'verified_at' => now()->toIso8601String(),
            'verified_by' => auth()->id() ?? null,
            'details' => $verificationDetails ?? [],
        ];
        
        // Emit event for weight verification
        event(new WeightVerificationRequired($leg->request, $leg, $verificationData));
        
        Log::info("Weight verification processed for leg {$leg->id}", $verificationData);
        
        return [
            'original_weight' => $originalWeight,
            'verified_weight' => $verifiedChargeableWeight,
            'difference' => $weightDifference,
            'price_adjustment_pending' => true,
        ];
    }

    /**
     * Get current status summary for a request
     */
    public function getRequestStatusSummary(Request $request): array
    {
        $legs = $request->legs()->orderBy('leg_number')->get();
        $currentLeg = $legs->firstWhere('leg_number', $request->current_leg_number);
        
        $completedLegs = $legs->where('status', 'completed')->count();
        $progressPercentage = $request->total_legs > 0 
            ? ($completedLegs / $request->total_legs) * 100 
            : 0;
        
        return [
            'request_number' => $request->request_number,
            'overall_status' => $request->status,
            'current_leg_number' => $request->current_leg_number,
            'total_legs' => $request->total_legs,
            'completed_legs' => $completedLegs,
            'progress_percentage' => round($progressPercentage, 1),
            'current_leg' => $currentLeg ? [
                'leg_number' => $currentLeg->leg_number,
                'leg_type' => $currentLeg->leg_type,
                'display_name' => $currentLeg->display_name,
                'status' => $currentLeg->status,
                'provider' => $currentLeg->provider_name,
            ] : null,
            'legs' => $legs->map(fn($leg) => [
                'leg_number' => $leg->leg_number,
                'leg_type' => $leg->leg_type,
                'display_name' => $leg->display_name,
                'status' => $leg->status,
                'completed_at' => $leg->completed_at,
            ]),
            'financial_summary' => [
                'local_pickup_fee' => $request->local_pickup_fee,
                'interstate_transport_fee' => $request->interstate_transport_fee,
                'local_delivery_fee' => $request->local_delivery_fee,
                'total_amount' => $request->request_eta_amount,
                'paid_amount' => $this->paymentService->getPaidAmount($request),
                'pending_amount' => $this->paymentService->getPendingAmount($request),
            ],
        ];
    }
}
