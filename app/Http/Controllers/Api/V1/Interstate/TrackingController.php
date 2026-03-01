<?php

namespace App\Http\Controllers\Api\V1\Interstate;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Request\Request;
use App\Models\Interstate\TrackingUpdate;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Validator;
use Kreait\Firebase\Contract\Database;
use App\Jobs\Notifications\SendPushNotification;

class TrackingController extends BaseController
{
    public function __construct(
        private Database $database
    ) {}

    /**
     * Get Tracking Updates for User
     * 
     * GET /api/v1/interstate/tracking/{requestId}
     */
    public function getTrackingUpdates(string $requestId)
    {
        $interstateRequest = Request::with(['packages', 'trackingUpdates', 'truckingCompany'])
            ->where('id', $requestId)
            ->where('user_id', auth()->id())
            ->where('delivery_mode', 'interstate')
            ->first();

        if (!$interstateRequest) {
            return $this->respondError('Request not found', 404);
        }

        $updates = $interstateRequest->trackingUpdates()
            ->visibleToCustomer()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->respondSuccess([
            'request' => [
                'id' => $interstateRequest->id,
                'number' => $interstateRequest->request_number,
                'status' => $interstateRequest->status,
                'inspection_status' => $interstateRequest->inspection_status,
            ],
            'updates' => $updates->map(fn($update) => [
                'id' => $update->id,
                'message' => $update->message,
                'type' => $update->update_type,
                'location' => $update->location_name,
                'coordinates' => $update->hasLocation() ? [
                    'lat' => $update->latitude,
                    'lng' => $update->longitude,
                ] : null,
                'image' => $update->image_url,
                'created_by' => $update->created_by_name,
                'created_at' => $update->created_at,
                'formatted_time' => $update->formatted_time,
            ]),
            'pagination' => [
                'current_page' => $updates->currentPage(),
                'last_page' => $updates->lastPage(),
                'per_page' => $updates->perPage(),
                'total' => $updates->total(),
            ],
        ]);
    }

