<?php

namespace App\Events\Interstate;

use App\Models\Interstate\RequestLeg;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when the next leg is triggered
 */
class NextLegTriggered
{
    use Dispatchable, SerializesModels;

    public RequestLeg $nextLeg;
    public RequestLeg $completedLeg;

    public function __construct(RequestLeg $nextLeg, RequestLeg $completedLeg)
    {
        $this->nextLeg = $nextLeg;
        $this->completedLeg = $completedLeg;
    }
}
