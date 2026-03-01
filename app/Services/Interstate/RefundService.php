<?php

namespace App\Services\Interstate;

use App\Models\Request\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RefundService
{
    /**
     * Process cancellation refund
     */
    public function processCancellationRefund(Request $interstateRequest): RefundResult
    {
        $refundAmount = $this->calculateRefundAmount($interstateRequest);
        
        DB::transaction(function () use ($interstateRequest, $refundAmount) {
            // Create refund record
            // TODO: Create refund transaction record
            
            Log::info('Refund processed', [
                'request_id' => $interstateRequest->id,
                'amount' => $refundAmount,
                'status' => $interstateRequest->status,
            ]);
        });

        return new RefundResult(
            amount: $refundAmount,
            status: 'pending', // Would be updated by payment processor
            estimatedDays: 3,
            currency: 'NGN'
        );
    }

    /**
     * Calculate refund amount based on cancellation stage
     */
    private function calculateRefundAmount(Request $interstateRequest): float
    {
        $totalPaid = $interstateRequest->request_eta_amount ?? 0;
        
        return match($interstateRequest->inspection_status) {
            // Before inspection - full refund minus processing fee
            'not_required', 'awaiting_inspection' => $totalPaid * 0.95,
            
            // During inspection - partial refund
            'inspection_in_progress' => $totalPaid * 0.75,
            
            // After inspection but before approval - partial refund with inspection fee
            'awaiting_user_approval' => $totalPaid * 0.50,
            
            // After approval - no refund
            'approved_by_user', 'completed' => 0,
            
            // Re-routing - full refund for new bidding
            'rerouting_requested' => $totalPaid * 0.90,
            
            default => $totalPaid * 0.50,
        };
    }

    /**
     * Process payment failure after approval
     */
    public function handlePaymentFailure(Request $interstateRequest, string $failureReason): void
    {
        $interstateRequest->update([
            'status' => 'payment_failed',
            'payment_failure_reason' => $failureReason,
            'payment_failed_at' => now(),
        ]);

        // Create tracking update
        \App\Models\Interstate\TrackingUpdate::createStatusChange(
            requestId: $interstateRequest->id,
            previousStatus: 'awaiting_payment',
            newStatus: 'payment_failed',
            message: 'Payment failed: ' . $failureReason,
            createdByType: 'system'
        );

        // Notify user
        $user = $interstateRequest->userDetail;
        $title = trans('push_notifications.payment_failed_title', [], $user->lang);
        $body = trans('push_notifications.payment_failed_body', [
            'request_number' => $interstateRequest->request_number,
        ], $user->lang);

        dispatch(new \App\Jobs\Notifications\SendPushNotification($user, $title, $body));
    }
}

/**
 * Refund result data transfer object
 */
class RefundResult
{
    public function __construct(
        public float $amount,
        public string $status,
        public int $estimatedDays,
        public string $currency
    ) {}
}
