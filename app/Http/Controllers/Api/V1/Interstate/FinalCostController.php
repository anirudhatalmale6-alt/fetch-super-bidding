<?php

namespace App\Http\Controllers\Api\V1\Interstate;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Request\Request;
use App\Models\Interstate\TrackingUpdate;
use App\Services\Interstate\ReroutingService;
use App\Services\Interstate\RefundService;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Kreait\Firebase\Contract\Database;
use App\Jobs\Notifications\SendPushNotification;

class FinalCostController extends BaseController
{
    public function __construct(
        private Database $database,
        private ReroutingService $reroutingService,
        private RefundService $refundService
    ) {}

    /**
     * Get Final Cost Details for User Approval
     * 
     * GET /api/v1/interstate/final-cost/{requestId}
     */
    public function getFinalCostDetails(string $requestId)
    {
        $interstateRequest = Request::with(['packages', 'truckingCompany', 'originHub', 'trackingUpdates'])
            ->where('id', $requestId)
            ->where('user_id', auth()->id())
            ->where('delivery_mode', 'interstate')
            ->first();

        if (!$interstateRequest) {
            return $this->respondError('Request not found', 404);
        }

        // Calculate time remaining for approval
        $timeRemaining = null;
        $isExpired = false;
        if ($interstateRequest->user_approval_deadline) {
            $now = now();
            $deadline = $interstateRequest->user_approval_deadline;
            $isExpired = $now->isAfter($deadline);
            if (!$isExpired) {
                $diff = $now->diff($deadline);
                $timeRemaining = [
                    'hours' => $diff->h + ($diff->days * 24),
                    'minutes' => $diff->i,
                    'formatted' => $diff->format('%H:%I:%S'),
                ];
            }
        }

        return $this->respondSuccess([
            'request_id' => $interstateRequest->id,
            'request_number' => $interstateRequest->request_number,
            'status' => $interstateRequest->status,
            'inspection_status' => $interstateRequest->inspection_status,
            'approval_status' => $interstateRequest->approval_status,
            'trucking_company' => [
                'id' => $interstateRequest->truckingCompany->id,
                'name' => $interstateRequest->truckingCompany->company_name,
                'logo' => $interstateRequest->truckingCompany->logo,
                'rating' => $interstateRequest->truckingCompany->rating,
                'phone' => $interstateRequest->truckingCompany->phone,
            ],
            'hub' => [
                'name' => $interstateRequest->originHub->hub_name ?? null,
                'address' => $interstateRequest->originHub->address ?? null,
            ],
            'packages' => $interstateRequest->packages->map(fn($pkg) => [
                'package_number' => $pkg->package_number,
                'description' => $pkg->description,
                'estimated' => [
                    'weight_kg' => $pkg->estimated_weight_kg,
                    'dimensions' => [
                        'length_cm' => $pkg->estimated_length_cm,
                        'width_cm' => $pkg->estimated_width_cm,
                        'height_cm' => $pkg->estimated_height_cm,
                    ],
                    'declared_value' => $pkg->estimated_declared_value,
                ],
                'measured' => [
                    'weight_kg' => $pkg->final_weight_kg,
                    'dimensions' => [
                        'length_cm' => $pkg->final_length_cm,
                        'width_cm' => $pkg->final_width_cm,
                        'height_cm' => $pkg->final_height_cm,
                    ],
                    'declared_value' => $pkg->final_declared_value,
                    'chargeable_weight_kg' => $pkg->final_chargeable_weight_kg,
                ],
                'discrepancy' => [
                    'weight_percent' => $pkg->weight_discrepancy_percent,
                    'is_significant' => abs($pkg->weight_discrepancy_percent ?? 0) > 10,
                ],
            ]),
            'pricing' => [
                'initial_bid' => [
                    'transportation_fee' => $interstateRequest->initial_bid_amount ? $interstateRequest->initial_bid_amount - ($interstateRequest->final_insurance_fee ?? 0) : null,
                    'insurance_fee' => 0, // Will be calculated from bid
                    'total' => $interstateRequest->initial_bid_amount,
                ],
                'final_cost' => [
                    'transportation_fee' => $interstateRequest->final_transportation_fee,
                    'insurance_fee' => $interstateRequest->final_insurance_fee,
                    'total' => $interstateRequest->final_total_amount,
                ],
                'difference' => [
                    'amount' => $interstateRequest->price_difference,
                    'percent' => $interstateRequest->price_difference_percent,
                    'is_increase' => ($interstateRequest->price_difference ?? 0) > 0,
                ],
            ],
            'company_remarks' => $interstateRequest->final_cost_remarks,
            'timeline' => [
                'submitted_at' => $interstateRequest->final_cost_submitted_at,
                'approval_deadline' => $interstateRequest->user_approval_deadline,
                'time_remaining' => $timeRemaining,
                'is_expired' => $isExpired,
            ],
            'available_actions' => $this->getAvailableActions($interstateRequest, $isExpired),
        ]);
    }