    /**
     * Add Tracking Update (Company/Driver)
     * 
     * POST /api/v1/interstate/tracking/update
     */
    public function addTrackingUpdate(HttpRequest $request)
    {
        $validator = Validator::make($request->all(), [
            'request_id' => 'required|exists:requests,id',
            'message' => 'required|string|max:500',
            'update_type' => 'required|in:status_change,location_update,inspection_note,delay_notification,general_update,hub_arrival,hub_departure,checkpoint_passed',
            'location_name' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'image' => 'nullable|image|max:5120',
            'previous_status' => 'nullable|string',
            'new_status' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->respondWithValidationErrors($validator);
        }

        $company = auth()->user()->truckingCompany;
        
        if (!$company) {
            return $this->respondError('Unauthorized', 403);
        }

        $interstateRequest = Request::with('userDetail')
            ->where('id', $request->input('request_id'))
            ->where('trucking_company_id', $company->id)
            ->first();

        if (!$interstateRequest) {
            return $this->respondError('Request not found', 404);
        }

        try {
            $imageUrl = null;
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store(
                    "tracking/{$interstateRequest->id}",
                    'public'
                );
                $imageUrl = asset('storage/' . $path);
            }

            $update = TrackingUpdate::create([
                'request_id' => $interstateRequest->id,
                'message' => $request->input('message'),
                'update_type' => $request->input('update_type'),
                'location_name' => $request->input('location_name'),
                'latitude' => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
                'image_url' => $imageUrl,
                'created_by_type' => 'trucking_company',
                'created_by_id' => auth()->id(),
                'created_by_name' => auth()->user()->name,
                'previous_status' => $request->input('previous_status'),
                'new_status' => $request->input('new_status'),
            ]);

            // Sync to Firebase for real-time updates
            $this->syncTrackingUpdateToFirebase($update);

            // Notify user
            $this->notifyUserOfTrackingUpdate($interstateRequest, $update);

            return $this->respondSuccess([
                'update_id' => $update->id,
                'message' => $update->message,
                'created_at' => $update->created_at,
            ], 'Tracking update added successfully');

        } catch (\Exception $e) {
            return $this->respondError('Failed to add tracking update: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get Timeline with Stages
     * 
     * GET /api/v1/interstate/timeline/{requestId}
     */
    public function getTimeline(string $requestId)
    {
        $interstateRequest = Request::with(['packages', 'legs', 'truckingCompany', 'originHub', 'destinationHub'])
            ->where('id', $requestId)
            ->where('user_id', auth()->id())
            ->where('delivery_mode', 'interstate')
            ->first();

        if (!$interstateRequest) {
            return $this->respondError('Request not found', 404);
        }

        // Build timeline stages
        $stages = $this->buildTimelineStages($interstateRequest);

        return $this->respondSuccess([
            'request_id' => $interstateRequest->id,
            'current_stage' => $this->getCurrentStage($interstateRequest),
            'stages' => $stages,
            'estimated_delivery' => $this->calculateEstimatedDelivery($interstateRequest),
        ]);
    }

    /**
     * Build timeline stages
     */
    private function buildTimelineStages(Request $interstateRequest): array
    {
        $stages = [];

        // Stage 1: Pickup Complete
        $stages[] = [
            'stage_number' => 1,
            'name' => 'Pickup Complete',
            'status' => $this->getStageStatus($interstateRequest, 'pickup'),
            'completed_at' => $interstateRequest->legs->firstWhere('leg_type', 'local_pickup')?->completed_at,
            'description' => 'Package picked up from sender',
        ];

        // Stage 2: Arrived at Trucking Hub
        $hubDropoffLeg = $interstateRequest->legs->firstWhere('leg_type', 'hub_dropoff');
        $stages[] = [
            'stage_number' => 2,
            'name' => 'Arrived at Trucking Hub',
            'status' => $hubDropoffLeg && $hubDropoffLeg->isCompleted() ? 'completed' : 'pending',
            'completed_at' => $hubDropoffLeg?->completed_at,
            'description' => 'Package arrived at ' . ($interstateRequest->originHub->hub_name ?? 'origin hub'),
            'hub_name' => $interstateRequest->originHub->hub_name ?? null,
        ];

        // Stage 3: Inspection & Final Cost Approval
        $inspectionStage = [
            'stage_number' => 3,
            'name' => 'Inspection & Final Cost Approval',
            'status' => $this->getInspectionStageStatus($interstateRequest),
            'description' => 'Physical inspection and final cost confirmation',
        ];
        
        if ($interstateRequest->inspection_status === 'awaiting_user_approval') {
            $inspectionStage['action_required'] = true;
            $inspectionStage['final_cost'] = [
                'amount' => $interstateRequest->final_total_amount,
                'deadline' => $interstateRequest->user_approval_deadline,
            ];
        }
        
        $stages[] = $inspectionStage;

        // Stage 4: In Transit
        $interstateLeg = $interstateRequest->legs->firstWhere('leg_type', 'interstate_transport');
        $stages[] = [
            'stage_number' => 4,
            'name' => 'In Transit',
            'status' => $this->getTransitStageStatus($interstateRequest, $interstateLeg),
            'started_at' => $interstateLeg?->picked_up_at,
            'company_name' => $interstateRequest->truckingCompany->company_name ?? null,
            'description' => 'Package in transit to destination',
        ];

        // Stage 5: Arrived Destination Hub
        $stages[] = [
            'stage_number' => 5,
            'name' => 'Arrived Destination Hub',
            'status' => 'pending',
            'description' => 'Package arrived at ' . ($interstateRequest->destinationHub->hub_name ?? 'destination hub'),
            'hub_name' => $interstateRequest->destinationHub->hub_name ?? null,
        ];

        // Stage 6: Last Mile Delivery
        $stages[] = [
            'stage_number' => 6,
            'name' => 'Last Mile Delivery',
            'status' => 'pending',
            'description' => 'Package out for final delivery',
        ];

        // Stage 7: Delivered
        $localDeliveryLeg = $interstateRequest->legs->firstWhere('leg_type', 'local_delivery');
        $stages[] = [
            'stage_number' => 7,
            'name' => 'Delivered',
            'status' => $localDeliveryLeg && $localDeliveryLeg->isCompleted() ? 'completed' : 'pending',
            'completed_at' => $localDeliveryLeg?->completed_at,
            'description' => 'Package delivered to recipient',
        ];

        return $stages;
    }

    /**
     * Get stage status
     */
    private function getStageStatus(Request $interstateRequest, string $stageType): string
    {
        $leg = $interstateRequest->legs->firstWhere('leg_type', $stageType);
        if ($leg && $leg->isCompleted()) {
            return 'completed';
        }
        return 'pending';
    }

    /**
     * Get inspection stage status
     */
    private function getInspectionStageStatus(Request $interstateRequest): string
    {
        return match($interstateRequest->inspection_status) {
            'completed', 'approved_by_user' => 'completed',
            'awaiting_user_approval' => 'action_required',
            'inspection_in_progress' => 'in_progress',
            'rejected_by_user', 'rerouting_requested' => 'rejected',
            default => 'pending',
        };
    }

    /**
     * Get transit stage status
     */
    private function getTransitStageStatus(Request $interstateRequest, $interstateLeg): string
    {
        if (!$interstateLeg) {
            return 'pending';
        }
        
        if ($interstateLeg->isCompleted()) {
            return 'completed';
        }
        
        if ($interstateLeg->isInProgress()) {
            return 'in_progress';
        }
        
        return 'pending';
    }

    /**
     * Get current stage number
     */
    private function getCurrentStage(Request $interstateRequest): int
    {
        return match($interstateRequest->inspection_status) {
            'approved_by_user', 'completed' => $interstateRequest->current_leg_number + 2,
            'awaiting_user_approval' => 3,
            'inspection_in_progress' => 3,
            default => min($interstateRequest->current_leg_number + 1, 7),
        };
    }

    /**
     * Calculate estimated delivery
     */
    private function calculateEstimatedDelivery(Request $interstateRequest): ?string
    {
        if (!$interstateRequest->trip_start_time) {
            return null;
        }

        $route = $interstateRequest->supportedRoute;
        if (!$route) {
            return null;
        }

        $hours = $route->standard_sla_hours;
        return $interstateRequest->trip_start_time->copy()->addHours($hours)->toIso8601String();
    }

    /**
     * Sync tracking update to Firebase
     */
    private function syncTrackingUpdateToFirebase(TrackingUpdate $update): void
    {
        $this->database
            ->getReference("interstate-requests/{$update->request_id}/tracking_updates")
            ->push([
                'id' => $update->id,
                'message' => $update->message,
                'type' => $update->update_type,
                'location' => $update->location_name,
                'image' => $update->image_url,
                'created_by' => $update->created_by_name,
                'timestamp' => $update->created_at->timestamp * 1000,
            ]);
    }

    /**
     * Notify user of tracking update
     */
    private function notifyUserOfTrackingUpdate(Request $interstateRequest, TrackingUpdate $update): void
    {
        $user = $interstateRequest->userDetail;
        
        $title = trans('push_notifications.tracking_update_title', [], $user->lang);
        $body = $update->message;

        $pushData = [
            'type' => 'tracking_update',
            'request_id' => $interstateRequest->id,
            'update_id' => $update->id,
        ];

        dispatch(new SendPushNotification($user, $title, $body, $pushData));
    }

    protected function respondWithValidationErrors($validator)
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }
}
