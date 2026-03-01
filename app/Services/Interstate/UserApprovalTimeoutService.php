<?php

namespace App\Services\Interstate;

use App\Models\Request\Request;
use App\Models\Interstate\TrackingUpdate;
use App\Jobs\Notifications\SendPushNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class UserApprovalTimeoutService
{
    /**
     * Process expired approval deadlines
     * 
     * This should be called by a scheduled command (e.g., every hour)
     */
    public function processExpiredApprovals(): void
    {
        $expiredRequests = Request::where('delivery_mode', 'interstate')
            ->where('inspection_status', 'awaiting_user_approval')
            ->whereNotNull('user_approval_deadline')
            ->where('user_approval_deadline', '<', now())
            ->get();

        foreach ($expiredRequests as $request) {
            $this->handleExpiredApproval($request);
        }

        Log::info('Processed expired approvals', ['count' => $expiredRequests->count()]);
    }

    /**
     * Handle a single expired approval
     */
    public function handleExpiredApproval(Request $interstateRequest): void
    {
        DB::transaction(function () use ($interstateRequest) {
            // Mark as expired
            $interstateRequest->update([
                'approval_status' => 'expired',
                'inspection_status' => 'completed',
            ]);

            // Create tracking update
            TrackingUpdate::createStatusChange(
                requestId: $interstateRequest->id,
                previousStatus: 'awaiting_user_approval',
                newStatus: 'expired',
                message: 'User approval deadline expired. Request requires admin intervention.',
                createdByType: 'system'
            );

            // Notify user
            $this->notifyUserOfExpiry($interstateRequest);

            // Notify company
            $this->notifyCompanyOfExpiry($interstateRequest);

            // Notify admin for intervention
            $this->notifyAdminOfExpiredApproval($interstateRequest);

            Log::warning('User approval expired', [
                'request_id' => $interstateRequest->id,
                'deadline' => $interstateRequest->user_approval_deadline,
            ]);
        });
    }

    /**
     * Send reminder to user before deadline
     */
    public function sendApprovalReminders(): void
    {
        // Find requests with deadline within next 6 hours
        $reminderDeadline = now()->addHours(6);
        
        $requestsNeedingReminder = Request::where('delivery_mode', 'interstate')
            ->where('inspection_status', 'awaiting_user_approval')
            ->whereNotNull('user_approval_deadline')
            ->where('user_approval_deadline', '<=', $reminderDeadline)
            ->where('user_approval_deadline', '>', now())
            ->whereDoesntHave('trackingUpdates', function ($query) {
                $query->where('update_type', 'reminder_sent')
                    ->where('created_at', '>', now()->subHours(6));
            })
            ->get();

        foreach ($requestsNeedingReminder as $request) {
            $this->sendReminderNotification($request);
        }
    }

    /**
     * Send reminder notification to user
     */
    private function sendReminderNotification(Request $interstateRequest): void
    {
        $user = $interstateRequest->userDetail;
        $hoursRemaining = now()->diffInHours($interstateRequest->user_approval_deadline);
        
        $title = trans('push_notifications.approval_reminder_title', [], $user->lang);
        $body = trans('push_notifications.approval_reminder_body', [
            'request_number' => $interstateRequest->request_number,
            'hours' => $hoursRemaining,
        ], $user->lang);

        $pushData = [
            'type' => 'approval_reminder',
            'request_id' => $interstateRequest->id,
            'hours_remaining' => $hoursRemaining,
        ];

        dispatch(new SendPushNotification($user, $title, $body, $pushData));

        // Create tracking update for reminder
        TrackingUpdate::create([
            'request_id' => $interstateRequest->id,
            'update_type' => 'reminder_sent',
            'message' => "Reminder sent: {$hoursRemaining} hours remaining for approval",
            'created_by_type' => 'system',
        ]);
    }

    /**
     * Notify user of expiry
     */
    private function notifyUserOfExpiry(Request $interstateRequest): void
    {
        $user = $interstateRequest->userDetail;
        
        $title = trans('push_notifications.approval_expired_user_title', [], $user->lang);
        $body = trans('push_notifications.approval_expired_user_body', [
            'request_number' => $interstateRequest->request_number,
        ], $user->lang);

        $pushData = [
            'type' => 'approval_expired',
            'request_id' => $interstateRequest->id,
        ];

        dispatch(new SendPushNotification($user, $title, $body, $pushData));
    }

    /**
     * Notify company of expiry
     */
    private function notifyCompanyOfExpiry(Request $interstateRequest): void
    {
        $company = $interstateRequest->truckingCompany;
        
        if ($company && $company->user) {
            $title = trans('push_notifications.approval_expired_company_title', [], $company->user->lang);
            $body = trans('push_notifications.approval_expired_company_body', [
                'request_number' => $interstateRequest->request_number,
            ], $company->user->lang);

            $pushData = [
                'type' => 'approval_expired',
                'request_id' => $interstateRequest->id,
            ];

            dispatch(new SendPushNotification($company->user, $title, $body, $pushData));
        }
    }

    /**
     * Notify admin for intervention
     */
    private function notifyAdminOfExpiredApproval(Request $interstateRequest): void
    {
        // TODO: Integrate with admin notification system
        // This could be an email, dashboard notification, or Slack message
        
        Log::alert('Admin intervention required: User approval expired', [
            'request_id' => $interstateRequest->id,
            'request_number' => $interstateRequest->request_number,
            'user_id' => $interstateRequest->user_id,
            'company_id' => $interstateRequest->trucking_company_id,
        ]);
    }

    /**
     * Auto-approve if user doesn't respond (optional business logic)
     * 
     * This could be enabled for trusted companies or low-price differences
     */
    public function autoApproveIfEligible(Request $interstateRequest): bool
    {
        // Only auto-approve if price difference is within acceptable range (e.g., 5%)
        $maxAutoApprovePercent = config('interstate.auto_approve_max_percent', 5);
        
        if ($interstateRequest->price_difference_percent > $maxAutoApprovePercent) {
            return false;
        }

        // Only auto-approve for companies with high ratings
        $minRating = config('interstate.auto_approve_min_rating', 4.5);
        
        if ($interstateRequest->truckingCompany->rating < $minRating) {
            return false;
        }

        // Auto-approve
        DB::transaction(function () use ($interstateRequest) {
            $interstateRequest->update([
                'inspection_status' => 'approved_by_user',
                'approval_status' => 'approved',
                'user_approved_at' => now(),
            ]);

            TrackingUpdate::createStatusChange(
                requestId: $interstateRequest->id,
                previousStatus: 'awaiting_user_approval',
                newStatus: 'approved_by_user',
                message: 'Auto-approved: Price difference within acceptable range',
                createdByType: 'system'
            );
        });

        return true;
    }

    /**
     * Get timeout status for display
     */
    public function getTimeoutStatus(Request $interstateRequest): array
    {
        $deadline = $interstateRequest->user_approval_deadline;
        
        if (!$deadline) {
            return [
                'has_deadline' => false,
                'is_expired' => false,
                'time_remaining' => null,
            ];
        }

        $now = now();
        $isExpired = $now->isAfter($deadline);
        
        if ($isExpired) {
            return [
                'has_deadline' => true,
                'is_expired' => true,
                'time_remaining' => null,
                'expired_at' => $deadline->toIso8601String(),
            ];
        }

        $diff = $now->diff($deadline);
        
        return [
            'has_deadline' => true,
            'is_expired' => false,
            'time_remaining' => [
                'hours' => $diff->h + ($diff->days * 24),
                'minutes' => $diff->i,
                'seconds' => $diff->s,
                'total_hours' => $now->diffInHours($deadline, false),
            ],
            'deadline' => $deadline->toIso8601String(),
        ];
    }
}
