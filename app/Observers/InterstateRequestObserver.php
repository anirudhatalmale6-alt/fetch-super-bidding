<?php

namespace App\Observers;

use App\Models\Request\Request;
use App\Models\Interstate\TrackingUpdate;
use App\Jobs\Notifications\SendPushNotification;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Database;

/**
 * Observes Request model for interstate leg completion.
 *
 * When a child bid ride (with interstate_parent_id) is marked completed,
 * this observer progresses the parent interstate request's flow:
 *
 * - Leg 1 (local_pickup) complete → Company inspection phase
 * - Reroute transfer complete → Company inspection phase at new company
 * - Leg 2 (local_delivery) complete → Interstate delivery complete
 */
class InterstateRequestObserver
{
    public function __construct(private Database $database) {}

    /**
     * Handle the Request "updated" event.
     */
    public function updated(Request $request): void
    {
        // Only care about child requests with interstate parent
        if (!$request->interstate_parent_id) {
            return;
        }

        // Only care when is_completed just changed to true
        if (!$request->is_completed || !$request->wasChanged('is_completed')) {
            return;
        }

        try {
            $this->handleChildRequestCompleted($request);
        } catch (\Exception $e) {
            Log::error('Interstate observer error: ' . $e->getMessage(), [
                'child_request_id' => $request->id,
                'parent_id' => $request->interstate_parent_id,
                'leg_type' => $request->interstate_leg_type,
            ]);
        }
    }

    private function handleChildRequestCompleted(Request $childRequest): void
    {
        $parentRequest = Request::find($childRequest->interstate_parent_id);
        if (!$parentRequest) {
            Log::warning('Interstate parent not found', ['parent_id' => $childRequest->interstate_parent_id]);
            return;
        }

        switch ($childRequest->interstate_leg_type) {
            case 'local_pickup':
                $this->onLeg1Complete($parentRequest, $childRequest);
                break;

            case 'reroute_transfer':
                $this->onRerouteTransferComplete($parentRequest, $childRequest);
                break;

            case 'local_delivery':
                $this->onLeg2Complete($parentRequest, $childRequest);
                break;

            default:
                Log::info('Unknown interstate leg type completed', [
                    'type' => $childRequest->interstate_leg_type,
                ]);
        }
    }

    /**
     * Leg 1 (local pickup → hub) completed.
     * Transition parent to inspection phase — company weighs and prices.
     */
    private function onLeg1Complete(Request $parentRequest, Request $childRequest): void
    {
        Log::info('Interstate Leg 1 completed', [
            'parent_id' => $parentRequest->id,
            'child_id' => $childRequest->id,
        ]);

        // Update leg status
        $leg = $parentRequest->legs()->where('leg_number', $childRequest->interstate_leg_number)->first();
        if ($leg) {
            $leg->update([
                'status' => 'completed',
                'completed_at' => now(),
                'provider_id' => $childRequest->driver_id,
            ]);
        }

        // Transition parent to inspection phase
        $parentRequest->update([
            'inspection_status' => 'awaiting_inspection',
            'actual_hub_arrival' => now(),
        ]);

        // Create tracking update
        TrackingUpdate::create([
            'request_id' => $parentRequest->id,
            'new_status' => 'delivered_to_hub',
            'message' => 'Package delivered to trucking company hub. Awaiting inspection and pricing.',
            'created_by_type' => 'system',
        ]);

        // Sync to Firebase
        try {
            $this->database->getReference("interstate-requests/{$parentRequest->id}")->update([
                'status' => 'awaiting_inspection',
                'leg1_completed_at' => now()->timestamp * 1000,
                'updated_at' => Database::SERVER_TIMESTAMP,
            ]);
        } catch (\Exception $e) {
            Log::warning('Firebase sync failed: ' . $e->getMessage());
        }

        // Notify company to inspect package
        $this->notifyCompanyForInspection($parentRequest);
    }

