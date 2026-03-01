<?php

namespace App\Listeners\Interstate;

use App\Events\Interstate\WeightVerificationRequired;
use App\Services\Interstate\Notifications\InterstateFirebaseNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendWeightVerificationNotification implements ShouldQueue
{
    private InterstateFirebaseNotificationService $notificationService;

    public function __construct(InterstateFirebaseNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function handle(WeightVerificationRequired $event): void
    {
        $this->notificationService->sendWeightVerificationNotification(
            $event->request,
            $event->leg,
            $event->verificationData
        );
    }
}
