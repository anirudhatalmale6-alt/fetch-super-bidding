<?php

namespace App\Events\Interstate;

use App\Models\Interstate\RequestLeg;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when local delivery leg is ready for driver bidding
 */
class LocalDeliveryLegReadyForBidding
{
    use Dispatchable, SerializesModels;

    public RequestLeg $leg;

    public function __construct(RequestLeg $leg)
    {
        $this->leg = $leg;
    }
}