    /**
     * Reroute transfer (Company A hub → Company B hub) completed.
     * Same as Leg 1 — transition to inspection at the new company.
     */
    private function onRerouteTransferComplete(Request $parentRequest, Request $childRequest): void
    {
        Log::info('Interstate reroute transfer completed', [
            'parent_id' => $parentRequest->id,
            'new_company_id' => $parentRequest->trucking_company_id,
        ]);

        $leg = $parentRequest->legs()->where('leg_number', $childRequest->interstate_leg_number)->first();
        if ($leg) {
            $leg->update([
                'status' => 'completed',
                'completed_at' => now(),
                'provider_id' => $childRequest->driver_id,
            ]);
        }

        $parentRequest->update([
            'inspection_status' => 'awaiting_inspection',
            'approval_status' => null,
            'final_transport_fee' => null,
            'final_insurance_fee' => null,
            'final_total_amount' => null,
            'actual_hub_arrival' => now(),
        ]);

        TrackingUpdate::create([
            'request_id' => $parentRequest->id,
            'new_status' => 'reroute_delivered',
            'message' => 'Package delivered to new trucking company. Awaiting inspection and pricing.',
            'created_by_type' => 'system',
        ]);

        try {
            $this->database->getReference("interstate-requests/{$parentRequest->id}")->update([
                'status' => 'awaiting_inspection',
                'updated_at' => Database::SERVER_TIMESTAMP,
            ]);
        } catch (\Exception $e) {
            Log::warning('Firebase sync failed: ' . $e->getMessage());
        }

        $this->notifyCompanyForInspection($parentRequest);
    }

    /**
     * Leg 2 (hub → recipient) completed.
     * Interstate delivery is done!
     */
    private function onLeg2Complete(Request $parentRequest, Request $childRequest): void
    {
        Log::info('Interstate Leg 2 completed — delivery done!', [
            'parent_id' => $parentRequest->id,
        ]);

        // Update leg status
        $leg = $parentRequest->legs()->where('leg_number', $childRequest->interstate_leg_number)->first();
        if (!$leg) {
            $leg = $parentRequest->legs()->where('leg_type', 'local_delivery')->first();
        }
        if ($leg) {
            $leg->update([
                'status' => 'completed',
                'completed_at' => now(),
                'provider_id' => $childRequest->driver_id,
            ]);
        }

        // Mark parent as completed
        $parentRequest->update([
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        TrackingUpdate::create([
            'request_id' => $parentRequest->id,
            'new_status' => 'delivered',
            'message' => 'Package delivered to recipient. Interstate delivery complete!',
            'created_by_type' => 'system',
        ]);

        try {
            $this->database->getReference("interstate-requests/{$parentRequest->id}")->update([
                'status' => 'completed',
                'completed_at' => now()->timestamp * 1000,
                'updated_at' => Database::SERVER_TIMESTAMP,
            ]);
        } catch (\Exception $e) {
            Log::warning('Firebase sync failed: ' . $e->getMessage());
        }

        // Notify user
        $user = $parentRequest->userDetail;
        if ($user) {
            dispatch(new SendPushNotification(
                $user,
                'Delivery Complete!',
                "Your interstate package {$parentRequest->request_number} has been delivered.",
                [
                    'type' => 'interstate_delivery_complete',
                    'request_id' => $parentRequest->id,
                ]
            ));
        }
    }

    /**
     * Notify trucking company to inspect the package at their hub.
     */
    private function notifyCompanyForInspection(Request $parentRequest): void
    {
        $company = $parentRequest->truckingCompany;
        if ($company && $company->user) {
            dispatch(new SendPushNotification(
                $company->user,
                'Package Arrived — Inspect & Price',
                "Package {$parentRequest->request_number} has arrived at your hub. Please weigh, inspect, and submit your pricing.",
                [
                    'type' => 'inspection_required',
                    'request_id' => $parentRequest->id,
                    'request_number' => $parentRequest->request_number,
                ]
            ));
        }
    }
}
