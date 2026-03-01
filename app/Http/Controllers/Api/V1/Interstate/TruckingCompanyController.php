<?php

namespace App\Http\Controllers\Api\V1\Interstate;

use App\Http\Controllers\Api\V1\BaseController;
use App\Services\Interstate\LegOrchestrationService;
use App\Models\Interstate\RequestLeg;
use App\Models\Interstate\HubInventory;
use App\Models\Interstate\TruckingCompany;
use App\Models\Interstate\SupportedRoute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Controller for Trucking Company operations
 * Handles weight verification, hub operations, and leg management
 */
class TruckingCompanyController extends BaseController
{
    public function __construct(
        private LegOrchestrationService $legOrchestrationService
    ) {}

    /**
     * Get trucking company dashboard
     */
    public function dashboard()
    {
        $company = $this->getAuthenticatedCompany();
        
        if (!$company) {
            return $this->respondForbidden('Trucking company not found');
        }

        // Get pending legs (interstate transport)
        $pendingLegs = RequestLeg::where('provider_type', 'App\Models\Interstate\TruckingCompany')
            ->where('provider_id', $company->id)
            ->where('leg_type', 'interstate_transport')
            ->where('status', 'pending')
            ->count();

        // Get active/in-transit legs
        $activeLegs = RequestLeg::where('provider_type', 'App\Models\Interstate\TruckingCompany')
            ->where('provider_id', $company->id)
            ->where('leg_type', 'interstate_transport')
            ->whereIn('status', ['accepted', 'picked_up', 'in_transit'])
            ->count();

        // Get completed today
        $completedToday = RequestLeg::where('provider_type', 'App\Models\Interstate\TruckingCompany')
            ->where('provider_id', $company->id)
            ->where('leg_type', 'interstate_transport')
            ->where('status', 'completed')
            ->whereDate('completed_at', today())
            ->count();

        // Get inventory at all hubs
        $hubInventoryCount = HubInventory::whereHas('hub', function ($query) use ($company) {
            $query->where('trucking_company_id', $company->id);
        })->whereIn('status', ['received', 'stored'])->count();

        // Get revenue summary for current month
        $monthlyRevenue = RequestLeg::where('provider_type', 'App\Models\Interstate\TruckingCompany')
            ->where('provider_id', $company->id)
            ->where('leg_type', 'interstate_transport')
            ->whereMonth('completed_at', now()->month)
            ->whereYear('completed_at', now()->year)
            ->sum('provider_earnings') ?? 0;

        return $this->respondSuccess([
            'company' => [
                'id' => $company->id,
                'name' => $company->company_name,
                'rating' => $company->rating,
                'status' => $company->status,
            ],
            'stats' => [
                'pending_legs' => $pendingLegs,
                'active_legs' => $activeLegs,
                'completed_today' => $completedToday,
                'hub_inventory' => $hubInventoryCount,
                'monthly_revenue' => round($monthlyRevenue, 2),
            ],
            'hubs' => $company->hubs()->where('is_active', true)->get()->map(fn($hub) => [
                'id' => $hub->id,
                'name' => $hub->hub_name,
                'city' => $hub->city,
                'inventory_count' => HubInventory::where('hub_id', $hub->id)
                    ->whereIn('status', ['received', 'stored'])
                    ->count(),
            ]),
        ]);
    }

    /**
     * Get company profile
     */
    public function getProfile()
    {
        $company = $this->getAuthenticatedCompany();
        
        if (!$company) {
            return $this->respondForbidden('Trucking company not found');
        }

        return $this->respondSuccess([
            'company' => $company->toArray(),
            'hubs_count' => $company->hubs()->count(),
            'routes_count' => $company->routes()->count(),
        ]);
    }

