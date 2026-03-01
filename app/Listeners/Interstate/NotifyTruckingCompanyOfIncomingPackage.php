<?php

namespace App\Listeners\Interstate;

use App\Events\Interstate\PackageReadyForTransport;
use App\Services\Interstate\Notifications\InterstateFirebaseNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyTruckingCompanyOfIncomingPackage implements ShouldQueue
{
    private InterstateFirebaseNotificationService $notificationService;

    public function __construct(InterstateFirebaseNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function handle(PackageReadyForTransport $event): void
    {
        $request = $event->request;
        
        if (!$request->trucking_company_id || !$request->originHub) {
            return;
        }

        $this->notificationService->notifyTruckingCompanyOfIncomingPackage(
            $request,
            $request->trucking_company_id,
            $request->originHub->hub_name
        );
    }
}
