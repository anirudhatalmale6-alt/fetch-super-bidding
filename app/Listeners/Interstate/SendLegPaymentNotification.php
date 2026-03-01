<?php

namespace App\Listeners\Interstate;

use App\Events\Interstate\LegPaymentRequired;
use App\Services\Interstate\Notifications\InterstateFirebaseNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendLegPaymentNotification implements ShouldQueue
{
    private InterstateFirebaseNotificationService $notificationService;

    public function __construct(InterstateFirebaseNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function handle(LegPaymentRequired $event): void
    {
        if ($event->isAdditionalPayment()) {
            $this->notificationService->sendPaymentRequiredNotification(
                $event->request,
                $event->leg,
                $event->amount
            );
        } elseif ($event->isRefund()) {
            $this->notificationService->sendRefundNotification(
                $event->request,
                $event->leg,
                $event->amount
            );
        }
    }
}
