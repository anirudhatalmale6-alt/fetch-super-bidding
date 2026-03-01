<?php

namespace App\Http\Controllers\Api\V1\Interstate;

use App\Http\Controllers\Api\V1\BaseController;
use App\Services\Interstate\InterstateRequestService;
use App\Models\Request\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Validator;

class InterstateDeliveryController extends BaseController
{
    public function __construct(
        private InterstateRequestService $requestService
    ) {}

    /**
     * Create a new interstate delivery request
     * 
     * POST /api/v1/interstate/delivery/request
     */
    public function createRequest(HttpRequest $request)
    {
        $validator = Validator::make($request->all(), [
            'route_id' => 'required|exists:supported_routes,id',
            'pick_address' => 'required|string|max:500',
            'pick_lat' => 'required|numeric|between:-90,90',
            'pick_lng' => 'required|numeric|between:-180,180',
            'drop_address' => 'required|string|max:500',
            'drop_lat' => 'required|numeric|between:-90,90',
            'drop_lng' => 'required|numeric|between:-180,180',
            'packages' => 'required|array|min:1',
            // Estimated values from user
            'packages.*.estimated_weight_kg' => 'required|numeric|min:0.1|max:1000',
            'packages.*.estimated_length_cm' => 'required|numeric|min:1|max:500',
            'packages.*.estimated_width_cm' => 'required|numeric|min:1|max:500',
            'packages.*.estimated_height_cm' => 'required|numeric|min:1|max:500',
            'packages.*.estimated_declared_value' => 'nullable|numeric|min:0',
            'packages.*.quantity' => 'integer|min:1|max:100',
            'packages.*.description' => 'nullable|string|max:255',
            'packages.*.is_fragile' => 'boolean',
            'packages.*.requires_insurance' => 'boolean',
            'packages.*.special_instructions' => 'nullable|string|max:500',
            'service_type' => 'in:standard,express',
            'requires_insurance' => 'boolean',
            'preferred_pickup_time' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return $this->respondWithValidationErrors($validator);
        }

        try {
            $data = $request->all();
            $data['user_id'] = auth()->id();
            $data['service_type'] = $data['service_type'] ?? 'standard';

            $interstateRequest = $this->requestService->createInterstateRequest($data);
            
            // Notify eligible trucking companies about new bidding opportunity
            $this->notifyEligibleCompanies($interstateRequest);

            return $this->respondSuccess([
                'request_id' => $interstateRequest->id,
                'request_number' => $interstateRequest->request_number,
                'tracking_number' => $interstateRequest->request_number,
                'status' => $interstateRequest->status,
                'is_bidding_phase' => true,
                'bidding_timeout_at' => $interstateRequest->bidding_timeout_at,
                'currency' => 'NGN',
                'route' => [
                    'origin_hub' => $interstateRequest->originHub->hub_name,
                    'destination_hub' => $interstateRequest->destinationHub->hub_name,
                ],
                'packages' => $interstateRequest->packages->map(fn($pkg) => [
                    'package_number' => $pkg->package_number,
                    'estimated_weight_kg' => $pkg->estimated_weight_kg,
                    'estimated_dimensions' => "{$pkg->estimated_length_cm} × {$pkg->estimated_width_cm} × {$pkg->estimated_height_cm} cm",
                    'estimated_declared_value' => $pkg->estimated_declared_value,
                    'quantity' => $pkg->quantity,
                ]),
                'next_step' => 'waiting_for_bids',
                'message' => 'Your request has been created and is now open for bidding by trucking companies.',
                'created_at' => $interstateRequest->created_at,
            ], 'Interstate delivery request created successfully. Waiting for company bids.');

        } catch (\InvalidArgumentException $e) {
            return $this->respondError($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->respondError('Failed to create request: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Notify eligible trucking companies about new bidding opportunity
     */
    private function notifyEligibleCompanies(Request $interstateRequest)
    {
        // Get companies that operate on this route
        $eligibleCompanies = \App\Models\Interstate\TruckingCompany::active()
            ->interstateTrucking()
            ->whereHas('routes', function ($query) use ($interstateRequest) {
                $query->where('id', $interstateRequest->supported_route_id);
            })
            ->get();
        
        foreach ($eligibleCompanies as $company) {
            if ($company->user) {
                $title = trans('push_notifications.new_interstate_bid_request_title', [], $company->user->lang);
                $body = trans('push_notifications.new_interstate_bid_request_body', [
                    'origin' => $interstateRequest->originHub->city,
                    'destination' => $interstateRequest->destinationHub->city,
                ], $company->user->lang);
                
                $pushData = [
                    'type' => 'new_bid_request',
                    'request_id' => $interstateRequest->id,
                    'request_number' => $interstateRequest->request_number,
                ];
                
                dispatch(new \App\Jobs\Notifications\SendPushNotification(
                    $company->user, 
                    $title, 
                    $body,
                    $pushData
                ));
            }
        }
    }

    /**
     * Get user's interstate delivery requests
     * 
     * GET /api/v1/interstate/delivery/requests
     */
    public function getUserRequests(HttpRequest $request)
    {
        $perPage = $request->input('per_page', 10);
        $status = $request->input('status');

        $query = Request::with(['packages', 'legs', 'truckingCompany', 'originHub', 'destinationHub'])
            ->where('user_id', auth()->id())
            ->where('delivery_mode', 'interstate')
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $requests = $query->paginate($perPage);

        return $this->respondSuccess([
            'data' => $requests->map(fn($req) => [
                'request_id' => $req->id,
                'request_number' => $req->request_number,
                'status' => $req->status,
                'total_amount' => $req->request_eta_amount,
                'trucking_company' => $req->truckingCompany ? [
                    'name' => $req->truckingCompany->company_name,
                    'logo' => $req->truckingCompany->logo,
                ] : null,
                'route' => [
                    'origin' => $req->originHub->city ?? 'Unknown',
                    'destination' => $req->destinationHub->city ?? 'Unknown',
                ],
                'packages_count' => $req->packages->count(),
                'current_leg' => $req->current_leg_number,
                'total_legs' => $req->total_legs,
                'progress_percentage' => ($req->current_leg_number / $req->total_legs) * 100,
                'created_at' => $req->created_at,
            ]),
            'pagination' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    /**
     * Get detailed request information
     * 
     * GET /api/v1/interstate/delivery/requests/{requestId}
     */
    public function getRequestDetails(string $requestId)
    {
        $request = Request::with([
            'packages',
            'legs.provider',
            'truckingCompany',
            'originHub',
            'destinationHub',
            'requestPlace'
        ])
            ->where('user_id', auth()->id())
            ->where('delivery_mode', 'interstate')
            ->findOrFail($requestId);

        return $this->respondSuccess([
            'request' => [
                'id' => $request->id,
                'request_number' => $request->request_number,
                'status' => $request->status,
                'created_at' => $request->created_at,
                'trip_start_time' => $request->trip_start_time,
            ],
            'locations' => [
                'pickup' => [
                    'address' => $request->requestPlace->pick_address,
                    'lat' => $request->requestPlace->pick_lat,
                    'lng' => $request->requestPlace->pick_lng,
                ],
                'dropoff' => [
                    'address' => $request->requestPlace->drop_address,
                    'lat' => $request->requestPlace->drop_lat,
                    'lng' => $request->requestPlace->drop_lng,
                ],
            ],
            'trucking_company' => $request->truckingCompany ? [
                'id' => $request->truckingCompany->id,
                'name' => $request->truckingCompany->company_name,
                'phone' => $request->truckingCompany->phone,
                'rating' => $request->truckingCompany->rating,
            ] : null,
            'route' => [
                'origin_hub' => [
                    'name' => $request->originHub->hub_name,
                    'address' => $request->originHub->address,
                    'city' => $request->originHub->city,
                ],
                'destination_hub' => [
                    'name' => $request->destinationHub->hub_name,
                    'address' => $request->destinationHub->address,
                    'city' => $request->destinationHub->city,
                ],
            ],
            'packages' => $request->packages->map(fn($pkg) => [
                'package_number' => $pkg->package_number,
                'description' => $pkg->description,
                'actual_weight_kg' => $pkg->actual_weight_kg,
                'dimensions_cm' => [
                    'length' => $pkg->length_cm,
                    'width' => $pkg->width_cm,
                    'height' => $pkg->height_cm,
                ],
                'volumetric_weight_kg' => $pkg->volumetric_weight_kg,
                'chargeable_weight_kg' => $pkg->chargeable_weight_kg,
                'quantity' => $pkg->quantity,
                'is_fragile' => $pkg->is_fragile,
                'declared_value' => $pkg->declared_value,
            ]),
            'legs' => $request->legs->map(fn($leg) => [
                'leg_number' => $leg->leg_number,
                'leg_type' => $leg->leg_type,
                'display_name' => $leg->display_name,
                'status' => $leg->status,
                'formatted_status' => $leg->formatted_status,
                'provider' => $leg->provider_name ? [
                    'name' => $leg->provider_name,
                    'phone' => $leg->provider_phone,
                ] : null,
                'pickup' => $leg->pickup_location,
                'drop' => $leg->drop_location,
                'chargeable_weight_kg' => $leg->total_chargeable_weight,
                'fare' => $leg->final_fare,
                'timestamps' => [
                    'accepted_at' => $leg->accepted_at,
                    'picked_up_at' => $leg->picked_up_at,
                    'completed_at' => $leg->completed_at,
                ],
            ]),
            'pricing' => [
                'local_pickup_fee' => $request->local_pickup_fee,
                'interstate_transport_fee' => $request->interstate_transport_fee,
                'local_delivery_fee' => $request->local_delivery_fee,
                'total' => $request->request_eta_amount,
            ],
            'current_leg' => $request->current_leg_number,
            'progress' => [
                'current' => $request->current_leg_number,
                'total' => $request->total_legs,
                'percentage' => ($request->current_leg_number / $request->total_legs) * 100,
            ],
        ]);
    }

    /**
     * Get tracking information for a request
     * 
     * GET /api/v1/interstate/delivery/tracking/{requestNumber}
     */
    public function getTracking(string $requestNumber)
    {
        $request = Request::with([
            'packages',
            'legs',
            'truckingCompany',
            'originHub',
            'destinationHub'
        ])
            ->where('request_number', $requestNumber)
            ->where('delivery_mode', 'interstate')
            ->firstOrFail();

        // Build timeline
        $timeline = $request->legs->map(function ($leg) {
            $events = [];
            
            if ($leg->status === 'pending') {
                $events[] = [
                    'status' => 'pending',
                    'time' => null,
                    'description' => 'Waiting for provider assignment',
                ];
            }
            
            if ($leg->accepted_at) {
                $events[] = [
                    'status' => 'accepted',
                    'time' => $leg->accepted_at,
                    'description' => 'Provider assigned: ' . $leg->provider_name,
                ];
            }
            
            if ($leg->picked_up_at) {
                $events[] = [
                    'status' => 'picked_up',
                    'time' => $leg->picked_up_at,
                    'description' => 'Package picked up from ' . ($leg->pickup_location['hub_name'] ?? 'sender'),
                ];
            }
            
            if ($leg->status === 'in_transit') {
                $events[] = [
                    'status' => 'in_transit',
                    'time' => $leg->picked_up_at,
                    'description' => 'In transit to destination',
                    'current_location' => $leg->current_lat ? [
                        'lat' => $leg->current_lat,
                        'lng' => $leg->current_lng,
                    ] : null,
                ];
            }
            
            if ($leg->completed_at) {
                $events[] = [
                    'status' => 'completed',
                    'time' => $leg->completed_at,
                    'description' => 'Delivered to ' . ($leg->drop_location['hub_name'] ?? 'recipient'),
                ];
            }

            return [
                'leg_number' => $leg->leg_number,
                'leg_type' => $leg->leg_type,
                'display_name' => $leg->display_name,
                'status' => $leg->status,
                'events' => $events,
            ];
        });

        return $this->respondSuccess([
            'request' => [
                'number' => $request->request_number,
                'status' => $request->status,
                'created_at' => $request->created_at,
                'estimated_delivery' => $this->calculateEstimatedDelivery($request),
            ],
            'trucking_company' => $request->truckingCompany ? [
                'name' => $request->truckingCompany->company_name,
                'phone' => $request->truckingCompany->phone,
                'rating' => $request->truckingCompany->rating,
            ] : null,
            'timeline' => $timeline,
            'current_location' => $this->getCurrentLocation($request),
            'progress' => [
                'current_leg' => $request->current_leg_number,
                'total_legs' => $request->total_legs,
                'percentage' => ($request->current_leg_number / $request->total_legs) * 100,
                'completed_legs' => $request->legs->where('status', 'completed')->count(),
            ],
            'packages' => $request->packages->map(fn($pkg) => [
                'number' => $pkg->package_number,
                'description' => $pkg->description,
                'weight' => $pkg->chargeable_weight_kg,
                'status' => $request->status,
            ]),
        ]);
    }

    /**
     * Cancel a request
     * 
     * POST /api/v1/interstate/delivery/cancel/{requestId}
     */
    public function cancelRequest(string $requestId, HttpRequest $request)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->respondWithValidationErrors($validator);
        }

        $interstateRequest = Request::where('user_id', auth()->id())
            ->where('delivery_mode', 'interstate')
            ->whereIn('status', ['pending', 'confirmed'])
            ->findOrFail($requestId);

        // Cancel all pending legs
        $interstateRequest->legs()
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        // Update request status
        $interstateRequest->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'reason' => $request->input('reason'),
        ]);

        // TODO: Process refund based on cancellation policy
        // - Full refund if cancelled before pickup
        // - Partial refund if after pickup but before interstate
        // - No refund after interstate departure

        return $this->respondSuccess([
            'request_id' => $interstateRequest->id,
            'status' => 'cancelled',
            'cancelled_at' => $interstateRequest->cancelled_at,
            'refund_status' => 'pending', // Would be calculated based on policy
        ], 'Request cancelled successfully');
    }

    /**
     * Calculate estimated delivery date/time
     */
    private function calculateEstimatedDelivery(Request $request): ?string
    {
        if (!$request->trip_start_time) {
            return null;
        }

        $route = $request->supportedRoute;
        if (!$route) {
            return null;
        }

        $hours = $request->service_type === 'express' 
            ? $route->express_sla_hours 
            : $route->standard_sla_hours;

        return $request->trip_start_time->copy()->addHours($hours)->toIso8601String();
    }

    /**
     * Get current location of shipment
     */
    private function getCurrentLocation(Request $request): ?array
    {
        $currentLeg = $request->legs()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->first();

        if (!$currentLeg || !$currentLeg->current_lat) {
            return null;
        }

        return [
            'lat' => $currentLeg->current_lat,
            'lng' => $currentLeg->current_lng,
            'leg_number' => $currentLeg->leg_number,
            'leg_type' => $currentLeg->leg_type,
            'updated_at' => $currentLeg->updated_at,
        ];
    }

    /**
     * Respond with validation errors
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
