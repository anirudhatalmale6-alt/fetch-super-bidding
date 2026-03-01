<?php

namespace App\Events\Interstate;

use App\Models\Interstate\RequestLeg;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a leg is completed
 */
class LegCompleted
{
    use Dispatchable, SerializesModels;

    public RequestLeg $leg;
    public ?string $previousStatus;

    public function __construct(RequestLeg $leg, ?string $previousStatus = null)
    {
        $this->leg = $leg;
        $this->previousStatus = $previousStatus;
    }
}
