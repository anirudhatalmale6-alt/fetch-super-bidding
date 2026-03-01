<?php

namespace App\Listeners\Interstate;

use App\Events\Interstate\InterstateRequestCompleted;
use App\Services\Interstate\Notifications\InterstateFirebaseNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendDeliveryCompletedNotification implements ShouldQueue
{
    private InterstateFirebaseNotificationService $notificationService;

    public function __construct(InterstateFirebaseNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function handle(InterstateRequestCompleted $event): void
    {
        $this->notificationService->sendDeliveryCompletedNotification($event->request);
    }
}
