<?php

namespace App\Http\Controllers\Api\V1\Interstate;

use App\Http\Controllers\Api\V1\BaseController;
use App\Services\Interstate\InterstateRequestService;
use App\Services\Interstate\InterstateRequestServiceV2;
use App\Models\Request\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Validator;

class InterstateDeliveryController extends BaseController
{
    public function __construct(
        private InterstateRequestService $requestService,
        private InterstateRequestServiceV2 $requestServiceV2
    ) {}

    /**
     * Create a new interstate delivery request
     *
     * POST /api/v1/interstate/delivery/request
     *
     * Supports three formats:
     * 1. V2 (Company Selection): trucking_company_id + pickup/destination details
     * 2. Legacy: route_id + pick/drop coordinates + packages array
     * 3. Structured (3-step Flutter form): pickup_state/destination_state + single package
     */
    public function createRequest(HttpRequest $request)
    {
        // V2 flow: user selected a company directly (no bidding phase)
        if ($request->has('trucking_company_id') && !$request->has('route_id')) {
            return $this->createRequestV2($request);
        }

        // Detect format: structured if pickup_state is present
        $isStructured = $request->has('pickup_state') || $request->has('destination_state');

        if ($isStructured) {
            return $this->createStructuredRequest($request);
        }

        $validator = Validator::make($request->all(), [
            'route_id' => 'required|exists:supported_routes,id',
            'pick_address' => 'required|string|max:500',
            'pick_lat' => 'required|numeric|between:-90,90',
            'pick_lng' => 'required|numeric|between:-180,180',
            'drop_address' => 'required|string|max:500',
            'drop_lat' => 'required|numeric|between:-90,90',
            'drop_lng' => 'required|numeric|between:-180,180',
            'packages' => 'required|array|min:1',
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

        return $this->processCreateRequest($request->all());
    }

    /**
     * Handle the structured 3-step Flutter form format
     */
    private function createStructuredRequest(HttpRequest $request)
    {
        $validator = Validator::make($request->all(), [
            'pickup_address' => 'required|string|max:500',
            'pickup_state' => 'required|string|max:100',
            'sender_phone' => 'required|string|max:20',
            'destination_address' => 'required|string|max:500',
            'destination_state' => 'required|string|max:100',
            'recipient_phone' => 'required|string|max:20',
            'estimated_weight_kg' => 'required|numeric|min:0.1|max:1000',
            'estimated_length_cm' => 'required|numeric|min:1|max:500',
            'estimated_width_cm' => 'required|numeric|min:1|max:500',
            'estimated_height_cm' => 'required|numeric|min:1|max:500',
            'estimated_goods_value' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->respondWithValidationErrors($validator);
        }

        // Validate origin != destination
        if (strtolower(trim($request->pickup_state)) === strtolower(trim($request->destination_state))) {
            return $this->respondError('Origin and destination states must be different for interstate delivery.', 422);
        }

        // Auto-match a route based on origin/destination states
        $route = \App\Models\Interstate\SupportedRoute::where('is_active', true)
            ->where(function ($q) use ($request) {
                $q->where('origin_state', 'LIKE', '%' . trim($request->pickup_state) . '%')
                  ->where('destination_state', 'LIKE', '%' . trim($request->destination_state) . '%');
            })
            ->first();

        if (!$route) {
            // Try matching by city or create a generic route lookup
            $route = \App\Models\Interstate\SupportedRoute::where('is_active', true)
                ->where(function ($q) use ($request) {
                    $q->where('origin_city', 'LIKE', '%' . trim($request->pickup_state) . '%')
                      ->orWhere('origin_state', 'LIKE', '%' . trim($request->pickup_state) . '%');
                })
                ->where(function ($q) use ($request) {
                    $q->where('destination_city', 'LIKE', '%' . trim($request->destination_state) . '%')
                      ->orWhere('destination_state', 'LIKE', '%' . trim($request->destination_state) . '%');
                })
                ->first();
        }

        if (!$route) {
            return $this->respondError(
                'No supported route found from ' . $request->pickup_state . ' to ' . $request->destination_state . '. Please try a different route.',
                422
            );
        }

        // Convert structured format to legacy format for the service
        // Map estimated_* to the keys the pricing service expects (actual_weight_kg, length_cm, etc.)
        // and also preserve the estimated_* values for storage
        $weight = (float) $request->estimated_weight_kg;
        $length = (float) $request->estimated_length_cm;
        $width = (float) $request->estimated_width_cm;
        $height = (float) $request->estimated_height_cm;
        $declaredValue = (float) ($request->estimated_goods_value ?? 0);

        $data = [
            'route_id' => $route->id,
            'pick_address' => $request->pickup_address,
            'pick_lat' => 0, // Geocoding not available from state-level input
            'pick_lng' => 0,
            'drop_address' => $request->destination_address,
            'drop_lat' => 0,
            'drop_lng' => 0,
            'sender_phone' => $request->sender_phone,
            'sender_name' => $request->sender_name ?? null,
            'recipient_phone' => $request->recipient_phone,
            'recipient_name' => $request->recipient_name ?? null,
            'pickup_state' => $request->pickup_state,
            'destination_state' => $request->destination_state,
            'packages' => [[
                // Keys the pricing service expects
                'actual_weight_kg' => $weight,
                'length_cm' => $length,
                'width_cm' => $width,
                'height_cm' => $height,
                'declared_value' => $declaredValue,
                'quantity' => 1,
                // Also store estimated values for the DB
                'estimated_weight_kg' => $weight,
                'estimated_length_cm' => $length,
                'estimated_width_cm' => $width,
                'estimated_height_cm' => $height,
                'estimated_declared_value' => $declaredValue,
            ]],
            'service_type' => 'standard',
        ];

        return $this->processCreateRequest($data);
    }

    /**
     * V2 Flow: Create interstate request with company selection.
     * User picks a company from list → Leg 1 bid ride created for dispatch rider pickup.
     */
    private function createRequestV2(HttpRequest $request)
    {
        $validator = Validator::make($request->all(), [
            'trucking_company_id' => 'required|integer|exists:trucking_companies,id',
            'pickup_address' => 'required|string|max:500',
            'pickup_state' => 'required|string|max:100',
            'pick_lat' => 'nullable|numeric|between:-90,90',
            'pick_lng' => 'nullable|numeric|between:-180,180',
            'sender_phone' => 'required|string|max:20',
            'sender_name' => 'nullable|string|max:100',
            'destination_address' => 'required|string|max:500',
            'destination_state' => 'required|string|max:100',
            'drop_lat' => 'nullable|numeric|between:-90,90',
            'drop_lng' => 'nullable|numeric|between:-180,180',
            'recipient_phone' => 'required|string|max:20',
            'recipient_name' => 'nullable|string|max:100',
            'estimated_weight_kg' => 'required|numeric|min:0.1|max:1000',
            'estimated_length_cm' => 'required|numeric|min:1|max:500',
            'estimated_width_cm' => 'required|numeric|min:1|max:500',
            'estimated_height_cm' => 'required|numeric|min:1|max:500',
            'estimated_goods_value' => 'nullable|numeric|min:0',
            'origin_hub_id' => 'nullable|integer',
            'destination_hub_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->respondWithValidationErrors($validator);
        }

        if (strtolower(trim($request->pickup_state)) === strtolower(trim($request->destination_state))) {
            return $this->respondError('Origin and destination must be different states.', 422);
        }

        $weight = (float) $request->estimated_weight_kg;
        $length = (float) $request->estimated_length_cm;
        $width = (float) $request->estimated_width_cm;
        $height = (float) $request->estimated_height_cm;
        $declaredValue = (float) ($request->estimated_goods_value ?? 0);

        try {
            $result = $this->requestServiceV2->createInterstateRequest([
                'user_id' => auth()->id(),
                'trucking_company_id' => $request->trucking_company_id,
                'pick_address' => $request->pickup_address,
                'pick_lat' => $request->pick_lat ?? 0,
                'pick_lng' => $request->pick_lng ?? 0,
                'drop_address' => $request->destination_address,
                'drop_lat' => $request->drop_lat ?? 0,
                'drop_lng' => $request->drop_lng ?? 0,
                'pickup_state' => $request->pickup_state,
                'destination_state' => $request->destination_state,
                'sender_phone' => $request->sender_phone,
                'sender_name' => $request->sender_name,
                'recipient_phone' => $request->recipient_phone,
                'recipient_name' => $request->recipient_name,
                'origin_hub_id' => $request->origin_hub_id,
                'destination_hub_id' => $request->destination_hub_id,
                'packages' => [[
                    'actual_weight_kg' => $weight,
                    'length_cm' => $length,
                    'width_cm' => $width,
                    'height_cm' => $height,
                    'declared_value' => $declaredValue,
                    'quantity' => 1,
                    'estimated_weight_kg' => $weight,
                    'estimated_length_cm' => $length,
                    'estimated_width_cm' => $width,
                    'estimated_height_cm' => $height,
                    'estimated_declared_value' => $declaredValue,
                ]],
            ]);

            $parentRequest = $result['parent_request'];
            $leg1BidRequest = $result['leg1_bid_request'];

            return $this->respondSuccess([
                'request_id' => $parentRequest->id,
                'request_number' => $parentRequest->request_number,
                'status' => 'leg1_awaiting_driver',
                'trucking_company' => [
                    'id' => $parentRequest->truckingCompany->id,
                    'name' => $parentRequest->truckingCompany->company_name,
                ],
                'origin_hub' => $parentRequest->originHub ? [
                    'name' => $parentRequest->originHub->hub_name,
                    'address' => $parentRequest->originHub->address,
                ] : null,
                'leg1_bid_ride' => [
                    'request_id' => $leg1BidRequest->id,
                    'request_number' => $leg1BidRequest->request_number,
                    'pickup' => $leg1BidRequest->pick_address,
                    'dropoff' => $leg1BidRequest->drop_address,
                ],
                'packages' => $parentRequest->packages->map(fn($pkg) => [
                    'package_number' => $pkg->package_number,
                    'weight_kg' => $pkg->estimated_weight_kg,
                    'dimensions' => "{$pkg->length_cm} x {$pkg->width_cm} x {$pkg->height_cm} cm",
                ]),
                'flow' => [
                    'step' => 1,
                    'description' => 'Waiting for dispatch rider to accept pickup',
                    'next' => 'Rider picks up → Delivers to company hub → Company weighs & prices → You approve/reject',
                ],
                'created_at' => $parentRequest->created_at,
            ], 'Interstate delivery created! A dispatch rider will pick up your package and deliver it to the trucking company hub.');

        } catch (\InvalidArgumentException $e) {
            return $this->respondError($e->getMessage(), 422);
        } catch (\Exception $e) {
            \Log::error('V2 interstate request creation failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->respondError('Failed to create request: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Common logic to process and create the interstate request
     */
    private function processCreateRequest(array $data)
    {
        try {
            $data['user_id'] = auth()->id();
            $data['service_type'] = $data['service_type'] ?? 'standard';

            $interstateRequest = $this->requestService->createInterstateRequest($data);

            // Notify eligible trucking companies about new bidding opportunity (non-critical)
            try {
                $this->notifyEligibleCompanies($interstateRequest);
            } catch (\Exception $e) {
                \Log::warning('Failed to notify companies: ' . $e->getMessage());
            }

            return $this->respondSuccess([
                'request_id' => $interstateRequest->id,
                'request_number' => $interstateRequest->request_number,
                'tracking_number' => $interstateRequest->request_number,
                'status' => 'pending',
                'is_bidding_phase' => true,
                'bidding_timeout_at' => $interstateRequest->bidding_timeout_at,
                'currency' => 'NGN',
                'route' => [
                    'origin_hub' => $interstateRequest->originHub ? $interstateRequest->originHub->hub_name : null,
                    'destination_hub' => $interstateRequest->destinationHub ? $interstateRequest->destinationHub->hub_name : null,
                ],
                'packages' => $interstateRequest->packages->map(fn($pkg) => [
                    'package_number' => $pkg->package_number,
                    'weight_kg' => $pkg->actual_weight_kg ?? $pkg->estimated_weight_kg,
                    'dimensions' => "{$pkg->length_cm} × {$pkg->width_cm} × {$pkg->height_cm} cm",
                    'volumetric_weight_kg' => $pkg->volumetric_weight_kg,
                    'chargeable_weight_kg' => $pkg->chargeable_weight_kg,
                    'declared_value' => $pkg->declared_value ?? $pkg->estimated_declared_value,
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
            ->where('is_completed', false)
            ->where('is_cancelled', false)
            ->findOrFail($requestId);

        // Cancel all pending legs
        $interstateRequest->legs()
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        // Update request as cancelled
        $interstateRequest->update([
            'is_cancelled' => true,
            'cancelled_at' => now(),
            'cancel_method' => '0',
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

        $hours = $route->standard_sla_hours ?? 72;

        $startTime = $request->trip_start_time instanceof \Carbon\Carbon
            ? $request->trip_start_time
            : \Carbon\Carbon::parse($request->trip_start_time);

        return $startTime->copy()->addHours($hours)->toIso8601String();
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
