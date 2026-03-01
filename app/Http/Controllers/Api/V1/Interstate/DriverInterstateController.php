<?php

namespace App\Http\Controllers\Api\V1\Interstate;

use App\Http\Controllers\Controller;
use App\Models\Interstate\RequestLeg;
use App\Models\Interstate\TrackingUpdate;
use App\Models\Request\Request as DeliveryRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DriverInterstateController
 *
 * Handles all interstate delivery leg actions performed by a dispatch rider (driver)
 * via the Flutter driver mobile app.
 *
 * Legs handled:
 *  Leg 1 – local_pickup     : Driver picks up from sender → Origin Hub
 *  Leg 2 – hub_dropoff      : Driver confirms handoff at Origin Hub
 *  Leg 4 – hub_pickup       : Driver collects from Destination Hub
 *  Leg 5 – local_delivery   : Driver delivers to final recipient
 */
class DriverInterstateController extends Controller
{
    // ─────────────────────────────────────────────────────────
    // GET – Endpoints
    // ─────────────────────────────────────────────────────────

    /**
     * List active interstate legs assigned to the authenticated driver.
     *
     * GET /api/v1/interstate/driver/legs
     */
    public function activeLegs(Request $request)
    {
        $driver = $this->resolveDriver();

        $legs = RequestLeg::with([
            'interstateRequest.originHub',
            'interstateRequest.destinationHub',
            'interstateRequest.packages',
        ])
        ->where('provider_type', 'App\\Models\\Admin\\Driver')
        ->where('provider_id', $driver->id)
        ->whereNotIn('status', ['completed', 'cancelled'])
        ->orderBy('created_at', 'desc')
        ->get();

        return response()->json([
            'status'  => 'success',
            'data'    => $legs,
            'count'   => $legs->count(),
        ]);
    }

    /**
     * Get full detail for a single interstate delivery request (driver view).
     *
     * GET /api/v1/interstate/driver/request/{requestId}
     */
    public function requestDetail(Request $request, int $requestId)
    {
        $driver = $this->resolveDriver();

        $interstateRequest = DeliveryRequest::with([
            'legs',
            'packages',
            'originHub',
            'destinationHub',
            'truckingCompany',
            'places',
        ])
        ->where('delivery_mode', 'interstate')
        ->whereHas('legs', function ($q) use ($driver) {
            $q->where('provider_type', 'App\\Models\\Admin\\Driver')
              ->where('provider_id', $driver->id);
        })
        ->findOrFail($requestId);

        return response()->json([
            'status' => 'success',
            'data'   => $interstateRequest,
        ]);
    }

    /**
     * Get detail for a specific leg.
     *
     * GET /api/v1/interstate/driver/leg/{legId}
     */
    public function legDetail(Request $request, int $legId)
    {
        $driver = $this->resolveDriver();

        $leg = RequestLeg::with(['interstateRequest.packages'])
            ->where('provider_type', 'App\\Models\\Admin\\Driver')
            ->where('provider_id', $driver->id)
            ->findOrFail($legId);

        return response()->json([
            'status' => 'success',
            'data'   => $leg,
        ]);
    }

