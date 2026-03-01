<?php

namespace App\Services\Interstate;

use App\Models\Request\Request;
use App\Models\Interstate\TrackingUpdate;
use App\Models\Interstate\InterstateBid;
use App\Jobs\Notifications\SendPushNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReroutingService
{
    /**
     * Initiate the re-routing process
     * 
     * When user rejects final cost and requests re-routing:
     * 1. Mark current company as rejected
     * 2. Create dispatch rider request to pick up from current hub
     * 3. Find alternative trucking companies
     * 4. Restart bidding process
     */
    public function initiateRerouting(Request $interstateRequest, string $rejectionReason): void
    {
        DB::transaction(function () use ($interstateRequest, $rejectionReason) {
            // Store previous company info
            $previousCompanyId = $interstateRequest->trucking_company_id;
            $previousCompany = $interstateRequest->truckingCompany;

            // Mark bid from previous company as rejected
            InterstateBid::where('request_id', $interstateRequest->id)
                ->where('trucking_company_id', $previousCompanyId)
                ->update([
                    'status' => 'rejected',
                    'rejected_at' => now(),
                ]);

            // Reset request for new bidding
            $interstateRequest->update([
                'trucking_company_id' => null,
                'status' => 'pending',
                'inspection_status' => 'awaiting_inspection',
                'approval_status' => null,
                'final_transportation_fee' => null,
                'final_insurance_fee' => null,
                'final_total_amount' => null,
                'previous_company_id' => $previousCompanyId,
            ]);

            // Create tracking update
            TrackingUpdate::createStatusChange(
                requestId: $interstateRequest->id,
                previousStatus: 'awaiting_user_approval',
                newStatus: 'rerouting_requested',
                message: "Customer rejected final cost from {$previousCompany->company_name}. Re-routing initiated. Reason: {$rejectionReason}",
                createdByType: 'system'
            );

            // Create dispatch rider request for hub pickup
            $this->createHubPickupRequest($interstateRequest, $previousCompanyId);

            // Notify eligible companies about re-routing opportunity
            $this->notifyEligibleCompanies($interstateRequest, $previousCompanyId);

            // Notify user about re-routing status
            $this->notifyUserOfRerouting($interstateRequest);

            Log::info('Re-routing initiated', [
                'request_id' => $interstateRequest->id,
                'previous_company_id' => $previousCompanyId,
                'reason' => $rejectionReason,
            ]);
        });
    }

    /**
     * Create dispatch rider request to pick up from current hub
     */
    private function createHubPickupRequest(Request $interstateRequest, int $fromHubId): void
    {
        // Create a new leg for hub pickup
        $interstateRequest->legs()->create([
            'leg_number' => $interstateRequest->legs()->count() + 1,
            'leg_type' => 'hub_pickup',
            'provider_type' => 'dispatch_rider',
            'from_location' => [
                'hub_id' => $fromHubId,
                'address' => $interstateRequest->originHub->address,
                'hub_name' => $interstateRequest->originHub->hub_name,
            ],
            'to_location' => [
                'address' => 'Alternative trucking hub (TBD)',
            ],
            'status' => 'pending_assignment',
            'base_fare' => 0,
            'final_fare' => 0,
            'provider_earnings' => 0,
        ]);

        // TODO: Trigger dispatch rider assignment notification
        // This would integrate with the existing dispatch rider system
    }

    /**
     * Notify eligible trucking companies about re-routing opportunity
     */
    private function notifyEligibleCompanies(Request $interstateRequest, int $excludeCompanyId): void
    {
        // Find companies that service this route, excluding the rejected one
        $eligibleCompanies = \App\Models\Interstate\TruckingCompany::active()
            ->whereHas('routes', function ($query) use ($interstateRequest) {
                $query->where('origin_hub_id', $interstateRequest->origin_hub_id)
                    ->where('destination_hub_id', $interstateRequest->destination_hub_id)
                    ->where('is_active', true);
            })
            ->where('id', '!=', $excludeCompanyId)
            ->get();

        foreach ($eligibleCompanies as $company) {
            if ($company->user) {
                $title = trans('push_notifications.rerouting_opportunity_title', [], $company->user->lang);
                $body = trans('push_notifications.rerouting_opportunity_body', [
                    'origin' => $interstateRequest->originHub->city,
                    'destination' => $interstateRequest->destinationHub->city,
                    'previous_company' => $interstateRequest->truckingCompany?->company_name ?? 'Previous company',
                ], $company->user->lang);

                $pushData = [
                    'type' => 'rerouting_opportunity',
                    'request_id' => $interstateRequest->id,
                    'request_number' => $interstateRequest->request_number,
                ];

                dispatch(new SendPushNotification($company->user, $title, $body, $pushData));
            }
        }
    }

    /**
     * Notify user about re-routing status
     */
    private function notifyUserOfRerouting(Request $interstateRequest): void
    {
        $user = $interstateRequest->userDetail;
        
        $title = trans('push_notifications.rerouting_initiated_title', [], $user->lang);
        $body = trans('push_notifications.rerouting_initiated_body', [
            'request_number' => $interstateRequest->request_number,
            'attempt' => $interstateRequest->rerouting_attempt_count,
        ], $user->lang);

        $pushData = [
            'type' => 'rerouting_initiated',
            'request_id' => $interstateRequest->id,
            'attempt_count' => $interstateRequest->rerouting_attempt_count,
        ];

        dispatch(new SendPushNotification($user, $title, $body, $pushData));
    }

    /**
     * Check if maximum re-routing attempts reached
     */
    public function canReroute(Request $interstateRequest): bool
    {
        return $interstateRequest->rerouting_attempt_count < 2;
    }

    /**
     * Get re-routing status for display
     */
    public function getReroutingStatus(Request $interstateRequest): array
    {
        return [
            'can_reroute' => $this->canReroute($interstateRequest),
            'attempts_made' => $interstateRequest->rerouting_attempt_count,
            'max_attempts' => 2,
            'previous_company_id' => $interstateRequest->previous_company_id,
            'previous_company_name' => $interstateRequest->previousCompany?->company_name,
        ];
    }
}
