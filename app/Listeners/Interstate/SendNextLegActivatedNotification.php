<?php

namespace App\Listeners\Interstate;

use App\Events\Interstate\NextLegTriggered;
use App\Services\Interstate\Notifications\InterstateFirebaseNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendNextLegActivatedNotification implements ShouldQueue
{
    private InterstateFirebaseNotificationService $notificationService;

    public function __construct(InterstateFirebaseNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function handle(NextLegTriggered $event): void
    {
        $this->notificationService->sendNextLegActivatedNotification(
            $event->nextLeg->request,
            $event->nextLeg
        );
    }
}