    /**
     * Update company profile
     */
    public function updateProfile(Request $request)
    {
        $company = $this->getAuthenticatedCompany();
        
        if (!$company) {
            return $this->respondForbidden('Trucking company not found');
        }

        $validator = Validator::make($request->all(), [
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'operating_hours' => 'nullable|array',
            'fleet_size' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->respondWithValidationErrors($validator);
        }

        $company->update($request->only(['phone', 'email', 'operating_hours', 'fleet_size']));

        return $this->respondSuccess($company->fresh(), 'Profile updated successfully');
    }

    /**
     * Get all hubs for the company
     */
    public function getHubs()
    {
        $company = $this->getAuthenticatedCompany();
        
        if (!$company) {
            return $this->respondForbidden('Trucking company not found');
        }

        $hubs = $company->hubs()->withCount([
            'inventory as inventory_count' => function ($query) {
                $query->whereIn('status', ['received', 'stored']);
            }
        ])->get();

        return $this->respondSuccess($hubs);
    }

    /**
     * Get hub details
     */
    public function getHubDetails(string $hubId)
    {
        $company = $this->getAuthenticatedCompany();
        
        if (!$company) {
            return $this->respondForbidden('Trucking company not found');
        }

        $hub = $company->hubs()->findOrFail($hubId);

        return $this->respondSuccess([
            'hub' => $hub,
            'inventory_count' => HubInventory::where('hub_id', $hubId)
                ->whereIn('status', ['received', 'stored'])
                ->count(),
            'today_arrivals' => HubInventory::where('hub_id', $hubId)
                ->whereDate('received_at', today())
                ->count(),
            'today_dispatches' => HubInventory::where('hub_id', $hubId)
                ->where('status', 'dispatched')
                ->whereDate('dispatched_at', today())
                ->count(),
        ]);
    }

    /**
     * Get hub inventory
     */
    public function getHubInventory(string $hubId)
    {
        $company = $this->getAuthenticatedCompany();
        
        if (!$company) {
            return $this->respondForbidden('Trucking company not found');
        }

        $hub = $company->hubs()->findOrFail($hubId);

        $inventory = HubInventory::with(['request', 'request.user', 'request.packages'])
            ->where('hub_id', $hubId)
            ->whereIn('status', ['received', 'stored'])
            ->orderBy('received_at', 'desc')
            ->get()
            ->map(fn($item) => [
                'id' => $item->id,
                'request_number' => $item->request->request_number,
                'customer_name' => $item->request->user->name ?? 'Unknown',
                'customer_phone' => $item->request->user->phone ?? 'N/A',
                'packages_count' => $item->request->packages->count(),
                'total_weight' => $item->request->packages->sum('chargeable_weight_kg'),
                'storage_location' => $item->storage_location,
                'status' => $item->status,
                'received_at' => $item->received_at,
                'expected_departure' => $item->expected_departure_at,
            ]);

        return $this->respondSuccess([
            'hub' => [
                'id' => $hub->id,
                'name' => $hub->hub_name,
                'city' => $hub->city,
            ],
            'inventory' => $inventory,
            'total_items' => $inventory->count(),
        ]);
    }

    /**
     * Check in package at hub
     */
    public function checkInPackage(string $hubId, Request $request)
    {
        $company = $this->getAuthenticatedCompany();
        
        if (!$company) {
            return $this->respondForbidden('Trucking company not found');
        }

        $validator = Validator::make($request->all(), [
            'request_leg_id' => 'required|exists:request_legs,id',
            'storage_location' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->respondWithValidationErrors($validator);
        }

        $hub = $company->hubs()->findOrFail($hubId);
        $leg = RequestLeg::findOrFail($request->input('request_leg_id'));

        // Verify this leg belongs to this hub
        if (($leg->drop_location['hub_id'] ?? null) != $hubId) {
            return $this->respondError('This leg is not destined for this hub', 400);
        }

        DB::transaction(function () use ($hubId, $leg, $request) {
            // Create or update inventory record
            HubInventory::updateOrCreate(
                [
                    'hub_id' => $hubId,
                    'request_id' => $leg->request_id,
                    'request_leg_id' => $leg->id,
                ],
                [
                    'status' => 'stored',
                    'received_at' => now(),
                    'received_by' => auth()->id(),
                    'storage_location' => $request->input('storage_location'),
                ]
            );

            // Update leg status
            $leg->update(['status' => 'completed']);
        });

        return $this->respondSuccess(null, 'Package checked in successfully');
    }

    /**
     * Check out package from hub
     */
    public function checkOutPackage(string $hubId, Request $request)
    {
        $company = $this->getAuthenticatedCompany();
        
        if (!$company) {
            return $this->respondForbidden('Trucking company not found');
        }

        $validator = Validator::make($request->all(), [
            'inventory_id' => 'required|exists:hub_inventory,id',
        ]);

        if ($validator->fails()) {
            return $this->respondWithValidationErrors($validator);
        }

        $hub = $company->hubs()->findOrFail($hubId);
        
        $inventory = HubInventory::where('hub_id', $hubId)
            ->findOrFail($request->input('inventory_id'));

        $inventory->update([
            'status' => 'dispatched',
            'dispatched_at' => now(),
            'dispatched_by' => auth()->id(),
        ]);

        return $this->respondSuccess(null, 'Package checked out successfully');
    }

    /**
     * Get all routes for the company
     */
    public function getRoutes()
    {
        $company = $this->getAuthenticatedCompany();
        
        if (!$company) {
            return $this->respondForbidden('Trucking company not found');
        }

        $routes = $company->routes()
            ->with(['originHub', 'destinationHub'])
            ->get()
            ->map(fn($route) => [
                'id' => $route->id,
                'route_code' => $route->route_code,
                'origin' => [
                    'hub_name' => $route->originHub->hub_name,
                    'city' => $route->originHub->city,
                ],
                'destination' => [
                    'hub_name' => $route->destinationHub->hub_name,
                    'city' => $route->destinationHub->city,
                ],
                'pricing' => [
                    'price_per_kg' => $route->price_per_kg,
                    'minimum_charge' => $route->minimum_charge,
                ],
                'is_active' => $route->is_active,
            ]);

        return $this->respondSuccess($routes);
    }

    /**
     * Get route details
     */
    public function getRouteDetails(string $routeId)
    {
        $company = $this->getAuthenticatedCompany();
        
        if (!$company) {
            return $this->respondForbidden('Trucking company not found');
        }

        $route = $company->routes()
            ->with(['originHub', 'destinationHub'])
            ->findOrFail($routeId);

        return $this->respondSuccess($route);
    }

    /**
     * Update route pricing
     */
    public function updateRoutePricing(string $routeId, Request $request)
    {
        $company = $this->getAuthenticatedCompany();
        
        if (!$company) {
            return $this->respondForbidden('Trucking company not found');
        }

        $validator = Validator::make($request->all(), [
            'price_per_kg' => 'nullable|numeric|min:0',
            'minimum_charge' => 'nullable|numeric|min:0',
            'express_surcharge_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return $this->respondWithValidationErrors($validator);
        }

        $route = $company->routes()->findOrFail($routeId);
        $route->update($request->only([
            'price_per_kg',
            'minimum_charge',
            'express_surcharge_percent',
        ]));

        return $this->respondSuccess($route->fresh(), 'Route pricing updated');
    }

    /**
     * Get pending legs (interstate transport)
     */
    public function getPendingLegs()
    {
        $company = $this->getAuthenticatedCompany();
        
        if (!$company) {
            return $this->respondForbidden('Trucking company not found');
        }

        $legs = RequestLeg::with(['request', 'request.user', 'request.packages'])
            ->where('provider_type', 'App\Models\Interstate\TruckingCompany')
            ->where('provider_id', $company->id)
            ->where('leg_type', 'interstate_transport')
            ->where('status', 'pending')
            ->get()
            ->map(fn($leg) => $this->formatLegForResponse($leg));

        return $this->respondSuccess($legs);
    }

    /**
     * Get active legs
     */
    public function getActiveLegs()
    {
        $company = $this->getAuthenticatedCompany();
        
        if (!$company) {
            return $this->respondForbidden('Trucking company not found');
        }

        $legs = RequestLeg::with(['request', 'request.user', 'request.packages'])
            ->where('provider_type', 'App\Models\Interstate\TruckingCompany')
            ->where('provider_id', $company->id)
            ->where('leg_type', 'interstate_transport')
            ->whereIn('status', ['accepted', 'picked_up', 'in_transit'])
            ->get()
            ->map(fn($leg) => $this->formatLegForResponse($leg));

        return $this->respondSuccess($legs);
    }

    /**
     * Get completed legs
     */
    public function getCompletedLegs()
    {
        $company = $this->getAuthenticatedCompany();
        
        if (!$company) {
            return $this->respondForbidden('Trucking company not found');
        }

        $legs = RequestLeg::with(['request', 'request.user', 'request.packages'])
            ->where('provider_type', 'App\Models\Interstate\TruckingCompany')
            ->where('provider_id', $company->id)
            ->where('leg_type', 'interstate_transport')
            ->where('status', 'completed')
            ->orderBy('completed_at', 'desc')
            ->paginate(20);

        return $this->respondSuccess([
            'data' => $legs->map(fn($leg) => $this->formatLegForResponse($leg)),
            'pagination' => [
                'current_page' => $legs->currentPage(),
                'last_page' => $legs->lastPage(),
                'per_page' => $legs->perPage(),
                'total' => $legs->total(),
            ],
        ]);
    }

    /**
     * Accept a leg (interstate transport)
     */
    public function acceptLeg(string $legId)
    {
        $company = $this->getAuthenticatedCompany();
        
        if (!$company) {
            return $this->respondForbidden('Trucking company not found');
        }

        $leg = RequestLeg::where('id', $legId)
            ->where('provider_id', $company->id)
            ->where('provider_type', 'App\Models\Interstate\TruckingCompany')
            ->firstOrFail();

        $leg->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        return $this->respondSuccess([
            'leg_id' => $leg->id,
            'status' => $leg->status,
            'accepted_at' => $leg->accepted_at,
        ], 'Leg accepted successfully');
    }

    /**
     * Mark leg as picked up from origin hub
     */
    public function markPickedUp(string $legId)
    {
        $company = $this->getAuthenticatedCompany();
        
        if (!$company) {
            return $this->respondForbidden('Trucking company not found');
        }

        $leg = RequestLeg::where('id', $legId)
            ->where('provider_id', $company->id)
            ->where('provider_type', 'App\Models\Interstate\TruckingCompany')
            ->firstOrFail();

        $leg->update([
            'status' => 'picked_up',
            'picked_up_at' => now(),
        ]);

        return $this->respondSuccess([
            'leg_id' => $leg->id,
            'status' => $leg->status,
            'picked_up_at' => $leg->picked_up_at,
        ]);
    }

    /**
     * Mark leg as in transit
     */
    public function markInTransit(string $legId)
    {
        $company = $this->getAuthenticatedCompany();
        
        if (!$company) {
            return $this->respondForbidden('Trucking company not found');
        }

        $leg = RequestLeg::where('id', $legId)
            ->where('provider_id', $company->id)
            ->where('provider_type', 'App\Models\Interstate\TruckingCompany')
            ->firstOrFail();

        $leg->update(['status' => 'in_transit']);

        return $this->respondSuccess([
            'leg_id' => $leg->id,
            'status' => $leg->status,
        ]);
    }

    /**
     * Mark leg as arrived at destination hub
     */
    public function markArrived(string $legId)
    {
        $company = $this->getAuthenticatedCompany();
        
        if (!$company) {
            return $this->respondForbidden('Trucking company not found');
        }

        $leg = RequestLeg::where('id', $legId)
            ->where('provider_id', $company->id)
            ->where('provider_type', 'App\Models\Interstate\TruckingCompany')
            ->firstOrFail();

        $leg->update(['status' => 'arrived_at_destination']);

        return $this->respondSuccess([
            'leg_id' => $leg->id,
            'status' => $leg->status,
        ]);
    }

    /**
     * Complete leg with weight verification
     * THIS IS THE KEY METHOD for weight verification flow
     */
    public function markComplete(string $legId, Request $request)
    {
        $company = $this->getAuthenticatedCompany();
        
        if (!$company) {
            return $this->respondForbidden('Trucking company not found');
        }

        $validator = Validator::make($request->all(), [
            'verified_chargeable_weight' => 'required|numeric|min:0.1',
            'verification_method' => 'required|in:scale,dimension_calculation,visual_estimate',
            'verification_notes' => 'nullable|string|max:500',
            'photos' => 'nullable|array',
            'photos.*' => 'string|url',
        ]);

        if ($validator->fails()) {
            return $this->respondWithValidationErrors($validator);
        }

        $leg = RequestLeg::with(['request', 'request.packages'])
            ->where('id', $legId)
            ->where('provider_id', $company->id)
            ->where('provider_type', 'App\Models\Interstate\TruckingCompany')
            ->firstOrFail();

        $verifiedWeight = $request->input('verified_chargeable_weight');
        $originalWeight = $leg->total_chargeable_weight;
        $weightDifference = $verifiedWeight - $originalWeight;

        // Prepare verification data
        $verificationData = [
            'verified_chargeable_weight' => $verifiedWeight,
            'original_chargeable_weight' => $originalWeight,
            'weight_difference' => $weightDifference,
            'verification_method' => $request->input('verification_method'),
            'verification_notes' => $request->input('verification_notes'),
            'verified_by' => auth()->id(),
            'verified_at' => now()->toIso8601String(),
            'photos' => $request->input('photos', []),
        ];

        // Process weight verification through orchestration service
        $this->legOrchestrationService->processWeightVerification(
            $leg,
            $verifiedWeight,
            $verificationData
        );

        // Complete the leg
        $this->legOrchestrationService->completeLeg($leg, [], $verificationData);

        // Determine if additional payment is needed
        $paymentRequired = $weightDifference > 0;
        $priceAdjustment = $this->calculatePriceAdjustment($leg, $verifiedWeight);

        Log::info("Leg completed with weight verification", [
            'leg_id' => $legId,
            'request_number' => $leg->request->request_number,
            'original_weight' => $originalWeight,
            'verified_weight' => $verifiedWeight,
            'difference' => $weightDifference,
            'price_adjustment' => $priceAdjustment,
        ]);

        return $this->respondSuccess([
            'leg_id' => $leg->id,
            'status' => 'completed',
            'weight_verification' => [
                'original_weight' => $originalWeight,
                'verified_weight' => $verifiedWeight,
                'difference' => $weightDifference,
                'difference_percentage' => $originalWeight > 0 
                    ? round(($weightDifference / $originalWeight) * 100, 2) 
                    : 0,
            ],
            'price_adjustment' => [
                'adjustment_required' => $paymentRequired,
                'adjustment_amount' => $priceAdjustment,
                'new_total' => $leg->final_fare + $priceAdjustment,
            ],
            'next_steps' => $paymentRequired 
                ? 'Customer will be notified to pay additional amount before next leg'
                : 'Next leg will be activated automatically',
        ], 'Leg completed successfully. Weight verified and customer will be notified.');
    }

    /**
     * Update location during transit
     */
    public function updateLocation(string $legId, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return $this->respondWithValidationErrors($validator);
        }

        $company = $this->getAuthenticatedCompany();
        
        if (!$company) {
            return $this->respondForbidden('Trucking company not found');
        }

        $leg = RequestLeg::where('id', $legId)
            ->where('provider_id', $company->id)
            ->where('provider_type', 'App\Models\Interstate\TruckingCompany')
            ->firstOrFail();

        $leg->updateLocation(
            $request->input('latitude'),
            $request->input('longitude')
        );

        return $this->respondSuccess([
            'leg_id' => $leg->id,
            'current_location' => [
                'lat' => $leg->current_lat,
                'lng' => $leg->current_lng,
            ],
            'updated_at' => $leg->updated_at,
        ]);
    }

    /**
     * Upload proof of delivery/pickup
     */
    public function uploadProof(string $legId, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:pickup,delivery',
            'photos' => 'required|array',
            'photos.*' => 'string|url',
            'signature' => 'nullable|string',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->respondWithValidationErrors($validator);
        }

        $company = $this->getAuthenticatedCompany();
        
        if (!$company) {
            return $this->respondForbidden('Trucking company not found');
        }

        $leg = RequestLeg::where('id', $legId)
            ->where('provider_id', $company->id)
            ->where('provider_type', 'App\Models\Interstate\TruckingCompany')
            ->firstOrFail();

        $proofData = [
            'type' => $request->input('type'),
            'photos' => $request->input('photos'),
            'signature' => $request->input('signature'),
            'notes' => $request->input('notes'),
            'uploaded_at' => now()->toIso8601String(),
        ];

        if ($request->input('type') === 'pickup') {
            $leg->addPickupProof($proofData);
        } else {
            $leg->addDeliveryProof($proofData);
        }

        return $this->respondSuccess(null, 'Proof uploaded successfully');
    }

    /**
     * Get analytics summary
     */
    public function getAnalyticsSummary()
    {
        $company = $this->getAuthenticatedCompany();
        
        if (!$company) {
            return $this->respondForbidden('Trucking company not found');
        }

        // Monthly stats for last 6 months
        $monthlyStats = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthlyStats[] = [
                'month' => $date->format('Y-m'),
                'month_name' => $date->format('F Y'),
                'shipments' => RequestLeg::where('provider_id', $company->id)
                    ->where('leg_type', 'interstate_transport')
                    ->whereMonth('completed_at', $date->month)
                    ->whereYear('completed_at', $date->year)
                    ->count(),
                'revenue' => RequestLeg::where('provider_id', $company->id)
                    ->where('leg_type', 'interstate_transport')
                    ->whereMonth('completed_at', $date->month)
                    ->whereYear('completed_at', $date->year)
                    ->sum('provider_earnings') ?? 0,
            ];
        }

        return $this->respondSuccess([
            'monthly_stats' => $monthlyStats,
            'total_shipments' => RequestLeg::where('provider_id', $company->id)
                ->where('leg_type', 'interstate_transport')
                ->where('status', 'completed')
                ->count(),
            'total_revenue' => RequestLeg::where('provider_id', $company->id)
                ->where('leg_type', 'interstate_transport')
                ->where('status', 'completed')
                ->sum('provider_earnings') ?? 0,
        ]);
    }

