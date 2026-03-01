<?php

namespace App\Services\Interstate\Notifications;

use App\Models\Request\Request;
use App\Models\Interstate\RequestLeg;
use App\Helpers\Notification\FirebaseNotificationHelper;
use Illuminate\Support\Facades\Log;

/**
 * Service for sending Firebase notifications for interstate delivery events
 * Handles notifications to users (Flutter app) and drivers
 */
class InterstateFirebaseNotificationService
{
    /**
     * Send weight verification notification to user
     * Called when trucking company verifies weight
     */
    public function sendWeightVerificationNotification(
        Request $request, 
        RequestLeg $leg, 
        array $verificationData
    ): void {
        $user = $request->user;
        
        if (!$user || !$user->fcm_token) {
            Log::warning("Cannot send weight verification notification - no FCM token for user {$request->user_id}");
            return;
        }

        $originalWeight = $verificationData['original_chargeable_weight'] ?? 0;
        $verifiedWeight = $verificationData['verified_chargeable_weight'] ?? 0;
        $difference = $verifiedWeight - $originalWeight;
        
        $title = 'Weight Verification Complete';
        
        if ($difference > 0) {
            $body = "Your package weight was verified at {$verifiedWeight}kg (was {$originalWeight}kg). Additional payment required before delivery continues.";
        } elseif ($difference < 0) {
            $body = "Your package weight was verified at {$verifiedWeight}kg (was {$originalWeight}kg). A refund will be processed.";
        } else {
            $body = "Your package weight was verified at {$verifiedWeight}kg. No price adjustment needed.";
        }

        $data = [
            'type' => 'weight_verification',
            'request_id' => $request->id,
            'request_number' => $request->request_number,
            'leg_number' => $leg->leg_number,
            'original_weight' => $originalWeight,
            'verified_weight' => $verifiedWeight,
            'weight_difference' => $difference,
            'price_adjustment_pending' => $difference > 0,
            'action' => $difference > 0 ? 'payment_required' : 'continue',
        ];

        $this->sendNotification($user->fcm_token, $title, $body, $data);
        
        Log::info("Weight verification notification sent for request {$request->request_number}");
    }

    /**
     * Send payment required notification
     * Called when additional payment is needed after weight verification
     */
    public function sendPaymentRequiredNotification(
        Request $request, 
        RequestLeg $leg, 
        float $amount
    ): void {
        $user = $request->user;
        
        if (!$user || !$user->fcm_token) {
            Log::warning("Cannot send payment notification - no FCM token for user {$request->user_id}");
            return;
        }

        $title = 'Additional Payment Required';
        $body = "Weight verification shows your package is heavier than estimated. Please pay ₦" . number_format($amount, 2) . " to continue delivery.";

        $data = [
            'type' => 'payment_required',
            'request_id' => $request->id,
            'request_number' => $request->request_number,
            'leg_number' => $leg->leg_number,
            'leg_type' => $leg->leg_type,
            'amount' => $amount,
            'currency' => 'NGN',
            'payment_deadline' => now()->addHours(2)->toIso8601String(),
            'action' => 'pay_now',
            'screen' => '/interstate/payment',
        ];

        $this->sendNotification($user->fcm_token, $title, $body, $data);
        
        Log::info("Payment required notification sent for request {$request->request_number}, amount: {$amount}");
    }

    /**
     * Send refund notification
     */
    public function sendRefundNotification(
        Request $request, 
        RequestLeg $leg, 
        float $amount
    ): void {
        $user = $request->user;
        
        if (!$user || !$user->fcm_token) {
            Log::warning("Cannot send refund notification - no FCM token for user {$request->user_id}");
            return;
        }

        $title = 'Refund Processed';
        $body = "Weight verification shows your package is lighter than estimated. A refund of ₦" . number_format($amount, 2) . " will be processed within 3-5 business days.";

        $data = [
            'type' => 'refund_processed',
            'request_id' => $request->id,
            'request_number' => $request->request_number,
            'leg_number' => $leg->leg_number,
            'refund_amount' => $amount,
            'currency' => 'NGN',
            'action' => 'view_details',
        ];

        $this->sendNotification($user->fcm_token, $title, $body, $data);
        
        Log::info("Refund notification sent for request {$request->request_number}, amount: {$amount}");
    }