    /**
     * Get all tracking updates for a request.
     *
     * GET /api/v1/interstate/driver/request/{requestId}/tracking
     */
    public function trackingUpdates(Request $request, int $requestId)
    {
        $driver = $this->resolveDriver();

        // Verify driver has a leg in this request
        $hasAccess = RequestLeg::where('provider_type', 'App\\Models\\Admin\\Driver')
            ->where('provider_id', $driver->id)
            ->where('request_id', $requestId)
            ->exists();

        if (!$hasAccess) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $updates = TrackingUpdate::where('request_id', $requestId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $updates,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // POST – Action Endpoints
    // ─────────────────────────────────────────────────────────

    /**
     * Accept a leg assignment.
     *
     * POST /api/v1/interstate/driver/leg/{legId}/accept
     */
    public function acceptLeg(Request $request, int $legId)
    {
        $driver = $this->resolveDriver();

        $leg = RequestLeg::where('provider_id', $driver->id)
            ->where('provider_type', 'App\\Models\\Admin\\Driver')
            ->where('status', 'pending')
            ->findOrFail($legId);

        DB::transaction(function () use ($leg, $driver) {
            $leg->update(['status' => 'accepted', 'accepted_at' => now()]);

            TrackingUpdate::createStatusChange(
                requestId: $leg->request_id,
                previousStatus: 'pending',
                newStatus: 'accepted',
                message: "Driver {$driver->name} accepted the {$leg->leg_type} leg.",
                createdByType: 'driver',
                createdById: $driver->id
            );
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Leg accepted successfully.',
            'leg'     => $leg->fresh(),
        ]);
    }

    /**
     * Mark leg as "on the way to pickup".
     *
     * POST /api/v1/interstate/driver/leg/{legId}/en-route-pickup
     */
    public function enRoutePickup(Request $request, int $legId)
    {
        $driver = $this->resolveDriver();

        $leg = RequestLeg::where('provider_id', $driver->id)
            ->where('provider_type', 'App\\Models\\Admin\\Driver')
            ->where('status', 'accepted')
            ->findOrFail($legId);

        DB::transaction(function () use ($leg, $driver) {
            $leg->update(['status' => 'en_route_pickup']);

            TrackingUpdate::createStatusChange(
                requestId: $leg->request_id,
                previousStatus: 'accepted',
                newStatus: 'en_route_pickup',
                message: "Driver is on the way to pickup location.",
                createdByType: 'driver',
                createdById: $driver->id
            );
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Status updated — driver en route to pickup.',
        ]);
    }

    /**
     * Mark goods as picked up from sender (start of Leg 1).
     *
     * POST /api/v1/interstate/driver/leg/{legId}/pickup
     */
    public function pickup(Request $request, int $legId)
    {
        $validated = $request->validate([
            'latitude'  => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'notes'     => 'nullable|string|max:500',
        ]);

        $driver = $this->resolveDriver();

        $leg = RequestLeg::where('provider_id', $driver->id)
            ->where('provider_type', 'App\\Models\\Admin\\Driver')
            ->whereIn('status', ['accepted', 'en_route_pickup'])
            ->findOrFail($legId);

        DB::transaction(function () use ($leg, $driver, $validated) {
            $leg->update([
                'status'          => 'picked_up',
                'picked_up_at'    => now(),
                'pickup_location' => array_merge((array) ($leg->pickup_location ?? []), [
                    'lat'   => $validated['latitude'] ?? null,
                    'lng'   => $validated['longitude'] ?? null,
                    'time'  => now()->toIso8601String(),
                ]),
            ]);

            TrackingUpdate::createStatusChange(
                requestId: $leg->request_id,
                previousStatus: 'accepted',
                newStatus: 'picked_up',
                message: "Goods picked up from sender. " . ($validated['notes'] ?? ''),
                createdByType: 'driver',
                createdById: $driver->id
            );
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Pickup confirmed.',
        ]);
    }

    /**
     * Mark goods as arrived at hub (end of Leg 1 / start of Leg 2).
     *
     * POST /api/v1/interstate/driver/leg/{legId}/arrived-at-hub
     */
    public function arrivedAtHub(Request $request, int $legId)
    {
        $validated = $request->validate([
            'hub_id'    => 'required|exists:trucking_hubs,id',
            'latitude'  => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'notes'     => 'nullable|string|max:500',
        ]);

        $driver = $this->resolveDriver();

        $leg = RequestLeg::where('provider_id', $driver->id)
            ->where('provider_type', 'App\\Models\\Admin\\Driver')
            ->where('status', 'picked_up')
            ->findOrFail($legId);

        DB::transaction(function () use ($leg, $driver, $validated) {
            $leg->update([
                'status'          => 'arrived_at_hub',
                'arrived_at_hub_at' => now(),
            ]);

            TrackingUpdate::createStatusChange(
                requestId: $leg->request_id,
                previousStatus: 'picked_up',
                newStatus: 'arrived_at_hub',
                message: "Goods arrived at hub. " . ($validated['notes'] ?? ''),
                createdByType: 'driver',
                createdById: $driver->id
            );
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Arrived at hub confirmed.',
        ]);
    }

    /**
     * Confirm handoff at hub (complete Leg 1 & 2, hand over to trucking company).
     *
     * POST /api/v1/interstate/driver/leg/{legId}/handoff
     */
    public function confirmHandoff(Request $request, int $legId)
    {
        $validated = $request->validate([
            'hub_staff_name'    => 'nullable|string|max:255',
            'handoff_reference' => 'nullable|string|max:100',
            'notes'             => 'nullable|string|max:500',
        ]);

        $driver = $this->resolveDriver();

        $leg = RequestLeg::where('provider_id', $driver->id)
            ->where('provider_type', 'App\\Models\\Admin\\Driver')
            ->where('status', 'arrived_at_hub')
            ->findOrFail($legId);

        DB::transaction(function () use ($leg, $driver, $validated) {
            $leg->update([
                'status'           => 'completed',
                'completed_at'     => now(),
                'handoff_details'  => $validated,
            ]);

            TrackingUpdate::createStatusChange(
                requestId: $leg->request_id,
                previousStatus: 'arrived_at_hub',
                newStatus: 'hub_handoff_completed',
                message: "Goods handed off to hub staff. Ref: " . ($validated['handoff_reference'] ?? 'N/A'),
                createdByType: 'driver',
                createdById: $driver->id
            );

            // Advance the request: trucking company now handles Leg 3
            $interstateRequest = $leg->interstateRequest;
            if ($interstateRequest) {
                $interstateRequest->update(['current_leg_number' => 3]);
            }
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Handoff confirmed. Goods are now with the trucking company.',
        ]);
    }

    /**
     * Confirm collection from destination hub (start of Leg 4/5).
     *
     * POST /api/v1/interstate/driver/leg/{legId}/collect-from-hub
     */
    public function collectFromHub(Request $request, int $legId)
    {
        $validated = $request->validate([
            'hub_staff_name' => 'nullable|string|max:255',
            'notes'          => 'nullable|string|max:500',
        ]);

        $driver = $this->resolveDriver();

        $leg = RequestLeg::where('provider_id', $driver->id)
            ->where('provider_type', 'App\\Models\\Admin\\Driver')
            ->whereIn('status', ['pending', 'accepted'])
            ->whereIn('leg_type', ['hub_pickup', 'local_delivery'])
            ->findOrFail($legId);

        DB::transaction(function () use ($leg, $driver, $validated) {
            $leg->update([
                'status'           => 'collected_from_hub',
                'collected_at'     => now(),
            ]);

            TrackingUpdate::createStatusChange(
                requestId: $leg->request_id,
                previousStatus: 'pending',
                newStatus: 'collected_from_hub',
                message: "Goods collected from destination hub. Last-mile delivery in progress.",
                createdByType: 'driver',
                createdById: $driver->id
            );
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Goods collected from hub. Proceed to delivery address.',
        ]);
    }

    /**
     * Mark final delivery as complete (end of Leg 5).
     *
     * POST /api/v1/interstate/driver/leg/{legId}/delivered
     */
    public function markDelivered(Request $request, int $legId)
    {
        $validated = $request->validate([
            'recipient_name'      => 'required|string|max:255',
            'recipient_signature' => 'nullable|string', // base64 image
            'proof_photo'         => 'nullable|string', // base64 image
            'notes'               => 'nullable|string|max:500',
            'latitude'            => 'nullable|numeric',
            'longitude'           => 'nullable|numeric',
        ]);

        $driver = $this->resolveDriver();

        $leg = RequestLeg::where('provider_id', $driver->id)
            ->where('provider_type', 'App\\Models\\Admin\\Driver')
            ->where('leg_type', 'local_delivery')
            ->whereIn('status', ['collected_from_hub', 'en_route_delivery'])
            ->findOrFail($legId);

        DB::transaction(function () use ($leg, $driver, $validated) {
            $leg->update([
                'status'           => 'completed',
                'completed_at'     => now(),
                'delivery_proof'   => [
                    'recipient_name'      => $validated['recipient_name'],
                    'recipient_signature' => $validated['recipient_signature'] ?? null,
                    'proof_photo'         => $validated['proof_photo'] ?? null,
                    'delivered_at'        => now()->toIso8601String(),
                    'latitude'            => $validated['latitude'] ?? null,
                    'longitude'           => $validated['longitude'] ?? null,
                    'notes'               => $validated['notes'] ?? null,
                ],
            ]);

            // Mark the entire interstate request as delivered
            $interstateRequest = $leg->interstateRequest;
            if ($interstateRequest) {
                $interstateRequest->update([
                    'interstate_status' => 'delivered',
                    'completed_at'      => now(),
                ]);

                TrackingUpdate::createStatusChange(
                    requestId: $leg->request_id,
                    previousStatus: 'last_mile_delivery',
                    newStatus: 'delivered',
                    message: "Package successfully delivered to {$validated['recipient_name']}.",
                    createdByType: 'driver',
                    createdById: $driver->id
                );
            }
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Delivery confirmed! The shipment is complete.',
        ]);
    }

    /**
     * Post a location update during active delivery.
     *
     * POST /api/v1/interstate/driver/leg/{legId}/location
     */
    public function updateLocation(Request $request, int $legId)
    {
        $validated = $request->validate([
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
            'speed_kmh' => 'nullable|numeric',
            'heading'   => 'nullable|numeric',
        ]);

        $driver = $this->resolveDriver();

        $leg = RequestLeg::where('provider_id', $driver->id)
            ->where('provider_type', 'App\\Models\\Admin\\Driver')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->findOrFail($legId);

        // Store location snapshot
        $leg->update([
            'last_known_lat' => $validated['latitude'],
            'last_known_lng' => $validated['longitude'],
            'last_location_updated_at' => now(),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Location updated.',
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────

    /**
     * Resolve the authenticated driver from the API guard.
     * Throws 401 if not authenticated.
     */
    private function resolveDriver()
    {
        $driver = Auth::guard('api')->user();

        if (!$driver) {
            abort(401, 'Unauthenticated driver.');
        }

        return $driver;
    }
}