    /**
     * Helper: Get authenticated trucking company
     */
    private function getAuthenticatedCompany(): ?TruckingCompany
    {
        $user = auth()->user();
        
        if (!$user) {
            return null;
        }

        // Assuming trucking company is linked to user
        return TruckingCompany::where('user_id', $user->id)->first();
    }

    /**
     * Helper: Format leg for response
     */
    private function formatLegForResponse(RequestLeg $leg): array
    {
        return [
            'id' => $leg->id,
            'leg_number' => $leg->leg_number,
            'request_number' => $leg->request->request_number,
            'status' => $leg->status,
            'customer' => [
                'name' => $leg->request->user->name ?? 'Unknown',
                'phone' => $leg->request->user->phone ?? 'N/A',
            ],
            'pickup' => $leg->pickup_location,
            'drop' => $leg->drop_location,
            'packages' => [
                'count' => $leg->request->packages->count(),
                'total_weight' => $leg->total_chargeable_weight,
            ],
            'financial' => [
                'final_fare' => $leg->final_fare,
                'provider_earnings' => $leg->provider_earnings,
            ],
            'timestamps' => [
                'accepted_at' => $leg->accepted_at,
                'picked_up_at' => $leg->picked_up_at,
                'completed_at' => $leg->completed_at,
            ],
        ];
    }

    /**
     * Helper: Calculate price adjustment based on weight difference
     */
    private function calculatePriceAdjustment(RequestLeg $leg, float $verifiedWeight): float
    {
        $route = SupportedRoute::find($leg->supported_route_id);
        
        if (!$route) {
            return 0;
        }

        $originalWeight = $leg->total_chargeable_weight;
        $weightDifference = $verifiedWeight - $originalWeight;

        if ($weightDifference <= 0) {
            return 0;
        }

        // Calculate additional charge
        $additionalCharge = $weightDifference * $route->price_per_kg;

        // Check if minimum charge applies
        $newTotal = ($originalWeight * $route->price_per_kg) + $additionalCharge;
        if ($newTotal < $route->minimum_charge) {
            $additionalCharge = $route->minimum_charge - ($originalWeight * $route->price_per_kg);
        }

        return round($additionalCharge, 2);
    }

    /**
     * Helper: Respond with validation errors
     */
    protected function respondWithValidationErrors($validator)
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }
}