    /**
     * Send next leg activated notification
     */
    public function sendNextLegActivatedNotification(Request $request, RequestLeg $leg): void
    {
        $user = $request->user;
        
        if (!$user || !$user->fcm_token) {
            return;
        }

        $legNames = [
            'local_pickup' => 'Pickup from Seller',
            'hub_dropoff' => 'Hub Drop-off',
            'interstate_transport' => 'Interstate Transport',
            'hub_pickup' => 'Hub Pickup',
            'local_delivery' => 'Delivery to You',
        ];

        $title = 'Delivery Progress Update';
        $body = "Your package is now in progress: {$legNames[$leg->leg_type]}.";

        $data = [
            'type' => 'leg_activated',
            'request_id' => $request->id,
            'request_number' => $request->request_number,
            'leg_number' => $leg->leg_number,
            'leg_type' => $leg->leg_type,
            'leg_name' => $legNames[$leg->leg_type] ?? $leg->leg_type,
            'status' => $leg->status,
            'action' => 'track_delivery',
            'screen' => '/interstate/tracking',
        ];

        $this->sendNotification($user->fcm_token, $title, $body, $data);
    }

    /**
     * Send package at hub notification
     */
    public function sendPackageAtHubNotification(Request $request, string $hubName, string $hubType): void
    {
        $user = $request->user;
        
        if (!$user || !$user->fcm_token) {
            return;
        }

        if ($hubType === 'origin') {
            $title = 'Package at Origin Hub';
            $body = "Your package has arrived at {$hubName} and is ready for interstate transport.";
        } else {
            $title = 'Package at Destination Hub';
            $body = "Your package has arrived at {$hubName} and will be delivered soon.";
        }

        $data = [
            'type' => 'package_at_hub',
            'request_id' => $request->id,
            'request_number' => $request->request_number,
            'hub_name' => $hubName,
            'hub_type' => $hubType,
            'action' => 'track_delivery',
        ];

        $this->sendNotification($user->fcm_token, $title, $body, $data);
    }

    /**
     * Send delivery completed notification
     */
    public function sendDeliveryCompletedNotification(Request $request): void
    {
        $user = $request->user;
        
        if (!$user || !$user->fcm_token) {
            return;
        }

        $title = 'Delivery Complete!';
        $body = "Your package has been successfully delivered. Thank you for using our service!";

        $data = [
            'type' => 'delivery_completed',
            'request_id' => $request->id,
            'request_number' => $request->request_number,
            'action' => 'rate_experience',
            'screen' => '/interstate/rate',
        ];

        $this->sendNotification($user->fcm_token, $title, $body, $data);
    }

    /**
     * Notify trucking company of new package at their hub
     */
    public function notifyTruckingCompanyOfIncomingPackage(
        Request $request, 
        int $companyId,
        string $hubName
    ): void {
        // Get company user with FCM token
        $company = \App\Models\Interstate\TruckingCompany::find($companyId);
        
        if (!$company || !$company->user || !$company->user->fcm_token) {
            Log::warning("Cannot notify trucking company - no FCM token for company {$companyId}");
            return;
        }

        $title = 'New Package at ' . $hubName;
        $body = "A new package ({$request->request_number}) is ready for interstate transport from {$hubName}.";

        $data = [
            'type' => 'new_package_at_hub',
            'request_id' => $request->id,
            'request_number' => $request->request_number,
            'hub_name' => $hubName,
            'action' => 'view_package',
            'screen' => '/trucking/incoming',
        ];

        $this->sendNotification($company->user->fcm_token, $title, $body, $data);
    }

    /**
     * Send notification to multiple devices
     */
    private function sendNotification(string $token, string $title, string $body, array $data = []): void
    {
        try {
            $notificationData = [
                'to' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                    'sound' => 'default',
                    'badge' => 1,
                ],
                'data' => $data,
                'priority' => 'high',
            ];

            // Use existing Firebase helper if available
            if (class_exists(FirebaseNotificationHelper::class)) {
                FirebaseNotificationHelper::send($notificationData);
            } else {
                // Direct FCM API call
                $this->sendDirectFcmNotification($notificationData);
            }
        } catch (\Exception $e) {
            Log::error("Failed to send Firebase notification: " . $e->getMessage());
        }
    }

    /**
     * Send notification directly via FCM API
     */
    private function sendDirectFcmNotification(array $notificationData): void
    {
        $serverKey = config('firebase.server_key');
        
        if (!$serverKey) {
            Log::error("Firebase server key not configured");
            return;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: key=' . $serverKey,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notificationData));

        $result = curl_exec($ch);
        
        if ($result === false) {
            Log::error("FCM curl error: " . curl_error($ch));
        } else {
            $response = json_decode($result, true);
            if (isset($response['failure']) && $response['failure'] > 0) {
                Log::error("FCM notification failed: " . json_encode($response));
            }
        }

        curl_close($ch);
    }
}