    /**
     * Accept Final Cost and Proceed to Payment
     * 
     * POST /api/v1/interstate/final-cost/accept/{requestId}
     */
    public function acceptFinalCost(string $requestId)
    {
        $interstateRequest = Request::with(['packages', 'truckingCompany'])
            ->where('id', $requestId)
            ->where('user_id', auth()->id())
            ->where('delivery_mode', 'interstate')
            ->where('inspection_status', 'awaiting_user_approval')
            ->first();

        if (!$interstateRequest) {
            return $this->respondError('Request not found or not available for approval', 404);
        }

        // Check if approval deadline has passed
        if ($interstateRequest->user_approval_deadline && now()->isAfter($interstateRequest->user_approval_deadline)) {
            return $this->respondError('Approval deadline has passed. Please contact support.', 422);
        }

        try {
            DB::transaction(function () use ($interstateRequest) {
                $interstateRequest->update([
                    'inspection_status' => 'approved_by_user',
                    'approval_status' => 'approved',
                    'user_approved_at' => now(),
                    'approved_by_user_id' => auth()->id(),
                    'status' => 'confirmed', // Ready for payment
                    'interstate_transport_fee' => $interstateRequest->final_transportation_fee,
                ]);

                // Create tracking update
                TrackingUpdate::createStatusChange(
                    requestId: $interstateRequest->id,
                    previousStatus: 'awaiting_user_approval',
                    newStatus: 'approved_by_user',
                    message: 'Customer approved final cost',
                    createdById: auth()->id(),
                    createdByType: 'user'
                );

                // Sync to Firebase
                $this->syncApprovalToFirebase($interstateRequest, 'approved');
            });

            // Notify company
            $this->notifyCompanyOfApproval($interstateRequest);

            return $this->respondSuccess([
                'request_id' => $interstateRequest->id,
                'status' => 'approved_by_user',
                'next_step' => 'payment',
                'final_amount' => $interstateRequest->final_total_amount,
            ], 'Final cost approved. Please proceed to payment.');

        } catch (\Exception $e) {
            return $this->respondError('Failed to approve final cost: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Reject Final Cost and Request Re-routing
     * 
     * POST /api/v1/interstate/final-cost/reject-reroute/{requestId}
     */
    public function rejectAndReroute(string $requestId, HttpRequest $request)
    {
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->respondWithValidationErrors($validator);
        }

        $interstateRequest = Request::with(['packages', 'truckingCompany'])
            ->where('id', $requestId)
            ->where('user_id', auth()->id())
            ->where('delivery_mode', 'interstate')
            ->where('inspection_status', 'awaiting_user_approval')
            ->first();

        if (!$interstateRequest) {
            return $this->respondError('Request not found or not available for rejection', 404);
        }

        // Check re-routing attempt limit
        if ($interstateRequest->rerouting_attempt_count >= 2) {
            return $this->respondError('Maximum re-routing attempts reached. Please cancel or accept current offer.', 422);
        }

        try {
            DB::transaction(function () use ($interstateRequest, $request) {
                // Store current company as previous
                $previousCompanyId = $interstateRequest->trucking_company_id;

                $interstateRequest->update([
                    'inspection_status' => 'rerouting_requested',
                    'approval_status' => 'rejected',
                    'user_rejected_at' => now(),
                    'approved_by_user_id' => auth()->id(),
                    'rerouting_requested_at' => now(),
                    'previous_company_id' => $previousCompanyId,
                    'rerouting_attempt_count' => $interstateRequest->rerouting_attempt_count + 1,
                ]);

                // Create tracking update
                TrackingUpdate::createStatusChange(
                    requestId: $interstateRequest->id,
                    previousStatus: 'awaiting_user_approval',
                    newStatus: 'rerouting_requested',
                    message: 'Customer rejected final cost and requested re-routing. Reason: ' . $request->input('rejection_reason'),
                    createdById: auth()->id(),
                    createdByType: 'user'
                );

                // Sync to Firebase
                $this->syncApprovalToFirebase($interstateRequest, 'rejected');
            });

            // Trigger re-routing process
            $this->reroutingService->initiateRerouting($interstateRequest, $request->input('rejection_reason'));

            return $this->respondSuccess([
                'request_id' => $interstateRequest->id,
                'status' => 'rerouting_requested',
                'rerouting_attempt' => $interstateRequest->rerouting_attempt_count,
                'message' => 'Re-routing request submitted. We will arrange pickup from the current hub and find a new trucking company.',
            ]);

        } catch (\Exception $e) {
            return $this->respondError('Failed to process re-routing request: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cancel Shipment
     * 
     * POST /api/v1/interstate/final-cost/cancel/{requestId}
     */
    public function cancelShipment(string $requestId, HttpRequest $request)
    {
        $validator = Validator::make($request->all(), [
            'cancellation_reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->respondWithValidationErrors($validator);
        }

        $interstateRequest = Request::with('packages')
            ->where('id', $requestId)
            ->where('user_id', auth()->id())
            ->where('delivery_mode', 'interstate')
            ->whereIn('inspection_status', ['awaiting_user_approval', 'inspection_in_progress'])
            ->first();

        if (!$interstateRequest) {
            return $this->respondError('Request not found or cannot be cancelled at this stage', 404);
        }

        try {
            DB::transaction(function () use ($interstateRequest, $request) {
                $interstateRequest->update([
                    'inspection_status' => 'completed',
                    'approval_status' => 'cancelled',
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'reason' => $request->input('cancellation_reason'),
                ]);

                // Create tracking update
                TrackingUpdate::createStatusChange(
                    requestId: $interstateRequest->id,
                    previousStatus: $interstateRequest->inspection_status,
                    newStatus: 'cancelled',
                    message: 'Shipment cancelled by customer. Reason: ' . $request->input('cancellation_reason'),
                    createdById: auth()->id(),
                    createdByType: 'user'
                );
            });

            // Process refund
            $refund = $this->refundService->processCancellationRefund($interstateRequest);

            return $this->respondSuccess([
                'request_id' => $interstateRequest->id,
                'status' => 'cancelled',
                'refund' => [
                    'amount' => $refund->amount,
                    'status' => $refund->status,
                    'estimated_days' => $refund->estimatedDays,
                ],
            ], 'Shipment cancelled successfully. Refund will be processed.');

        } catch (\Exception $e) {
            return $this->respondError('Failed to cancel shipment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get Available Actions based on request state
     */
    private function getAvailableActions(Request $interstateRequest, bool $isExpired): array
    {
        if ($isExpired) {
            return ['contact_support'];
        }

        $actions = [];

        if ($interstateRequest->inspection_status === 'awaiting_user_approval') {
            $actions[] = 'accept_and_pay';
            
            if ($interstateRequest->rerouting_attempt_count < 2) {
                $actions[] = 'decline_and_reroute';
            }
            
            $actions[] = 'cancel_shipment';
        }

        return $actions;
    }

    /**
     * Sync approval to Firebase
     */
    private function syncApprovalToFirebase(Request $interstateRequest, string $action): void
    {
        $this->database
            ->getReference("interstate-requests/{$interstateRequest->id}")
            ->update([
                'approval_action' => $action,
                'approved_at' => now()->timestamp * 1000,
                'approved_by' => auth()->id(),
            ]);
    }

    /**
     * Notify company of approval
     */
    private function notifyCompanyOfApproval(Request $interstateRequest): void
    {
        $company = $interstateRequest->truckingCompany;
        
        if ($company && $company->user) {
            $title = trans('push_notifications.final_cost_approved_title', [], $company->user->lang);
            $body = trans('push_notifications.final_cost_approved_body', [
                'request_number' => $interstateRequest->request_number,
            ], $company->user->lang);

            $pushData = [
                'type' => 'final_cost_approved',
                'request_id' => $interstateRequest->id,
            ];

            dispatch(new SendPushNotification($company->user, $title, $body, $pushData));
        }
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
